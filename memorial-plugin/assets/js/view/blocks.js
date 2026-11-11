/**
 * Behaviour of the plugin’s blocks on the site — Interactivity API (script module).
 *
 * Every block has its own store; the markup (PHP) marks the root of the block with
 * data-wp-interactive="memorial-plugin/<store>" and data-wp-init / data-wp-on-- directives:
 *  - faq, guide — topics and the search with suggestions (questions and answers, “Good to know”);
 *  - reviews    — branch tabs and third-party widgets (after the cookie consent or by the button);
 *  - rewio      — reviews from Rewio: tabs, sorting, filter by site, “Read more”, “Show more”;
 *  - book       — guest book: “Read more”;
 *  - catalog    — sorting, “Show more”, the section bar that sticks under the header, current section;
 *  - card       — product card with several photos: arrows, dots, swipe;
 *  - works      — “Our works”: kind filter, sorting, photos, random cards;
 *  - focus      — the chosen point of a photo in the middle of a tile.
 * The cemeteries block has its own module (cemeteries.js).
 */
import { store, getElement, getConfig } from '@wordpress/interactivity';

// Texts of the blocks, translated on the server (core/assets.php, memorial_plugin_view_i18n).
const cfg = getConfig( 'memorial-plugin' ) || {};
const T = cfg.i18n || {};
const txt = ( key, ...args ) => {
	let i = 0;
	return String( T[ key ] || key )
		.replace( /%(\d)\$[sd]/g, ( m, n ) => args[ n - 1 ] )
		.replace( /%[sd]/g, () => args[ i++ ] );
};
// Plural forms by category of Intl.PluralRules (one, few, many, other…) — prepared on the server with _n().
let plurals;
const tn = ( key, n ) => {
	try {
		plurals ||= new Intl.PluralRules( cfg.locale || 'en' );
	} catch {
		plurals = new Intl.PluralRules( 'en' );
	}
	const forms = T[ key ] || {};
	return String( forms[ plurals.select( n ) ] || forms.other || n ).replace(
		'%d',
		n
	);
};

const d = document;
// smooth scrolling only for those who have not switched animations off
const smooth = () =>
	window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		? 'auto'
		: 'smooth';

/* ---------- Search with suggestions (questions and answers, “Good to know”) ----------
   Word endings are ignored (“похороны” = “похорон”), synonyms count (“стоимость” = “цена”), stop words do not. */
const sNorm = ( s ) => ( s || '' ).toLowerCase().replace( /ё/g, 'е' );
const sStop = new Set(
	'как что где кто когда какие какой какая каких можно ли не на в во и или по за для от до из у о об при это мне нам вы вас ваш если а но то же бы нужно надо есть ли'.split(
		' '
	)
);
const sStem = ( w ) => {
	if ( w.length >= 8 ) {
		return w.slice( 0, -3 );
	}
	if ( w.length >= 6 ) {
		return w.slice( 0, -2 );
	}
	return w.length >= 5 ? w.slice( 0, -1 ) : w;
};
const sSyn = [
	[ 'цен', 'стоим', 'стоит', 'скольк', 'прайс' ],
	[ 'докум', 'справк', 'бумаг', 'свидетельст' ],
	[ 'пособ', 'выплат', 'компенсац' ],
	[ 'кладбищ', 'могил', 'захоронен' ],
	[ 'крема', 'прах' ],
	[ 'памятник', 'надгроб' ],
	[ 'перевоз', 'транспорт', 'катафалк', 'доставк' ],
	[ 'поминк', 'поминал', 'обед' ],
	[ 'умер', 'смерт', 'скончал' ],
	[ 'морг' ],
];
const sWords = ( q ) =>
	sNorm( q )
		.split( /[^a-zа-я0-9]+/ )
		.filter( ( w ) => w.length > 1 && ! sStop.has( w ) )
		.map( ( w ) => {
			const st = sStem( w );
			const g = sSyn.find( ( gr ) =>
				gr.some( ( x ) => st.startsWith( x ) || x.startsWith( st ) )
			);
			return g ? [ st, ...g ] : [ st ];
		} );
