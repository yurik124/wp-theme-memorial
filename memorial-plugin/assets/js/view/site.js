/**
 * Behaviour of the plugin markup on every page (works in any theme): catalog sorting, Metrika goals,
 * the “Call me back” window, MAX, phone and e-mail clicks, Yandex reviews and maps loaded on demand,
 * counters, rows of buttons, embedded frames, the photo viewer, anchors, rows dragged with the mouse.
 * Script module (like view/blocks.js); texts — `getConfig( 'memorial-plugin' ).site` (core/assets.php).
 */
import { getConfig } from '@wordpress/interactivity';

const SITE_TEXTS = ( getConfig( 'memorial-plugin' ) || {} ).site || {};

( () => {
	const T = ( k ) => SITE_TEXTS[ k ] || k;
	const d = document;
	// Сортировка каталога: применяется сразу при выборе
	d.querySelectorAll( '[data-sort] select' ).forEach( ( sel ) =>
		sel.addEventListener( 'change', () => {
			const u = new URL( sel.form.action );
			if ( sel.value ) {
				u.searchParams.set( 'sort', sel.value );
			}
			window.location.href = u;
		} )
	);

	// Яндекс.Метрика: цель «Звонок» / «Письмо» + где именно нажали
	const place = ( a ) => {
		const pkg = a.closest( '.pkg' );
		if ( pkg ) {
			return (
				'Пакет «' +
				( pkg.querySelector( 'h3' )?.textContent.trim() || '' ) +
				'»'
			);
		}
		const map = [
			[ '.mbar', 'Нижняя панель (телефон)' ],
			[ '.fab', 'Плавающая кнопка' ],
			[ '.header', 'Шапка' ],
			[ '.hero', 'Первый экран' ],
			[ '.card', 'Карточка товара в каталоге' ],
			[ '.product', 'Страница товара' ],
			[ '#contacts', 'Блок «Контакты»' ],
			[ '.footer', 'Подвал' ],
			[ '.nf', 'Страница 404' ],
			[ '.empty', 'Пустой раздел каталога' ],
		];
		for ( const [ sel, name ] of map ) {
			if ( a.closest( sel ) ) {
				return name;
			}
		}
		return 'Другое';
	};
	const goal = ( name, params ) => {
		if ( window.memYm && typeof window.ym === 'function' ) {
			window.ym( window.memYm, 'reachGoal', name, params );
		}
	};
	const where = ( a ) => ( {
		'Где нажали': place( a ),
		Страница: decodeURI( window.location.pathname ),
	} );

	// Звонить умеет только телефон. Планшет без SIM и компьютер — нет.
	const canCall = () =>
		/Android.+Mobile|iPhone|iPod|Windows Phone|Mobile.+Firefox|Opera Mini/i.test(
			navigator.userAgent
		) ||
		( window.matchMedia( '(pointer: coarse)' ).matches &&
			Math.min( window.screen.width, window.screen.height ) < 600 );

	/* ---------- Окно «Перезвоните мне» ---------- */
	const cfg = window.memCall || {};
	const dlg = d.getElementById( 'callback' );
	let openedAt = 0,
		from = '',
		product = '';
	const openCallback = ( status, src, what ) => {
		if ( ! dlg ) {
			return;
		}
		from = src || '';
		// the “Order” button of a product card: the product goes with the request
		product = what || '';
		if ( product && ! status ) {
			status = T( 'order' ).replace( '%s', product );
		}
		const st = dlg.querySelector( '[data-cb-status]' );
		st.textContent = status || '';
		st.hidden = ! status;
		dlg.querySelector( '.cb__form' ).hidden = false;
		dlg.querySelector( '.cb__done' ).hidden = true;
		dlg.querySelector( '.cb__err' ).hidden = true;
		openedAt = Date.now();
		if ( ! dlg.open ) {
			dlg.showModal();
		}
		setTimeout(
			() => dlg.querySelector( 'input[name=phone]' ).focus(),
			50
		);
		goal( 'callback_open', { 'Окно звонка': { Откуда: from } } );
	};
	if ( dlg ) {
		dlg.querySelectorAll( '[data-cb-close]' ).forEach( ( b ) =>
			b.addEventListener( 'click', () => dlg.close() )
		);
		dlg.addEventListener( 'click', ( e ) => {
			if ( e.target === dlg ) {
				dlg.close();
			}
		} ); // клик по затемнению
		const form = dlg.querySelector( '.cb__form' );
		const err = dlg.querySelector( '.cb__err' );
		const phone = form.elements.phone;
		// Маска +7 (900) 000-00-00
		phone.addEventListener( 'input', () => {
			let v = phone.value.replace( /\D/g, '' );
			if ( ! v ) {
				phone.value = '';
				return;
			}
			if ( v[ 0 ] === '8' ) {
				v = '7' + v.slice( 1 );
			}
			if ( v[ 0 ] !== '7' ) {
				v = '7' + v;
			}
			v = v.slice( 0, 11 );
			const p = [
				v.slice( 1, 4 ),
				v.slice( 4, 7 ),
				v.slice( 7, 9 ),
				v.slice( 9, 11 ),
			];
			phone.value =
				'+7' +
				( p[ 0 ] ? ' (' + p[ 0 ] : '' ) +
				( p[ 0 ].length === 3 && p[ 1 ] ? ') ' + p[ 1 ] : '' ) +
				( p[ 2 ] ? '-' + p[ 2 ] : '' ) +
				( p[ 3 ] ? '-' + p[ 3 ] : '' );
		} );
		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const fail = ( m ) => {
				err.textContent = m;
				err.hidden = false;
			};
			if ( phone.value.replace( /\D/g, '' ).length < 11 ) {
				return fail( T( 'fullNumber' ) );
			}
			if ( ! form.elements.consent.checked ) {
				return fail( T( 'consent' ) );
			}
			err.hidden = true;
			const btn = form.querySelector( '[type=submit]' );
			btn.disabled = true;
			try {
				const r = await fetch( cfg.api, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						phone: phone.value,
						name: form.elements.name.value,
						website: form.elements.website.value,
						consent: 1,
						page: window.location.href,
						product,
						elapsed: Math.round( ( Date.now() - openedAt ) / 1000 ),
					} ),
				} );
				const j = await r.json().catch( () => ( {} ) );
				if ( ! r.ok ) {
					throw new Error( j.message || T( 'notSent' ) );
				}
				form.hidden = true;
				dlg.querySelector( '.cb__done' ).hidden = false;
				dlg.querySelector( '[data-cb-status]' ).hidden = true;
				form.reset();
				goal( 'callback_sent', {
					'Заявка на звонок': {
						Откуда: from,
						Страница: decodeURI( window.location.pathname ),
					},
				} );
			} catch ( x ) {
				fail( ( x.message || T( 'notSent' ) ) + ' ' + T( 'callUs' ) );
			} finally {
				btn.disabled = false;
			}
		} );
	}
	// Любая ссылка/кнопка с data-callback открывает окно
	d.addEventListener( 'click', ( e ) => {
		const b = e.target.closest( '[data-callback]' );
		if ( ! b ) {
			return;
		}
		e.preventDefault();
		// from the product window: it closes, the request window takes its place
		b.closest( 'dialog[open]:not(#callback)' )?.close();
		if ( dlg ) {
			openCallback( '', place( b ), b.dataset.product || '' );
		} else {
			const t = d.querySelector( 'a[href^="tel:"]' );
			if ( t ) {
				window.location.href = t.href;
			}
		} // заявки выключены в плагине — кнопка звонит
	} );

	/* ---------- Попытка открыть приложение MAX ---------- */
	const tryApp = ( url ) =>
		new Promise( ( resolve ) => {
			let left = false;
			const mark = () => {
				left = true;
			};
			window.addEventListener( 'blur', mark, { once: true } ); // браузер ушёл в приложение или спросил «Открыть MAX?»
			d.addEventListener( 'visibilitychange', mark, { once: true } );
			if ( /Firefox/i.test( navigator.userAgent ) ) {
				// Firefox показывает ошибку на всю страницу — пробуем в скрытом фрейме
				const f = d.createElement( 'iframe' );
				f.hidden = true;
				f.src = url;
				d.body.append( f );
				setTimeout( () => f.remove(), 2500 );
			} else {
				window.location.href = url;
			}
			setTimeout( () => {
				window.removeEventListener( 'blur', mark );
				d.removeEventListener( 'visibilitychange', mark );
				resolve( left );
			}, 1500 );
		} );

	/* ---------- Клики по телефонам и почте ---------- */
	d.addEventListener( 'click', async ( e ) => {
		const a = e.target.closest( 'a[href^="tel:"], a[href^="mailto:"]' );
		if ( ! a ) {
			return;
		}
		if ( a.protocol === 'mailto:' ) {
			goal( 'email_click', { Письмо: where( a ) } );
			return;
		}
		if ( canCall() ) {
			goal( 'phone_click', { Звонок: where( a ) } );
			return;
		} // телефон — обычный звонок

		// Компьютер: MAX → если не вышло, окно обратного звонка
		if ( ! dlg ) {
			return;
		}
		e.preventDefault();
		const src = place( a );
		if ( cfg.maxApp ) {
			goal( 'max_click', { MAX: where( a ) } );
			const opened = await tryApp( cfg.maxApp );
			if ( ! opened ) {
				openCallback( T( 'maxFailed' ), src );
			}
		} else if ( cfg.max ) {
			goal( 'max_click', { MAX: where( a ) } );
			const w = window.open( cfg.max, '_blank' );
			if ( ! w ) {
				// браузер не дал открыть вкладку
				openCallback( T( 'maxFailed' ), src );
				return;
			}
			try {
				w.opener = null;
			} catch {}
			// Посетитель быстро вернулся на сайт — значит, в MAX написать не вышло: предлагаем звонок
			const t0 = Date.now();
			const back = () => {
				if ( d.visibilityState !== 'visible' ) {
					return;
				}
				d.removeEventListener( 'visibilitychange', back );
				window.removeEventListener( 'focus', back );
				if ( Date.now() - t0 < 60000 ) {
					openCallback( T( 'maxBack' ), src );
				}
			};
			setTimeout( () => {
				// вкладка открылась в фоне и сайт остался перед глазами
				if ( d.visibilityState === 'visible' && d.hasFocus() ) {
					back();
				} else {
					d.addEventListener( 'visibilitychange', back );
					window.addEventListener( 'focus', back );
				}
			}, 1200 );
		} else {
			openCallback( '', src );
		}
	} );

	// Отзывы с Яндекс Карт: сами — после согласия на cookie (или если согласие не спрашивается), иначе по кнопке
	d.querySelectorAll( '[data-reviews]' ).forEach( ( box ) => {
		const load = () => {
			if ( box.classList.contains( 'is-loaded' ) ) {
				return;
			}
			const f = d.createElement( 'iframe' );
			f.src = box.dataset.reviews;
			f.title = T( 'reviewsFrame' );
			f.loading = 'lazy';
			box.querySelector( '.reviews__ph' )?.replaceWith( f );
			box.classList.add( 'is-loaded' );
		};
		let ok = false;
		try {
			ok = localStorage.getItem( 'mem_consent_v1' ) === 'yes';
		} catch {}
		const auto = ok || ! ( window.memTrack && window.memTrack.need );
		if ( auto ) {
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
		box.querySelector( '[data-reviews-load]' )?.addEventListener(
			'click',
			load
		);
		d.addEventListener( 'mem:consent', load );
	} );

	// Появление блоков и цифр (цифры не «пересчитываются» — сразу верные и плавно проявляются, см. .stat в main.css)
	const io = new window.IntersectionObserver(
		( entries ) => {
			entries.forEach( ( en ) => {
				if ( ! en.isIntersecting ) {
					return;
				}
				const el = en.target;
				el.classList.add( 'is-in' );
				io.unobserve( el );
			} );
		},
		{ threshold: 0.15 }
	);
	d.querySelectorAll( '.reveal, [data-count]' ).forEach( ( el ) =>
		io.observe( el )
	);

	// Карты: вкладки адресов, загрузка по требованию
	d.querySelectorAll( '.maps' ).forEach( ( box ) => {
		const tabs = [ ...box.querySelectorAll( '[role="tab"]' ) ];
		const load = ( panel ) => {
			if ( ! panel || panel.classList.contains( 'is-loaded' ) ) {
				return;
			}
			const f = d.createElement( 'iframe' );
			f.src = panel.dataset.src;
			f.title = T( 'mapFrame' );
			f.loading = 'lazy';
			f.allowFullscreen = true;
			panel.replaceChildren( f );
			panel.classList.add( 'is-loaded' );
			box.dataset.loaded = '1';
		};
		const select = ( tab ) => {
			tabs.forEach( ( t ) => {
				const on = t === tab;
				t.setAttribute( 'aria-selected', String( on ) );
				t.tabIndex = on ? 0 : -1;
				d.getElementById( t.getAttribute( 'aria-controls' ) ).hidden =
					! on;
			} );
			// если карту уже открывали, следующая вкладка грузится сразу
			if ( box.dataset.loaded ) {
				load( d.getElementById( tab.getAttribute( 'aria-controls' ) ) );
			}
		};
		tabs.forEach( ( t, i ) => {
			t.addEventListener( 'click', () => select( t ) );
			t.addEventListener( 'keydown', ( e ) => {
				const dir = { ArrowRight: 1, ArrowLeft: -1 }[ e.key ] || 0;
				if ( ! dir ) {
					return;
				}
				const next = tabs[ ( i + dir + tabs.length ) % tabs.length ];
				next.focus();
				select( next );
			} );
		} );
		box.querySelectorAll( '.map__load' ).forEach( ( b ) =>
			b.addEventListener( 'click', () => load( b.closest( '.map' ) ), {
				once: true,
			} )
		);
	} );
	// Ряды кнопок с прокруткой вбок: стрелки и затухание по краям, когда есть куда листать
	d.querySelectorAll( '.srow' ).forEach( ( row ) => {
		const sc = row.querySelector( '.srow__in' );
		if ( ! sc ) {
			return;
		}
		[ 'l', 'r' ].forEach( ( s ) => {
			const b = d.createElement( 'button' );
			b.type = 'button';
			b.className = 'srow__arr srow__arr--' + s;
			b.tabIndex = -1;
			b.setAttribute(
				'aria-label',
				s === 'l' ? T( 'scrollLeft' ) : T( 'scrollRight' )
			);
			b.textContent = s === 'l' ? '‹' : '›';
			b.addEventListener( 'click', () =>
				sc.scrollBy( {
					left: ( s === 'l' ? -1 : 1 ) * sc.clientWidth * 0.7,
					behavior: 'smooth',
				} )
			);
			row.appendChild( b );
		} );
		const upd = () => {
			row.classList.toggle( 'has-l', sc.scrollLeft > 4 );
			row.classList.toggle(
				'has-r',
				sc.scrollLeft + sc.clientWidth < sc.scrollWidth - 4
			);
		};
		sc.addEventListener( 'scroll', upd, { passive: true } );
		window.addEventListener( 'resize', upd );
		upd();
		// выбранная кнопка — в зону видимости
		sc.addEventListener( 'click', ( e ) => {
			const c = e.target.closest( 'button, a' );
			if ( c ) {
				c.scrollIntoView( {
					behavior: 'smooth',
					block: 'nearest',
					inline: 'center',
				} );
			}
		} );
	} );

	// Встроенные окна (панорама и т.п.): грузятся по кнопке, чтобы не тормозить страницу
	d.querySelectorAll( '[data-embed]' ).forEach( ( box ) => {
		box.querySelector( '[data-embed-load]' )?.addEventListener(
			'click',
			() => {
				const f = d.createElement( 'iframe' );
				f.src = box.dataset.embed;
				f.title = box.dataset.title || '';
				f.allowFullscreen = true;
				f.allow = 'fullscreen';
				box.replaceChildren( f );
				box.classList.add( 'is-loaded' );
			},
			{ once: true }
		);
	} );

	// Крупное фото с листанием: «Наши работы», фото в тексте страниц со ссылкой на файл, фото одного товара.
	// Пока крупное фото грузится, видно уже загруженную уменьшенную копию; соседние фото подгружаются заранее.
	const lb = d.getElementById( 'lb' );
	const pv = d.getElementById( 'pv' );
	// страница под открытым просмотром не прокручивается
	const lockPage = () => {
		d.documentElement.classList.toggle(
			'lb-open',
			!! ( lb?.open || pv?.open )
		);
	};
	// загрузка крупных фото: [href] → готово
	const loaded = new Set(),
		loading = new Map();
	const preload = ( href, done ) => {
		if ( ! href || loaded.has( href ) ) {
			return;
		}
		if ( loading.has( href ) ) {
			loading.get( href ).push( done );
			return;
		}
		loading.set( href, [ done ] );
		const im = new window.Image();
		im.onload = () => {
			loaded.add( href );
			( loading.get( href ) || [] ).forEach( ( f ) => f && f() );
			loading.delete( href );
		};
		im.onerror = () => loading.delete( href );
		im.decoding = 'async';
		im.src = href;
	};
	// фото в окне: сначала уменьшенная копия (если есть), крупное — когда загрузится
	const putPhoto = ( im, it, still ) => {
		im.alt = it.alt || '';
		if ( loaded.has( it.href ) ) {
			im.src = it.href;
			im.classList.remove( 'is-low' );
			return;
		}
		if ( it.low ) {
			im.src = it.low;
			im.classList.add( 'is-low' );
		} else {
			im.removeAttribute( 'src' );
		}
		preload( it.href, () => {
			if ( still() ) {
				im.src = it.href;
				im.classList.remove( 'is-low' );
			}
		} );
	};
	const idle =
		window.requestIdleCallback || ( ( f ) => setTimeout( f, 120 ) );
	// горизонтальный свайп (вертикальный — прокрутка, ничего не листает)
	const swipe = ( el, onSwipe ) => {
		let x0 = null,
			y0 = 0,
			t0 = null;
		el.addEventListener(
			'touchstart',
			( e ) => {
				x0 = e.touches[ 0 ].clientX;
				y0 = e.touches[ 0 ].clientY;
				t0 = e.target;
			},
			{ passive: true }
		);
		el.addEventListener( 'touchend', ( e ) => {
			if ( x0 === null ) {
				return;
			}
			const dx = e.changedTouches[ 0 ].clientX - x0,
				dy = e.changedTouches[ 0 ].clientY - y0;
			if (
				Math.abs( dx ) > 50 &&
				Math.abs( dx ) > Math.abs( dy ) * 1.5
			) {
				onSwipe( dx < 0 ? 1 : -1, t0 );
			}
			x0 = null;
		} );
	};

	// высота экрана без адресной строки браузера (100lvh): по ней подгоняются фото на телефоне;
	// не меняется, когда строка прячется или появляется при прокрутке
	let lvh = 0,
		lvhW = 0;
	const screenH = () => {
		if ( ! lvh || lvhW !== window.innerWidth ) {
			const m = d.createElement( 'div' );
			m.style.cssText =
				'position:fixed;top:0;left:0;width:1px;height:100lvh;visibility:hidden;pointer-events:none';
			d.body.appendChild( m );
			lvh = Math.max(
				m.offsetHeight,
				window.innerHeight,
				d.documentElement.clientHeight
			);
			m.remove();
			lvhW = window.innerWidth;
		}
		return lvh;
	};

	// закрытие с анимацией (крестик, Esc, щелчок мимо); без анимации, если в системе «уменьшить движение»
	const still = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const shut = ( modal ) => {
		if ( ! modal.open || modal.classList.contains( 'is-closing' ) ) {
			return;
		}
		if ( still.matches ) {
			modal.close();
			return;
		}
		modal.classList.add( 'is-closing' );
		setTimeout( () => {
			modal.classList.remove( 'is-closing' );
			modal.close();
		}, 180 );
	};
	const animateEsc = ( modal ) =>
		modal.addEventListener( 'cancel', ( e ) => {
			e.preventDefault();
			shut( modal );
		} );

	let lbOpen = null; // открыть фото: (список [{href, low, cap, alt}], с какого, { count })
	if ( lb && lb.showModal ) {
		const isImg = ( a ) =>
			/\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i.test(
				a.getAttribute( 'href' ) || ''
			);
		d.querySelectorAll(
			'.content a:not(.work__thumb):not([data-pv])'
		).forEach( ( a ) => {
			if ( isImg( a ) && a.querySelector( 'img' ) ) {
				a.setAttribute( 'data-lb', '' );
			}
		} );
		let group = [],
			idx = 0,
			withCount = true;
		const img = lb.querySelector( 'img' ),
			cap = lb.querySelector( 'figcaption' ),
			count = lb.querySelector( '.lb__count' );
		const show = ( i ) => {
			idx = ( i + group.length ) % group.length;
			const it = group[ idx ];
			putPhoto( img, it, () => group[ idx ] === it && lb.open );
			cap.textContent = it.cap || '';
			const many = group.length > 1;
			lb.querySelectorAll( '.lb__nav' ).forEach(
				( b ) => ( b.hidden = ! many )
			);
			if ( count ) {
				count.hidden = ! many || ! withCount;
				count.textContent = idx + 1 + ' / ' + group.length;
			}
			idle( () =>
				[ 1, -1, 2 ].forEach( ( k ) =>
					preload(
						group[ ( idx + k + group.length ) % group.length ]?.href
					)
				)
			);
		};
		lbOpen = ( items, start, opts = {} ) => {
			group = items;
			withCount = opts.count !== false;
			show( start );
			lb.showModal();
			lockPage();
		};
		const itemOf = ( x ) => ( {
			href: x.href,
			low: x.querySelector( 'img' )?.currentSrc || '',
			cap:
				x.dataset.cap ||
				x
					.closest( 'figure' )
					?.querySelector( 'figcaption' )
					?.textContent.trim() ||
				'',
			alt: x.querySelector( 'img' )?.alt || '',
		} );
		d.addEventListener( 'click', ( e ) => {
			const a = e.target.closest( 'a[data-lb]' );
			if ( ! a || e.ctrlKey || e.metaKey || e.shiftKey ) {
				return;
			}
			e.preventDefault();
			const items = a
				.closest( '.work' )
				?.querySelectorAll( '[data-lb-item]' );
			const list =
				items && items.length
					? [ ...items ]
					: [
							...(
								a.closest(
									'.wp-block-gallery, .works__list, .mem-photos'
								) || a.parentElement
							).querySelectorAll( 'a[data-lb]' ),
						].filter( ( x ) => x.offsetParent !== null || x === a );
			const all = list.map( itemOf );
			lbOpen(
				all,
				Math.max(
					0,
					all.findIndex( ( x ) => x.href === a.href )
				)
			);
		} );
		lb.querySelector( '.lb__x' ).addEventListener( 'click', () =>
			shut( lb )
		);
		animateEsc( lb );
		lb.querySelector( '.lb__prev' ).addEventListener( 'click', () =>
			show( idx - 1 )
		);
		lb.querySelector( '.lb__next' ).addEventListener( 'click', () =>
			show( idx + 1 )
		);
		lb.addEventListener( 'click', ( e ) => {
			if (
				e.target === lb ||
				e.target.classList.contains( 'lb__stage' )
			) {
				shut( lb );
			}
		} );
		lb.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowLeft' ) {
				show( idx - 1 );
			}
			if ( e.key === 'ArrowRight' ) {
				show( idx + 1 );
			}
		} );
		lb.addEventListener(
			'touchmove',
			( e ) => {
				if ( e.cancelable ) {
					e.preventDefault();
				}
			},
			{ passive: false }
		); // без прокрутки страницы
		swipe( lb, ( dir ) => group.length > 1 && show( idx + dir ) );
		lb.addEventListener( 'close', () => {
			img.removeAttribute( 'src' );
			lockPage();
		} );
	}

	// Крупный просмотр каталога: листаются товары (стрелки по бокам, ← →, свайп вбок),
	// фото одного товара — стрелками и точками на фото или свайпом по фото.
	// «Только фото» (pv--photo): фото, рядом название, цена, характеристики.
	// «Окно товара» (pv--card): белое окно — фото | название, описание, характеристики | цена и «Заказать»;
	// щелчок по фото открывает крупно только фото этого товара.
	if ( pv && pv.showModal ) {
		const box = pv.querySelector( '.pv__box' ),
			media = pv.querySelector( '.pv__media' ),
			pimg = pv.querySelector( '.pv__img' ),
			dots = pv.querySelector( '.pv__dots' ),
			main = pv.querySelector( '.pv__main' ),
			buy = pv.querySelector( '.pv__buy' );
		let prods = [],
			pi = 0,
			ph = 0;
		// товар из карточки: фото (крупные адреса — data-big); описание и характеристики — скрытый блок карточки
		const prodOf = ( a ) => {
			const li = a.closest( '.mcard' );
			const imgs = [ ...li.querySelectorAll( '.mcard__ph' ) ];
			const photos = imgs.length
				? imgs.map( ( x ) => ( {
						href: new URL(
							x.dataset.big || x.currentSrc || x.src,
							window.location.href
						).href,
						low: x.complete && x.naturalWidth ? x.currentSrc : '',
						alt: x.alt || '',
					} ) )
				: [ { href: a.href, low: '', alt: '' } ];
			return {
				a,
				li,
				mode: a.dataset.pv === 'card' ? 'card' : 'photo',
				photos,
				info: li.querySelector( '.mcard__info' ),
			};
		};
		// Описание и характеристики окно берёт у карточки на время показа и возвращает: в HTML
		// страницы они всегда в одном месте (для поисковиков), скрытые (hidden) — экранный диктор
		// читает их только в открытом окне.
		let lent = null;
		const giveBack = () => {
			if ( lent ) {
				lent.info.append( ...lent.nodes );
				lent = null;
			}
		};
		// название, подпись, цена и «Заказать» — из видимой карточки
		const fromCard = ( p ) => {
			const el = ( tag, cls, text ) => {
				const x = d.createElement( tag );
				x.className = cls;
				x.textContent = text;
				return x;
			};
			const name = p.li.querySelector( '.mcard__name' );
			const sub = p.li.querySelector( '.mcard__sub' );
			const price = p.li.querySelector( '.mcard__price' );
			const order = p.li.querySelector( '.mcard__order' );
			const out = {
				title: el( 'h2', 'pv__title', name?.textContent || '' ),
				sub:
					sub && p.info && ! p.info.hasAttribute( 'data-nosub' )
						? el( 'p', 'pv__sub', sub.textContent )
						: null,
				price: null,
				order: null,
			};
			if ( price ) {
				out.price = el( 'p', 'pv__price', '' );
				out.price.append(
					...[ ...price.childNodes ].map( ( x ) =>
						x.cloneNode( true )
					)
				);
			}
			if ( order ) {
				out.order = order.cloneNode( true );
				out.order.classList.replace( 'btn--line', 'btn--gold' );
				out.order.classList.replace( 'mcard__order', 'pv__order' );
			}
			return out;
		};
		const showPhoto = ( k ) => {
			const p = prods[ pi ];
			ph = ( k + p.photos.length ) % p.photos.length;
			const it = p.photos[ ph ];
			putPhoto(
				pimg,
				it,
				() => prods[ pi ] === p && p.photos[ ph ] === it && pv.open
			);
			[ ...dots.children ].forEach( ( x, n ) =>
				x.classList.toggle( 'is-on', n === ph )
			);
			idle( () =>
				preload( p.photos[ ( ph + 1 ) % p.photos.length ].href )
			);
		};
		// телефон: фото как можно крупнее, но под ним на экране видны цена и «Заказать»
		// («Только фото» — название и цена). Высота — постоянная высота окна, а не текущая
		// видимая часть: иначе фото растёт, когда браузер прячет адресную строку.
		const phone = window.matchMedia( '(max-width: 700px)' );
		const fit = () => {
			if ( ! phone.matches ) {
				box.style.removeProperty( '--pv-ph' );
				return;
			}
			const under =
				prods[ pi ]?.mode === 'card'
					? buy.offsetHeight
					: [ ...main.children ]
							.filter( ( x ) =>
								x.matches( '.pv__title, .pv__sub, .pv__price' )
							)
							.reduce( ( h, x ) => h + x.offsetHeight + 8, 14 );
			const top = media.offsetTop,
				dotsH = dots.children.length ? 26 : 0;
			const h = screenH() - top - dotsH - under - 16;
			box.style.setProperty(
				'--pv-ph',
				Math.max( 220, Math.round( h ) ) + 'px'
			);
		};
		// стрелки товаров на телефоне — на уровне строки под фото
		const placeNav = () =>
			box.style.setProperty(
				'--pv-under',
				media.offsetTop + media.offsetHeight + 'px'
			);
		const showProd = ( i ) => {
			pi = ( i + prods.length ) % prods.length;
			const p = prods[ pi ];
			// товар под «Показать ещё»: эта часть списка раскрывается, чтобы он был виден после закрытия
			const hid = p.li.closest( '[data-more-hidden]' );
			hid?.parentElement?.memReveal?.( hid );
			pv.classList.toggle( 'pv--card', p.mode === 'card' );
			pv.classList.toggle( 'pv--photo', p.mode !== 'card' );
			pv.setAttribute(
				'aria-label',
				p.li.querySelector( '.mcard__name' )?.textContent || ''
			);
			giveBack();
			const nodes = p.info ? [ ...p.info.children ] : [];
			lent = p.info ? { info: p.info, nodes } : null;
			const one = ( q ) => nodes.find( ( x ) => x.matches( q ) );
			const c = fromCard( p );
			const desc = one( '.pv__desc' ),
				specs = one( '.pv__specs' );
			const keep = ( list ) => list.filter( Boolean );
			if ( p.mode === 'card' ) {
				main.replaceChildren(
					...keep( [ c.title, c.sub, desc, specs ] )
				);
				buy.replaceChildren( ...keep( [ c.price, c.order ] ) );
			} else {
				main.replaceChildren(
					...keep( [ c.title, c.sub, c.price, specs ] )
				);
				buy.replaceChildren();
			}
			buy.hidden = ! buy.children.length;
			const many = p.photos.length > 1;
			media.classList.toggle( 'is-many', many );
			dots.replaceChildren(
				...( many ? p.photos.map( () => d.createElement( 'i' ) ) : [] )
			);
			pv.querySelectorAll( '.pv__nav' ).forEach(
				( b ) => ( b.hidden = prods.length < 2 )
			);
			fit();
			showPhoto( 0 );
			pv.scrollTop = 0;
			main.scrollTop = 0;
			placeNav();
			idle( () =>
				[ 1, -1 ].forEach( ( k ) =>
					preload(
						prods[ ( pi + k + prods.length ) % prods.length ]
							.photos[ 0 ].href
					)
				)
			);
		};
		pimg.addEventListener( 'load', placeNav );
		// окно закрыто (крестик, Esc, фон) — описание и характеристики возвращаются в карточку
		pv.addEventListener( 'close', () => {
			giveBack();
			main.replaceChildren();
			buy.replaceChildren();
		} );
		let pvW = window.innerWidth;
		window.addEventListener( 'resize', () => {
			// прокрутка на телефоне меняет только высоту (адресная строка) — размер фото не трогаем
			if ( pv.open && window.innerWidth !== pvW ) {
				pvW = window.innerWidth;
				fit();
				placeNav();
			}
		} );
		d.addEventListener( 'click', ( e ) => {
			const a = e.target.closest( 'a[data-pv]' );
			if ( ! a || e.ctrlKey || e.metaKey || e.shiftKey ) {
				return;
			}
			e.preventDefault();
			const scope = a.closest( '.mcat__grid' ) || a.parentElement;
			// видимые товары и те, что ждут под «Показать ещё» (не отфильтрованные)
			prods = [ ...scope.querySelectorAll( 'a[data-pv]' ) ]
				.filter(
					( x ) =>
						x === a ||
						x.offsetParent !== null ||
						( x.closest( '[data-more-hidden]' ) &&
							scope.offsetParent !== null )
				)
				.map( prodOf );
			pv.showModal();
			lockPage();
			showProd(
				Math.max(
					0,
					prods.findIndex( ( p ) => p.a === a )
				)
			);
		} );
		pv.querySelector( '.pv__x' ).addEventListener( 'click', () =>
			shut( pv )
		);
		animateEsc( pv );
		pv.querySelector( '.pv__prev' ).addEventListener( 'click', () =>
			showProd( pi - 1 )
		);
		pv.querySelector( '.pv__next' ).addEventListener( 'click', () =>
			showProd( pi + 1 )
		);
		pv.querySelector( '.pv__arr--l' ).addEventListener( 'click', () =>
			showPhoto( ph - 1 )
		);
		pv.querySelector( '.pv__arr--r' ).addEventListener( 'click', () =>
			showPhoto( ph + 1 )
		);
		// «Окно товара»: щелчок по фото — крупно только фото этого товара, без счётчика
		pimg.addEventListener( 'click', () => {
			const p = prods[ pi ];
			if ( p.mode === 'card' && lbOpen ) {
				lbOpen(
					p.photos.map( ( x ) => ( {
						...x,
						low: x.low || ( loaded.has( x.href ) ? x.href : '' ),
						cap: '',
					} ) ),
					ph,
					{ count: false }
				);
			}
		} );
		pv.addEventListener( 'click', ( e ) => {
			if ( e.target === pv ) {
				shut( pv );
			}
		} );
		pv.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowLeft' ) {
				showProd( pi - 1 );
			}
			if ( e.key === 'ArrowRight' ) {
				showProd( pi + 1 );
			}
		} );
		swipe( pv, ( dir, t ) => {
			const p = prods[ pi ];
			if ( media.contains( t ) ) {
				if ( p.photos.length > 1 ) {
					showPhoto( ph + dir );
				}
			} else if ( prods.length > 1 ) {
				showProd( pi + dir );
			}
		} );
		pv.addEventListener( 'close', () => {
			pimg.removeAttribute( 'src' );
			main.replaceChildren();
			buy.replaceChildren();
			lockPage();
		} );
	}

	// Каталог в одну колонку на телефоне: фото карточки по высоте экрана, чтобы под ним были видны
	// название, цена и «Заказать» (с учётом шапки, строки разделов и нижней панели «Позвонить»).
	const grids = d.querySelectorAll( '.mcat__grid' );
	if ( grids.length ) {
		const fitCards = () =>
			grids.forEach( ( g ) => {
				g.style.removeProperty( '--mcard-ph' );
				const one =
					window.innerWidth <= 600 &&
					window
						.getComputedStyle( g )
						.gridTemplateColumns.split( ' ' ).length === 1;
				const li = [ ...g.children ].find(
					( x ) => x.offsetParent !== null
				);
				const imgBox = li?.querySelector( '.mcard__img' );
				if ( ! one || ! imgBox ) {
					return;
				}
				// у закреплённых (position: fixed) элементов нет offsetParent — видимость по getClientRects
				const hOf = ( q ) => {
					const el = d.querySelector( q );
					return el && el.getClientRects().length
						? el.offsetHeight
						: 0;
				};
				const bottom =
					window.getComputedStyle(
						d.querySelector( '.mbar' ) || d.body
					).position === 'fixed'
						? hOf( '.mbar' )
						: 0;
				// верхняя граница — низ прилипшей строки разделов (её высота — в прилипшем виде)
				const bar = g.closest( '.mcat' )?.querySelector( '.mcat__bar' );
				let top = hOf( '.header' );
				if ( bar ) {
					const was = bar.classList.contains( 'is-stuck' );
					bar.classList.add( 'is-stuck' );
					top =
						parseFloat( window.getComputedStyle( bar ).top ) +
						bar.offsetHeight;
					bar.classList.toggle( 'is-stuck', was );
				}
				const avail = screenH() - top - bottom - 24;
				const setH = () => {
					const rest = li.offsetHeight - imgBox.offsetHeight;
					g.style.setProperty(
						'--mcard-ph',
						Math.max( 200, Math.round( avail - rest ) ) + 'px'
					);
					return rest;
				};
				// карточка сужается под ширину фото — название может перенестись на другую строку,
				// поэтому меряем ещё раз уже в узкой карточке
				const rest = setH();
				if ( li.offsetHeight - imgBox.offsetHeight !== rest ) {
					setH();
				}
			} );
		fitCards();
		window.addEventListener( 'load', fitCards );
		let cw = window.innerWidth;
		window.addEventListener( 'resize', () => {
			if ( window.innerWidth !== cw ) {
				cw = window.innerWidth;
				fitCards();
			}
		} );
	}

	// Переход по ссылке с якорем (#раздел): пока догружаются фото и каталог, страница сдвигается —
	// доводим прокрутку до нужного места ещё пару раз, если человек сам не начал листать
	const navType = (
		( performance.getEntriesByType &&
			performance.getEntriesByType( 'navigation' )[ 0 ] ) ||
		{}
	).type;
	// при обновлении страницы и «назад» браузер сам возвращает на прежнее место — не перебиваем его
	if (
		window.location.hash.length > 1 &&
		navType !== 'reload' &&
		navType !== 'back_forward'
	) {
		let user = false;
		[ 'wheel', 'touchstart', 'keydown' ].forEach( ( ev ) =>
			window.addEventListener(
				ev,
				() => {
					user = true;
				},
				{ once: true, passive: true }
			)
		);
		const go = () => {
			if ( user ) {
				return;
			}
			let id;
			try {
				id = decodeURIComponent( window.location.hash.slice( 1 ) );
			} catch {
				return;
			}
			// нет такого раздела (заголовок переименовали или заменили каталогом) — ведём к каталогу на странице
			const t =
				d.getElementById( id ) || d.querySelector( '.mcat, .works' );
			if ( t ) {
				t.scrollIntoView( { block: 'start' } );
			}
		};
		window.addEventListener( 'load', () => {
			go();
			setTimeout( go, 500 );
			setTimeout( go, 1500 );
		} );
	}
} )();