const sEsc = ( s ) => s.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
const sHtml = ( s ) =>
	s.replace(
		/[&<>"]/g,
		( c ) =>
			( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' } )[ c ]
	);
const sMark = ( text, words ) => {
	const alts = words.flat().filter( ( x ) => x.length > 1 );
	if ( ! alts.length ) {
		return sHtml( text );
	}
	// the whole word is marked
	const re = new RegExp(
		'([а-яёa-z0-9]*(?:' +
			alts.map( ( a ) => sEsc( a ).replace( /е/g, '[её]' ) ).join( '|' ) +
			')[а-яёa-z0-9]*)',
		'gi'
	);
	return sHtml( text ).replace( re, '<mark>$1</mark>' );
};
const sFlash = ( el ) => {
	el.classList.remove( 'is-flash' );
	void el.offsetWidth; // restart the animation
	el.classList.add( 'is-flash' );
};
// Scroll to an element: under the header and the stuck search field.
const sTo = ( el ) => {
	const hh =
		( d.querySelector( '.header' )?.getBoundingClientRect().bottom || 0 ) +
		( el
			.closest( '[data-faq],[data-guide]' )
			?.querySelector( '.msearch__bar' )?.offsetHeight || 0 ) +
		12;
	window.scrollTo( {
		top: window.scrollY + el.getBoundingClientRect().top - hh,
		behavior: smooth(),
	} );
};
const restart = ( box, cls ) => {
	box.classList.remove( cls );
	void box.offsetWidth; // restart the animation
	box.classList.add( cls );
};

/**
 * Search field with suggestions.
 *
 * @param {Element}  box   [data-msearch].
 * @param {Object[]} items [{q, t, g?, …}].
 * @param {Object}   opts  pick(item), filter(words, found), status(found, words, value), enter().
 * @return {Object} { run }.
 */
function memSearch( box, items, opts ) {
	const q = box.querySelector( '[data-msearch-q]' );
	const list = box.querySelector( '.msearch__list' );
	const x = box.querySelector( '[data-msearch-clear]' );
	const st = box.querySelector( '[data-msearch-status]' );
	const none = box.querySelector( '[data-msearch-none]' );
	let pop = box.querySelector( '[data-msearch-pop]' );
	items.forEach( ( it ) => {
		it._t = sNorm( it.t || it.q );
		it._q = sNorm( it.q );
	} );
	const score = ( words ) => {
		let best = 0;
		items.forEach( ( it ) => {
			it.n = words.filter( ( alts ) =>
				alts.some( ( w ) => it._t.includes( w ) )
			).length;
			// found in the question itself — higher
			it.k =
				words.filter( ( alts ) =>
					alts.some( ( w ) => it._q.includes( w ) )
				).length *
					4 +
				it.n * 2 +
				( it.k0 || 0 ) * 0.5;
			if ( it.n > best ) {
				best = it.n;
			}
		} );
		// all the words; when nothing has all of them — the most of them
		return best ? items.filter( ( it ) => it.n === best ) : [];
	};
	let sel = -1;
	let shown = [];
	let timer;
	let pre0 = false;
	// “What people search for”: the final query (not every letter) and how many were found
	let sent = '';
	let lt;
	let last = [ '', 0 ];
	const log = () => {
		clearTimeout( lt );
		const [ val, n ] = last;
		if ( ! box.dataset.log || val.length < 3 || val === sent ) {
			return;
		}
		sent = val;
		const f = new window.FormData();
		f.append( 'action', 'mem_sq' );
		f.append( 'q', val );
		f.append( 'n', n );
		f.append( 'w', box.dataset.w || 'f' );
		try {
			if ( window.navigator.sendBeacon ) {
				window.navigator.sendBeacon( box.dataset.log, f );
			} else {
				window.fetch( box.dataset.log, {
					method: 'POST',
					body: f,
					keepalive: true,
				} );
			}
		} catch {
			// no storage or no network — not important here
		}
	};
	window.addEventListener( 'pagehide', log );
	const close = () => {
		list.hidden = true;
		q.setAttribute( 'aria-expanded', 'false' );
		sel = -1;
	};
	// on a phone the list does not go under the keyboard
	const fitList = () => {
		const vv = window.visualViewport;
		const vh = vv ? vv.height + vv.offsetTop : window.innerHeight;
		list.style.maxHeight =
			Math.max( 160, vh - list.getBoundingClientRect().top - 12 ) + 'px';
	};
	const render = ( words, found ) => {
		shown = found
			.slice()
			.sort( ( a, b ) => b.k - a.k )
			.slice( 0, window.innerWidth < 700 ? 5 : 7 );
		list.innerHTML = shown
			.map(
				( it, i ) =>
					'<li role="option" id="' +
					q.id +
					'-o' +
					i +
					'" class="msearch__opt"><span class="msearch__oq">' +
					sMark( it.q, words ) +
					'</span>' +
					( it.g
						? '<span class="msearch__og">' +
							sHtml( it.g ) +
							'</span>'
						: '' ) +
					'</li>'
			)
			.join( '' );
		list.hidden = ! shown.length || d.activeElement !== q;
		q.setAttribute( 'aria-expanded', String( ! list.hidden ) );
		if ( ! list.hidden ) {
			fitList();
		}
		sel = -1;
	};
	const run = () => {
		const val = q.value.trim();
		const words = sWords( val );
		x.hidden = ! val;
		( box.closest( '[data-faq],[data-guide]' ) || box ).classList.toggle(
			'is-searching',
			!! words.length
		);
		const found = words.length ? score( words ) : [];
		opts.filter( words, found );
		render( words, found );
		if ( st ) {
			st.innerHTML = opts.status( found, words, val );
		}
		if ( none ) {
			none.hidden = ! words.length || found.length > 0;
		}
		if ( pop ) {
			pop.hidden = !! val;
		}
		clearTimeout( lt );
		// stopped typing — the query is final
		if ( words.length && ! pre0 ) {
			last = [ val, found.length ];
			lt = setTimeout( log, 2500 );
		}
		pre0 = false;
	};
	const go = ( i ) => {
		const it = shown[ i ];
		if ( ! it ) {
			return;
		}
		log();
		close();
		q.blur();
		opts.pick( it );
	};
	q.addEventListener( 'input', () => {
		clearTimeout( timer );
		timer = setTimeout( run, 90 );
	} );
	q.addEventListener( 'focus', () => {
		if ( window.innerWidth < 700 ) {
			// On a phone the field goes up under the header, so the suggestions fit above the keyboard.
			// The browser scrolls to the field itself when the keyboard opens — lift it after that.
			const bar = box.querySelector( '.msearch__bar' );
			const lift = () => {
				const hh =
					d.querySelector( '.header' )?.getBoundingClientRect()
						.bottom || 0;
				const top = bar.getBoundingClientRect().top;
				if ( Math.abs( top - hh ) > 16 ) {
					window.scrollTo( {
						top: window.scrollY + top - hh,
						behavior: 'instant',
					} );
				}
				if ( ! list.hidden ) {
					fitList();
				}
			};
			setTimeout( lift, 60 );
			setTimeout( lift, 450 );
			window.visualViewport?.addEventListener(
				'resize',
				() => setTimeout( lift, 30 ),
				{ once: true }
			);
		}
		if ( q.value.trim() && shown.length ) {
			list.hidden = false;
			q.setAttribute( 'aria-expanded', 'true' );
			fitList();
		}
	} );
	q.addEventListener( 'blur', () => setTimeout( close, 180 ) );
	q.addEventListener( 'keydown', ( e ) => {
		const options = [ ...list.children ];
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			if ( list.hidden || ! options.length ) {
				return;
			}
			e.preventDefault();
			sel =
				( sel + ( e.key === 'ArrowDown' ? 1 : -1 ) + options.length ) %
				options.length;
			options.forEach( ( o, i ) =>
				o.classList.toggle( 'is-sel', i === sel )
			);
			q.setAttribute( 'aria-activedescendant', options[ sel ].id );
			options[ sel ].scrollIntoView( { block: 'nearest' } );
		} else if ( e.key === 'Enter' ) {
			e.preventDefault();
			if ( sel >= 0 ) {
				go( sel );
			} else {
				// Enter — hide the keyboard and show what was found
				clearTimeout( timer );
				run();
				log();
				close();
				q.blur();
				opts.enter?.();
			}
		} else if ( e.key === 'Escape' ) {
			close();
		}
	} );
	list.addEventListener( 'mousedown', ( e ) => e.preventDefault() );
	list.addEventListener( 'click', ( e ) => {
		const o = e.target.closest( '.msearch__opt' );
		if ( o ) {
			go( [ ...list.children ].indexOf( o ) );
		}
	} );
	x.addEventListener( 'click', () => {
		q.value = '';
		run();
		q.focus();
	} );
	pop?.querySelectorAll( '[data-q]' ).forEach( ( b ) => {
		// a word without results is not shown
		if ( ! score( sWords( b.dataset.q ) ).length ) {
			b.remove();
			return;
		}
		b.addEventListener( 'click', () => {
			q.value = b.dataset.q;
			run();
			log();
			close();
			opts.enter?.();
		} );
	} );
	if ( pop && ! pop.querySelector( '[data-q]' ) ) {
		pop.remove();
		pop = null;
	}
	window.visualViewport?.addEventListener( 'resize', () => {
		if ( ! list.hidden ) {
			fitList();
		}
	} );
	// /vopros-otvet/?q=пособие — a query from a link is not counted
	const pre = new URLSearchParams( window.location.search ).get( 'q' );
	if ( pre ) {
		q.value = pre;
		pre0 = true;
		run();
		close();
	} else {
		run();
	}
	box.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '.msearch__reset' ) ) {
			q.value = '';
			run();
		}
	} );
	return { run };
}

// A link to a question (#v-…) opens it — on any page.
const openHash = () => {
	const el =
		window.location.hash.length > 1 &&
		d.getElementById(
			decodeURIComponent( window.location.hash.slice( 1 ) )
		);
	if ( ! el ) {
		return;
	}
	if ( el.tagName === 'DETAILS' ) {
		el.hidden = false;
		el.open = true;
		sFlash( el );
		setTimeout( () => sTo( el ), 250 );
	}
	for ( let p = el.parentElement; p; p = p.parentElement ) {
		if ( p.tagName === 'DETAILS' ) {
			p.open = true;
		}
	}
};
openHash();
window.addEventListener( 'hashchange', openHash );

/* ---------- Questions and answers ---------- */
store( 'memorial-plugin/faq', {
	callbacks: {
		init() {
			const { ref: box } = getElement();
			const chips = [ ...box.querySelectorAll( '[data-faq-group]' ) ];
			const groups = [ ...box.querySelectorAll( '.mfaq__group' ) ];
			const dets = [ ...box.querySelectorAll( 'details' ) ];
			const toc = box.querySelector( '.mfaq__toc' );
			const setChip = ( id ) =>
				chips.forEach( ( c ) => {
					const on = c.dataset.faqGroup === id;
					c.classList.toggle( 'is-on', on );
					c.setAttribute( 'aria-pressed', String( on ) );
				} );
			let ms = null;
			chips.forEach( ( c ) =>
				c.addEventListener( 'click', () => {
					const id = c.dataset.faqGroup;
					const inp = box.querySelector( '[data-msearch-q]' );
					if ( inp && inp.value ) {
						inp.value = '';
						ms?.run();
					}
					dets.forEach( ( el ) => {
						el.hidden = false;
						el.open = false;
					} );
					groups.forEach( ( g ) => {
						g.hidden = !! id && g.id !== id;
					} );
					setChip( id );
					const first = groups.find( ( g ) => ! g.hidden ) || box;
					if ( id || first.getBoundingClientRect().top < 0 ) {
						sTo( first );
					}
				} )
			);
			// many topics — a few first, the rest by the button
			if ( toc && chips.length > 7 ) {
				const more = d.createElement( 'button' );
				more.type = 'button';
				more.className = 'chip mfaq__more-t';
				more.textContent = txt( 'moreTopics', chips.length - 6 );
				more.addEventListener( 'click', () => {
					toc.classList.remove( 'is-cut' );
					more.remove();
				} );
				toc.classList.add( 'is-cut' );
				toc.append( more );
			}
			const sbox = box.querySelector( '[data-msearch]' );
			if ( ! sbox ) {
				return;
			}
			const items = dets.map( ( el ) => {
				const s = el.querySelector( 'summary' );
				return {
					el,
					s,
					html: s.innerHTML,
					q: s.textContent.trim(),
					t: el.textContent,
					g: el.dataset.g || '',
				};
			} );
			ms = memSearch( sbox, items, {
				filter: ( words, found ) => {
					setChip( '' );
					const ok = new Set( found );
					items.forEach( ( it ) => {
						const vis = ! words.length || ok.has( it );
						it.el.hidden = ! vis;
						it.el.open = false;
						it.s.innerHTML =
							words.length && vis
								? sMark( it.q, words )
								: it.html;
					} );
					if ( words.length && found.length && found.length <= 2 ) {
						found.forEach( ( it ) => {
							it.el.open = true;
						} );
					}
					groups.forEach( ( g ) => {
						g.hidden = ! g.querySelector( 'details:not([hidden])' );
					} );
					restart( box, 'is-upd' );
				},
				status: ( found, words, val ) => {
					if ( ! words.length ) {
						return (
							tn( 'questionsWithAnswers', items.length ) +
							'. ' +
							txt( 'startTyping' )
						);
					}
					return found.length
						? sHtml(
								txt(
									'foundBelow',
									val,
									tn( 'questions', found.length )
								)
							) +
								' <button type="button" class="msearch__reset">' +
								sHtml( txt( 'showAll' ) ) +
								'</button>'
						: '';
				},
				pick: ( it ) => {
					it.el.hidden = false;
					it.el.closest( '.mfaq__group' ).hidden = false;
					it.el.open = true;
					sTo( it.el );
					sFlash( it.el );
				},
				enter: () => {
					const f = box.querySelector( 'details:not([hidden])' );
					if ( f ) {
						sTo( f.closest( '.mfaq__group' ) || f );
					}
				},
			} );
		},
	},
} );