/* Ряды кнопок (разделы каталога, филиалы, темы вопросов) можно листать, потянув мышью.
   Если потянули — щелчок по кнопке после этого не срабатывает */
( function () {
	document
		.querySelectorAll( '.mcat__nav, .srow__in' )
		.forEach( function ( el ) {
			let x0 = 0,
				s0 = 0,
				down = false,
				moved = false;
			el.addEventListener( 'dragstart', function ( e ) {
				e.preventDefault();
			} );
			el.addEventListener( 'pointerdown', function ( e ) {
				if (
					e.pointerType !== 'mouse' ||
					e.button !== 0 ||
					el.scrollWidth <= el.clientWidth
				) {
					return;
				}
				down = true;
				moved = false;
				x0 = e.clientX;
				s0 = el.scrollLeft;
			} );
			window.addEventListener( 'pointermove', function ( e ) {
				if ( ! down ) {
					return;
				}
				const dx = e.clientX - x0;
				if ( ! moved && Math.abs( dx ) > 5 ) {
					moved = true;
					el.classList.add( 'is-drag' );
					el._touched = Date.now();
				}
				if ( moved ) {
					el.scrollLeft = s0 - dx;
					el._touched = Date.now();
				}
			} );
			window.addEventListener( 'pointerup', function () {
				if ( ! down ) {
					return;
				}
				down = false;
				if ( moved ) {
					setTimeout( function () {
						el.classList.remove( 'is-drag' );
					}, 0 );
				}
			} );
			el.addEventListener(
				'click',
				function ( e ) {
					if ( el.classList.contains( 'is-drag' ) ) {
						e.preventDefault();
						e.stopPropagation();
					}
				},
				true
			);
		} );
} )();