/* ---------- “Good to know”: article cards and the search over them and the questions of the site ---------- */
store( 'memorial-plugin/guide', {
	callbacks: {
		init() {
			const { ref: box } = getElement();
			let idx = [];
			try {
				idx = JSON.parse(
					box.querySelector( '[data-guide-idx]' ).textContent
				);
			} catch {
				return;
			}
			const sbox = box.querySelector( '[data-msearch]' );
			const cards = [ ...box.querySelectorAll( '[data-card]' ) ];
			idx.forEach( ( it ) => {
				it.k0 = { p: 2, h: 1 }[ it.k ] || 0;
			} );
			memSearch( sbox, idx, {
				filter: ( words, found ) => {
					const hit = new Set( found.map( ( it ) => it.c ) );
					cards.forEach( ( c ) => {
						c.hidden =
							!! words.length && ! hit.has( +c.dataset.card );
					} );
					restart( box, 'is-upd' );
				},
				status: ( found, words, val ) => {
					if ( ! words.length || ! found.length ) {
						return '';
					}
					const arts = new Set(
						found
							.filter( ( it ) => it.c >= 0 )
							.map( ( it ) => it.c )
					).size;
					const other = found.filter( ( it ) => it.c < 0 ).length;
					const what = [
						arts ? tn( 'articles', arts ) : '',
						other ? tn( 'answersElsewhere', other ) : '',
					]
						.filter( Boolean )
						.join( ' ' + txt( 'and' ) + ' ' );
					return (
						sHtml( txt( 'guideFound', val, what ) ) +
						' <button type="button" class="msearch__reset">' +
						sHtml( txt( 'showAll' ) ) +
						'</button>'
					);
				},
				pick: ( it ) => {
					window.location.href = it.u;
				},
				enter: () => {
					const c = cards.find( ( card ) => ! card.hidden );
					if ( c ) {
						sTo( c );
					}
				},
			} );
		},
	},
} );

/* ---------- Tabs (reviews of branches, Rewio) with the arrow keys ---------- */
function tabs( box, onSelect ) {
	const list = [ ...box.querySelectorAll( '[role="tab"]' ) ];
	const sel = ( t ) =>
		list.forEach( ( x ) => {
			const on = x === t;
			x.setAttribute( 'aria-selected', String( on ) );
			x.tabIndex = on ? 0 : -1;
			x.classList.toggle( 'is-on', on );
			d.getElementById( x.getAttribute( 'aria-controls' ) ).hidden = ! on;
			if ( on && onSelect ) {
				onSelect( t );
			}
		} );
	list.forEach( ( t, i ) => {
		t.addEventListener( 'click', () => sel( t ) );
		t.addEventListener( 'keydown', ( e ) => {
			const dir = { ArrowRight: 1, ArrowLeft: -1 }[ e.key ] || 0;
			if ( dir ) {
				const n = list[ ( i + dir + list.length ) % list.length ];
				n.focus();
				sel( n );
			}
		} );
	} );
	list[ 0 ]?.classList.add( 'is-on' );
}

// “Read more” / “Collapse” of a long review: the text is the button’s previous element.
function toggleMore( button ) {
	const open = button.previousElementSibling.classList.toggle( 'is-open' );
	button.setAttribute( 'aria-expanded', String( open ) );
	button.textContent = open ? txt( 'collapse' ) : txt( 'readMore' );
}

/* ---------- Reviews: branch tabs and third-party widgets ---------- */
store( 'memorial-plugin/reviews', {
	callbacks: {
		tabs() {
			tabs( getElement().ref );
		},
		// The code of a widget runs after the cookie consent (or by the button), when the block is near.
		embed() {
			const { ref: box } = getElement();
			const load = () => {
				if ( box.classList.contains( 'is-loaded' ) ) {
					return;
				}
				const frag = box
					.querySelector( 'template' )
					.content.cloneNode( true );
				// scripts of a template do not run by themselves — create them again
				frag.querySelectorAll( 'script' ).forEach( ( old ) => {
					const n = d.createElement( 'script' );
					[ ...old.attributes ].forEach( ( a ) =>
						n.setAttribute( a.name, a.value )
					);
					n.text = old.textContent;
					old.replaceWith( n );
				} );
				box.replaceChildren( frag );
				box.classList.add( 'is-loaded' );
			};
			let ok = false;
			try {
				ok = window.localStorage.getItem( 'mem_consent_v1' ) === 'yes';
			} catch {
				// no storage or no network — not important here
			}
			if ( ok || ! window.memTrack?.need ) {
				const io = new window.IntersectionObserver(
					( es ) => {
						if ( es.some( ( e ) => e.isIntersecting ) ) {
							io.disconnect();
							load();
						}
					},
					{ rootMargin: '400px' }
				);
				io.observe( box );
			}
			box.querySelector( '[data-embed-code-load]' )?.addEventListener(
				'click',
				load
			);
			d.addEventListener( 'mem:consent', load );
		},
	},
} );

/* ---------- Reviews from Rewio ---------- */
store( 'memorial-plugin/rewio', {
	callbacks: {
		init() {
			const { ref: box } = getElement();
			tabs( box );
			const step = +box.dataset.step || 6;
			// every tab: order, site, how many are shown
			box.querySelectorAll( '.rwo__panel' ).forEach( ( panel ) => {
				const list = panel.querySelector( '.rwo__list' );
				const cards = [ ...list.querySelectorAll( '.rwo__card' ) ];
				const st = { sort: '', dir: 'desc', src: '', shown: step };
				const wrap = panel.querySelector( '.rwo__morewrap' );
				const apply = () => {
					const view = cards.filter(
						( c ) => ! st.src || c.dataset.s === st.src
					);
					const k = st.dir === 'asc' ? -1 : 1;
					if ( st.sort === 'd' ) {
						view.sort(
							( a, b ) =>
								k * b.dataset.d.localeCompare( a.dataset.d )
						);
					}
					if ( st.sort === 'r' ) {
						view.sort(
							( a, b ) =>
								k * ( +b.dataset.r - +a.dataset.r ) ||
								b.dataset.d.localeCompare( a.dataset.d )
						);
					}
					cards.forEach( ( c ) => {
						c.hidden = true;
					} );
					view.forEach( ( c, i ) => {
						list.appendChild( c );
						c.hidden = i >= st.shown;
					} );
					if ( wrap ) {
						wrap.hidden = view.length <= st.shown;
					}
				};
				panel.addEventListener( 'click', ( e ) => {
					// sorting: the active button changes the direction, another one is chosen (newest/highest first)
					const s = e.target.closest( '[data-rwo-sort]' );
					if ( s ) {
						const on = s.getAttribute( 'aria-pressed' ) === 'true';
						st.dir = on && st.dir === 'desc' ? 'asc' : 'desc';
						st.sort = s.dataset.rwoSort;
						st.shown = step;
						panel
							.querySelectorAll( '[data-rwo-sort]' )
							.forEach( ( b ) => {
								const me = b === s;
								const desc = me && st.dir === 'desc';
								b.setAttribute( 'aria-pressed', String( me ) );
								b.querySelector( 'i' ).textContent =
									me && st.dir === 'asc' ? '↑' : '↓';
								if ( b.dataset.rwoSort === 'd' ) {
									b.title = desc
										? txt( 'newestFirst' )
										: txt( 'sortByDate' );
								} else {
									b.title = desc
										? txt( 'highestFirst' )
										: txt( 'sortByRating' );
								}
							} );
						apply();
						return;
					}
					// filter by site — the buttons of the sites in the summary; pressed again — all
					const p = e.target.closest( '[data-rwo-src]' );
					if ( p ) {
						const on = p.getAttribute( 'aria-pressed' ) === 'true';
						st.src = on ? '' : p.dataset.rwoSrc;
						st.shown = step;
						panel
							.querySelectorAll( '[data-rwo-src]' )
							.forEach( ( b ) =>
								b.setAttribute(
									'aria-pressed',
									String( ! on && b === p )
								)
							);
						const note = panel.querySelector( '.rwo__shown' );
						if ( note ) {
							note.innerHTML = st.src
								? sHtml(
										txt(
											'reviewsOf',
											p.querySelector(
												'span:not(.rw-ico)'
											).textContent
										)
									) +
									' · <button type="button" class="rwo__all">' +
									sHtml( txt( 'showAllLower' ) ) +
									'</button>'
								: '';
						}
						apply();
						return;
					}
					if ( e.target.closest( '.rwo__all' ) ) {
						panel
							.querySelector(
								'[data-rwo-src][aria-pressed="true"]'
							)
							?.click();
						return;
					}
					if ( e.target.closest( '.rwo__showmore' ) ) {
						const before = st.shown;
						st.shown += step;
						apply();
						cards
							.filter( ( c ) => ! c.hidden )
							.slice( before )
							.forEach( ( c ) => c.classList.add( 'is-in' ) );
					}
				} );
			} );
			box.addEventListener( 'click', ( e ) => {
				const m = e.target.closest( '.rwo__more' );
				if ( m ) {
					toggleMore( m );
				}
			} );
		},
	},
} );

/* ---------- Guest book ---------- */
store( 'memorial-plugin/book', {
	actions: {
		more() {
			toggleMore( getElement().ref );
		},
	},
} );

/* ---------- Catalog and “Our works”: sorting, “Show more”, section bar ---------- */
// Sorting of cards: by price (“on request” last), by year, or the order of the site.
function sortBy( items, dir ) {
	return items.sort( ( a, b ) => {
		if ( ! dir ) {
			return a.dataset.i - b.dataset.i;
		}
		if ( dir === 'new' ) {
			return (
				( +b.dataset.year || 0 ) - ( +a.dataset.year || 0 ) ||
				a.dataset.i - b.dataset.i
			);
		}
		const pa = +a.dataset.price;
		const pb = +b.dataset.price;
		if ( ! pa !== ! pb ) {
			return pa ? -1 : 1;
		}
		return dir === 'asc' ? pa - pb : pb - pa;
	} );
}

// Is someone scrolling the row of section buttons by hand (the last 2.5 s)?
const navBusy = ( nav ) => nav._touched && Date.now() - nav._touched < 2500;
// Show a button of the row only when it is not fully visible.
function chipIntoView( nav, a, animate ) {
	if ( nav.scrollWidth <= nav.clientWidth ) {
		return;
	}
	const l = a.offsetLeft - nav.offsetLeft;
	const r = l + a.offsetWidth;
	if (
		l >= nav.scrollLeft + 8 &&
		r <= nav.scrollLeft + nav.clientWidth - 8
	) {
		return;
	}
	nav.scrollTo( {
		left: Math.max( 0, l - 40 ),
		behavior: animate ? smooth() : 'auto',
	} );
}

// Section bar: in the text all the buttons are visible (several rows); stuck under the header — one row
// with scrolling and arrows; the current section is highlighted.
function sectionBar( bar ) {
	const pin = bar.previousElementSibling;
	const wrap = bar.querySelector( '.mcat__navwrap' );
	const nav = wrap?.querySelector( '.mcat__nav' );
	if ( ! nav ) {
		return;
	}
	const edges = () => {
		wrap.classList.toggle( 'has-l', nav.scrollLeft > 4 );
		wrap.classList.toggle(
			'has-r',
			nav.scrollLeft + nav.clientWidth < nav.scrollWidth - 4
		);
	};
	[ 'l', 'r' ].forEach( ( s ) => {
		const b = d.createElement( 'button' );
		b.type = 'button';
		b.className = 'mcat__arr mcat__arr--' + s;
		b.tabIndex = -1;
		b.setAttribute(
			'aria-label',
			s === 'l' ? txt( 'scrollLeft' ) : txt( 'scrollRight' )
		);
		b.textContent = s === 'l' ? '‹' : '›';
		b.addEventListener( 'click', () =>
			nav.scrollBy( {
				left: ( s === 'l' ? -1 : 1 ) * nav.clientWidth * 0.7,
			} )
		);
		wrap.appendChild( b );
	} );
	let tick = false;
	const check = () => {
		tick = false;
		const stuck =
			pin.getBoundingClientRect().top <
			parseFloat( window.getComputedStyle( bar ).top ) - 1;
		if ( stuck !== bar.classList.contains( 'is-stuck' ) ) {
			bar.classList.toggle( 'is-stuck', stuck );
			const on = nav.querySelector( '.is-on' );
			if ( stuck && on && ! navBusy( nav ) ) {
				chipIntoView( nav, on, false );
			}
		}
		edges();
	};
	window.addEventListener(
		'scroll',
		() => {
			if ( ! tick ) {
				tick = true;
				window.requestAnimationFrame( check );
			}
		},
		{ passive: true }
	);
	// sections below the bar: the heading stops under the bar, not behind it
	const box = bar.closest( '.mcat, .works' ) || bar.parentElement;
	const margin = () => {
		const h = bar.offsetHeight + 12;
		box.querySelectorAll( '[id]' ).forEach( ( el ) => {
			if ( ! bar.contains( el ) ) {
				el.style.scrollMarginTop = h + 'px';
			}
		} );
	};
	window.addEventListener( 'resize', () => {
		check();
		margin();
	} );
	nav.addEventListener( 'scroll', edges, { passive: true } );
	check();
	margin();
	// A section button: smoothly to its heading. The address is not changed — otherwise
	// reloading the page or “Back” jumps to this section by itself.
	nav.addEventListener( 'click', ( e ) => {
		const a = e.target.closest( 'a[href^="#"]' );
		const t =
			a && d.getElementById( decodeURIComponent( a.hash.slice( 1 ) ) );
		if ( ! t ) {
			return;
		}
		e.preventDefault();
		margin();
		nav.querySelectorAll( 'a.is-on' ).forEach( ( x ) =>
			x.classList.remove( 'is-on' )
		);
		a.classList.add( 'is-on' );
		nav._touched = 0;
		const l = a.offsetLeft - nav.offsetLeft;
		nav.scrollTo( {
			left: Math.max( 0, l - ( nav.clientWidth - a.offsetWidth ) / 2 ),
			behavior: smooth(),
		} );
		nav._chosen = Date.now();
		t.scrollIntoView( { behavior: smooth(), block: 'start' } );
		// the bar sticks and may change its height — correct once more
		setTimeout( () => {
			const top = t.getBoundingClientRect().top;
			const need = bar.getBoundingClientRect().bottom + 12;
			if ( Math.abs( top - need ) > 6 ) {
				window.scrollBy( { top: top - need, behavior: smooth() } );
			}
		}, 700 );
	} );
	// Current section. The row scrolls to it only when it is not visible and nobody scrolls the row by hand.
	const links = [ ...nav.querySelectorAll( 'a[href^="#"]' ) ];
	const secs = links
		.map( ( a ) =>
			d.getElementById( decodeURIComponent( a.hash.slice( 1 ) ) )
		)
		.filter( Boolean );
	if ( ! secs.length || ! window.IntersectionObserver ) {
		return;
	}
	[ 'touchmove', 'wheel' ].forEach( ( ev ) =>
		nav.addEventListener(
			ev,
			() => {
				nav._touched = Date.now();
			},
			{ passive: true }
		)
	);
	let first = true;
	const io = new window.IntersectionObserver(
		( es ) => {
			es.forEach( ( en ) => {
				// on the way to a chosen section the intermediate ones are not highlighted
				if (
					! en.isIntersecting ||
					( nav._chosen && Date.now() - nav._chosen < 1200 )
				) {
					return;
				}
				links.forEach( ( a ) => {
					const on = a.hash.slice( 1 ) === en.target.id;
					a.classList.toggle( 'is-on', on );
					if ( on && ! first && ! navBusy( nav ) ) {
						chipIntoView( nav, a, true );
					}
				} );
			} );
			first = false;
		},
		{ rootMargin: '-40% 0px -55% 0px' }
	);
	secs.forEach( ( s ) => io.observe( s ) );
}

// Large sections: a part of the products first, the rest by “Show more”.
function showMore( g ) {
	const step = +g.dataset.more;
	let shown = step;
	const btn = d.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'btn btn--line mcat__more';
	g.after( btn );
	const apply = () => {
		const li = [ ...g.children ];
		li.forEach( ( x, i ) => {
			x.hidden = i >= shown;
		} );
		const rest = li.length - shown;
		btn.hidden = rest <= 0;
		btn.textContent =
			rest > step
				? txt( 'showMoreOf', Math.min( step, rest ), rest )
				: txt( 'showMore', Math.min( step, rest ) );
	};
	btn.addEventListener( 'click', () => {
		const firstNew = g.children[ shown ];
		shown += step;
		apply();
		// for the keyboard — to the first of the new ones
		firstNew?.querySelector( 'a,button' )?.focus( { preventScroll: true } );
	} );
	g.memMore = apply;
	// a link to a product (#p-123) in the hidden part — show up to it
	const h =
		window.location.hash.length > 1 &&
		g.querySelector( window.location.hash.replace( /[^\w#-]/g, '' ) );
	if ( h ) {
		const i = [ ...g.children ].indexOf( h.closest( 'li' ) );
		if ( i >= shown ) {
			shown = Math.ceil( ( i + 1 ) / step ) * step;
		}
	}
	apply();
	if ( h ) {
		setTimeout( () => h.scrollIntoView( { block: 'center' } ), 100 );
	}
}

store( 'memorial-plugin/catalog', {
	actions: {
		sort( event ) {
			const box = event.target.closest( '[data-mcat]' );
			box.querySelectorAll( '.mcat__grid' ).forEach( ( g ) => {
				sortBy( [ ...g.children ], event.target.value ).forEach(
					( li ) => g.appendChild( li )
				);
				g.memMore?.();
			} );
		},
	},
	callbacks: {
		init() {
			const { ref: box } = getElement();
			box.querySelectorAll( '.mcat__grid[data-more]' ).forEach(
				showMore
			);
			box.querySelectorAll( '.mcat__bar' ).forEach( sectionBar );
		},
	},
} );

/* ---------- Product card with several photos: arrows, dots, swipe ----------
   The link of the card always leads to the current photo — the large view opens with it. */
function cardGo( media, dir ) {
	const box = media.querySelector( '.mcard__img' );
	const phs = [ ...box.querySelectorAll( '.mcard__ph' ) ];
	if ( phs.length < 2 ) {
		return;
	}
	const i = phs.findIndex( ( x ) => x.classList.contains( 'is-on' ) );
	const n = ( i + dir + phs.length ) % phs.length;
	const ph = phs[ n ];
	phs.forEach( ( x ) => x.classList.toggle( 'is-on', x === ph ) );
	// the next photo — in advance
	const after = phs[ ( n + dir + phs.length ) % phs.length ];
	if ( after ) {
		after.loading = 'eager';
	}
	if ( box.dataset.mbg0 === undefined ) {
		box.dataset.mbg0 = box.style.getPropertyValue( '--mbg' ) || '';
	}
	let bg = box.dataset.mbg0;
	if ( n ) {
		bg = ph.dataset.bg ? 'url("' + ph.dataset.bg + '")' : '';
	}
	box.classList.toggle( 'has-bg', !! bg );
	if ( bg ) {
		box.style.setProperty( '--mbg', bg );
	} else {
		box.style.removeProperty( '--mbg' );
	}
	const link = box.closest( '.mcard__link' );
	if ( link && ph.dataset.big && link.hasAttribute( 'data-lb' ) ) {
		link.href = ph.dataset.big;
	}
	box.closest( '.mcard' )
		?.querySelectorAll( '.mcard__dots i' )
		.forEach( ( dot, k ) => dot.classList.toggle( 'is-on', k === n ) );
}

// Swipe left or right on a photo; the click after a swipe does not open the large view.
function swipe( el, onSwipe, link ) {
	let x0 = null;
	let y0 = 0;
	el.addEventListener(
		'touchstart',
		( e ) => {
			x0 = e.touches[ 0 ].clientX;
			y0 = e.touches[ 0 ].clientY;
		},
		{ passive: true }
	);
	el.addEventListener( 'touchend', ( e ) => {
		if ( x0 === null ) {
			return;
		}
		const dx = e.changedTouches[ 0 ].clientX - x0;
		const dy = e.changedTouches[ 0 ].clientY - y0;
		x0 = null;
		if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
			onSwipe( dx < 0 ? 1 : -1 );
			if ( link ) {
				link.dataset.swiped = '1';
				setTimeout( () => delete link.dataset.swiped, 400 );
			}
		}
	} );
	link?.addEventListener(
		'click',
		( e ) => {
			if ( link.dataset.swiped ) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		},
		true
	);
}

function cardStep( event ) {
	event.preventDefault();
	event.stopPropagation();
	const a = event.target.closest( '.mcard__arr' );
	cardGo( a.closest( '.mcard__media' ), +a.dataset.dir );
}

store( 'memorial-plugin/card', {
	actions: {
		step: cardStep,
		key( event ) {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				cardStep( event );
			}
		},
	},
	callbacks: {
		init() {
			const { ref: media } = getElement();
			const box = media.querySelector( '.mcard__img' );
			if ( ! box || ! media.querySelector( '.mcard__arr' ) ) {
				return;
			}
			// pointer over the card — load the second photo in advance
			box.closest( '.mcard' )?.addEventListener(
				'pointerenter',
				() => {
					const p = box.querySelectorAll( '.mcard__ph' )[ 1 ];
					if ( p ) {
						p.loading = 'eager';
					}
				},
				{ once: true }
			);
			swipe( box, ( dir ) => cardGo( media, dir ), box.closest( 'a' ) );
		},
	},
} );

/* ---------- “Our works” ---------- */
// Thumbnails and arrows change the large photo; the large view opens by a click on the large photo.
function workGo( work, t ) {
	const thumbs = [ ...work.querySelectorAll( '.work__thumb' ) ];
	if ( ! t || ! thumbs.length ) {
		return;
	}
	const main = work.querySelector( '.work__img' );
	const im = main.querySelector( 'img' );
	const ti = t.querySelector( 'img' );
	im.removeAttribute( 'srcset' );
	im.removeAttribute( 'sizes' );
	// what is already loaded right away, then the large one
	if ( ti && ti.currentSrc ) {
		im.src = ti.currentSrc;
	}
	const big = new window.Image();
	big.onload = () => {
		if ( main.href === t.href ) {
			im.src = t.href;
		}
	};
	big.src = t.href;
	main.href = t.href;
	if ( ti ) {
		// the blurred background — from the new photo
		main.style.setProperty(
			'--wbg',
			'url("' + ( ti.currentSrc || ti.src ) + '")'
		);
	}
	thumbs.forEach( ( x ) => {
		x.classList.toggle( 'is-on', x === t );
		x.setAttribute( 'aria-current', x === t ? 'true' : 'false' );
	} );
}
function workStep( work, dir ) {
	const thumbs = [ ...work.querySelectorAll( '.work__thumb' ) ];
	const i = thumbs.findIndex( ( x ) => x.classList.contains( 'is-on' ) );
	workGo( work, thumbs[ ( i + dir + thumbs.length ) % thumbs.length ] );
}

store( 'memorial-plugin/works', {
	actions: {
		sort( event ) {
			const list = event.target
				.closest( '[data-works]' )
				.querySelector( '.works__list' );
			sortBy( [ ...list.children ], event.target.value ).forEach( ( w ) =>
				list.appendChild( w )
			);
		},
	},
	callbacks: {
		init() {
			const { ref: box } = getElement();
			const list = box.querySelector( '.works__list' );
			box.querySelectorAll( '[data-type]' ).forEach( ( b ) =>
				b.addEventListener( 'click', () => {
					box.querySelectorAll( '[data-type]' ).forEach( ( x ) =>
						x.classList.toggle( 'is-on', x === b )
					);
					list.querySelectorAll( '.work' ).forEach( ( w ) =>
						w.classList.toggle(
							'is-hidden',
							!! b.dataset.type &&
								! ( ' ' + w.dataset.types + ' ' ).includes(
									' ' + b.dataset.type + ' '
								)
						)
					);
				} )
			);
			box.addEventListener(
				'click',
				( e ) => {
					const t = e.target.closest( '.work__thumb' );
					const arr = e.target.closest( '.work__arr' );
					if ( t ) {
						e.preventDefault();
						e.stopPropagation();
						workGo( t.closest( '.work' ), t );
					} else if ( arr ) {
						e.preventDefault();
						e.stopPropagation();
						workStep( arr.closest( '.work' ), +arr.dataset.dir );
					}
				},
				true
			);
			box.querySelectorAll( '.work__media' ).forEach( ( m ) => {
				const main = m.querySelector( '.work__img' );
				if ( main && m.querySelector( '.work__thumb' ) ) {
					swipe(
						main,
						( dir ) => workStep( m.closest( '.work' ), dir ),
						main
					);
				}
			} );
			box.querySelectorAll( '.mcat__bar' ).forEach( sectionBar );
		},
		// N random works (cards, photos) — different on every visit, even from the page cache.
		random() {
			const { ref: box } = getElement();
			const n = +box.dataset.wcardsRand || 6;
			const cards = [ ...box.children ];
			for ( let i = cards.length - 1; i > 0; i-- ) {
				const j = Math.floor( Math.random() * ( i + 1 ) );
				[ cards[ i ], cards[ j ] ] = [ cards[ j ], cards[ i ] ];
			}
			cards.forEach( ( c, k ) => {
				if ( k < n ) {
					c.hidden = false;
					box.appendChild( c );
				} else {
					c.remove();
				}
			} );
		},
	},
} );

/* ---------- The chosen point of a photo in the middle of a tile (as far as the edges allow) ---------- */
function focusPos( el, nw, nh ) {
	// a tile has its photo in a block above the caption
	const box = el.querySelector( '.cat__ph' ) || el;
	const w = box.clientWidth;
	const h = box.clientHeight;
	if ( ! w || ! h || ! nw || ! nh ) {
		return;
	}
	const [ fx, fy ] = el
		.getAttribute( 'data-focus' )
		.split( ' ' )
		.map( ( v ) => v / 100 );
	const k = Math.max( w / nw, h / nh );
	const W = nw * k;
	const H = nh * k;
	const px =
		W - w > 1
			? Math.min(
					100,
					Math.max( 0, ( ( fx * W - w / 2 ) / ( W - w ) ) * 100 )
				)
			: 50;
	const py =
		H - h > 1
			? Math.min(
					100,
					Math.max( 0, ( ( fy * H - h / 2 ) / ( H - h ) ) * 100 )
				)
			: 50;
	const v = px.toFixed( 1 ) + '% ' + py.toFixed( 1 ) + '%';
	if ( el.tagName === 'IMG' ) {
		el.style.objectPosition = v;
	} else {
		el.style.setProperty( '--pos', v );
	}
}

store( 'memorial-plugin/focus', {
	callbacks: {
		init() {
			const { ref: el } = getElement();
			const place = ( nw, nh ) => {
				focusPos( el, nw, nh );
				let t;
				window.addEventListener( 'resize', () => {
					clearTimeout( t );
					t = setTimeout( () => focusPos( el, nw, nh ), 100 );
				} );
			};
			if ( el.tagName === 'IMG' ) {
				const go = () => place( el.naturalWidth, el.naturalHeight );
				if ( el.complete && el.naturalWidth ) {
					go();
				} else {
					el.addEventListener( 'load', go, { once: true } );
				}
				return;
			}
			const m = /url\(["']?([^"')]+)["']?\)/.exec(
				el.style.getPropertyValue( '--img' )
			);
			if ( ! m ) {
				return;
			}
			const im = new window.Image();
			im.onload = () => place( im.naturalWidth, im.naturalHeight );
			im.src = m[ 1 ];
		},
	},
} );
