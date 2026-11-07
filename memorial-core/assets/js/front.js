/* Memorial Core — поведение блоков на сайте: отзывы ([mem_reviews] вкладки и виджеты, [mem_rewio], книга отзывов),
   вопрос–ответ [mem_faq], каталог [mem_catalog] и «Наши работы» [mem_works] (сортировка, разделы, фото),
   точка фокуса фото в плитках и карточках разделов.
   Общее поведение сайта (меню, окно «Перезвоните мне», карты, крупное фото, ряды кнопок) — в теме, assets/js/main.js. */
(() => {
  const d = document;

  // Отзывы: вкладки филиалов и код сторонних виджетов (после согласия на cookie или по кнопке)
  d.querySelectorAll('.mrev__feeds').forEach(box => {
    const tabs = [...box.querySelectorAll('[role="tab"]')];
    const sel = t => tabs.forEach(x => {
      const on = x === t;
      x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1;
      d.getElementById(x.getAttribute('aria-controls')).hidden = !on;
    });
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => sel(t));
      t.addEventListener('keydown', e => {
        const dir = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (dir) { const n = tabs[(i + dir + tabs.length) % tabs.length]; n.focus(); sel(n); }
      });
    });
  });
  d.querySelectorAll('[data-embed-code]').forEach(box => {
    const load = () => {
      if (box.classList.contains('is-loaded')) return;
      const frag = box.querySelector('template').content.cloneNode(true);
      frag.querySelectorAll('script').forEach(old => { // скрипты из шаблона сами не запускаются — пересоздаём
        const n = d.createElement('script');
        [...old.attributes].forEach(a => n.setAttribute(a.name, a.value));
        n.text = old.textContent; old.replaceWith(n);
      });
      box.replaceChildren(frag);
      box.classList.add('is-loaded');
    };
    let ok = false;
    try { ok = localStorage.getItem('mem_consent_v1') === 'yes'; } catch (x) {}
    if (ok || !(window.memTrack && window.memTrack.need)) {
      const io = new IntersectionObserver(es => { if (es.some(e => e.isIntersecting)) { io.disconnect(); load(); } }, { rootMargin: '400px' });
      io.observe(box);
    }
    box.querySelector('[data-embed-code-load]')?.addEventListener('click', load);
    d.addEventListener('mem:consent', load);
  });


  // Вопрос–ответ: темы-фильтры и поиск
  d.querySelectorAll('[data-faq]').forEach(box => {
    const q = box.querySelector('[data-faq-q]');
    const chips = [...box.querySelectorAll('[data-faq-group]')];
    const groups = [...box.querySelectorAll('.mfaq__group')];
    const items = [...box.querySelectorAll('details')].map(el => ({ el, t: el.textContent.toLowerCase().replace(/ё/g, 'е') }));
    const none = box.querySelector('.mfaq__none');
    const setChip = id => chips.forEach(c => { const on = c.dataset.faqGroup === id; c.classList.toggle('is-on', on); c.setAttribute('aria-pressed', String(on)); });
    chips.forEach(c => c.addEventListener('click', () => {
      const id = c.dataset.faqGroup;
      if (q) q.value = '';
      items.forEach(({ el }) => { el.hidden = false; el.open = false; });
      groups.forEach(g => g.hidden = !!id && g.id !== id);
      if (none) none.hidden = true;
      setChip(id);
      const top = box.getBoundingClientRect().top;
      if (top < 0) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    if (!q) return;
    // Поиск без учёта окончаний: «похороны» найдёт «похорон», «документов» — «документы».
    // Показываются вопросы, где совпало больше всего слов (не обязательно все).
    const stop = new Set('как что где кто когда какие какой какая можно ли не на в во и или по за для от до из у о об при это мне нам вы вас ваш если а но то же бы'.split(' '));
    const stem = w => w.length > 6 ? w.slice(0, w.length - 3) : w.length > 4 ? w.slice(0, w.length - 2) : w.length > 3 ? w.slice(0, -1) : w;
    items.forEach(it => { it.s = it.el.querySelector('summary')?.textContent.toLowerCase().replace(/ё/g, 'е') || ''; });
    let timer;
    const run = () => {
      const words = q.value.toLowerCase().replace(/ё/g, 'е').split(/[^a-zа-я0-9]+/).filter(w => w.length > 1 && !stop.has(w)).map(stem);
      setChip('');
      let best = 0;
      items.forEach(it => {
        it.n = words.filter(w => it.t.includes(w)).length;
        it.k = it.n * 2 + words.filter(w => it.s.includes(w)).length;   // совпадение в самом вопросе — выше
        if (it.n > best) best = it.n;
      });
      let shown = 0;
      items.forEach(it => {
        const ok = !words.length || (best > 0 && it.n === best);
        it.el.hidden = !ok; if (ok) shown++;
        it.el.open = false;
      });
      if (words.length && shown && shown <= 3) items.forEach(it => { if (!it.el.hidden) it.el.open = true; });
      groups.forEach(g => g.hidden = !g.querySelector('details:not([hidden])'));
      if (none) none.hidden = !words.length || shown > 0;
    };
    q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 120); });
    q.addEventListener('search', run);
    q.closest('form')?.addEventListener('submit', e => { e.preventDefault(); run(); });
  });

  // Кладбища [mem_cemeteries]: фильтр и карта при раскрытии «Подробнее»
  d.querySelectorAll('[data-cem]').forEach(box => {
    const chips = [...box.querySelectorAll('[data-cem-f]')];
    const items = [...box.querySelectorAll('[data-cem-s]')];
    chips.forEach(c => c.addEventListener('click', () => {
      const f = c.dataset.cemF;
      chips.forEach(x => { const on = x === c; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', String(on)); });
      items.forEach(it => { it.hidden = !!f && it.dataset.cemS !== f; });
    }));
    box.querySelectorAll('details').forEach(dt => dt.addEventListener('toggle', () => {
      const m = dt.querySelector('[data-map-src]');
      if (!dt.open || !m || m.firstChild) return;
      const fr = d.createElement('iframe');
      fr.src = m.dataset.mapSrc; fr.title = m.dataset.mapTitle || 'Карта'; fr.loading = 'lazy'; fr.allowFullscreen = true;
      m.append(fr);
    }));
  });

  // Отзывы Rewio: вкладки филиалов, «Читать полностью», «Показать ещё»
  d.querySelectorAll('[data-rwo]').forEach(box => {
    const tabs = [...box.querySelectorAll('[role="tab"]')];
    const sel = t => tabs.forEach(x => {
      const on = x === t;
      x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; x.classList.toggle('is-on', on);
      d.getElementById(x.getAttribute('aria-controls')).hidden = !on;
    });
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => sel(t));
      t.addEventListener('keydown', e => {
        const dir = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (dir) { const n = tabs[(i + dir + tabs.length) % tabs.length]; n.focus(); sel(n); }
      });
    });
    if (tabs[0]) tabs[0].classList.add('is-on');
    const step = +box.dataset.step || 6;
    // каждая вкладка: порядок, площадка, сколько показано
    box.querySelectorAll('.rwo__panel').forEach(panel => {
      const list = panel.querySelector('.rwo__list');
      const cards = [...list.querySelectorAll('.rwo__card')];
      const st = { sort: '', dir: 'desc', src: '', shown: step };
      const wrap = panel.querySelector('.rwo__morewrap');
      const apply = () => {
        let view = cards.filter(c => !st.src || c.dataset.s === st.src);
        const k = st.dir === 'asc' ? -1 : 1;
        if (st.sort === 'd') view.sort((a, b) => k * b.dataset.d.localeCompare(a.dataset.d));
        if (st.sort === 'r') view.sort((a, b) => k * (+b.dataset.r - +a.dataset.r) || b.dataset.d.localeCompare(a.dataset.d));
        cards.forEach(c => { c.hidden = true; });
        view.forEach((c, i) => { list.appendChild(c); c.hidden = i >= st.shown; });
        if (wrap) wrap.hidden = view.length <= st.shown;
      };
      panel._rwo = { st, apply };
      panel.addEventListener('click', e => {
        // сортировка: щелчок по активной — меняет направление стрелки, по другой — выбирает её (сначала по убыванию)
        const s = e.target.closest('[data-rwo-sort]');
        if (s) {
          const on = s.getAttribute('aria-pressed') === 'true';
          st.dir = on ? (st.dir === 'desc' ? 'asc' : 'desc') : 'desc';
          st.sort = s.dataset.rwoSort; st.shown = step;
          panel.querySelectorAll('[data-rwo-sort]').forEach(b => {
            const me = b === s;
            b.setAttribute('aria-pressed', String(me));
            b.querySelector('i').textContent = me && st.dir === 'asc' ? '↑' : '↓';
            b.title = (b.dataset.rwoSort === 'd' ? (me && st.dir === 'desc' ? 'Сначала новые — нажмите, чтобы сначала старые' : 'Сортировать по дате') : (me && st.dir === 'desc' ? 'Сначала высокие оценки — нажмите, чтобы наоборот' : 'Сортировать по оценке'));
          });
          apply(); return;
        }
        // фильтр по площадке — кнопки площадок в сводке; повторное нажатие возвращает все
        const p = e.target.closest('[data-rwo-src]');
        if (p) {
          const on = p.getAttribute('aria-pressed') === 'true';
          st.src = on ? '' : p.dataset.rwoSrc; st.shown = step;
          panel.querySelectorAll('[data-rwo-src]').forEach(b => b.setAttribute('aria-pressed', String(!on && b === p)));
          const note = panel.querySelector('.rwo__shown');
          if (note) note.innerHTML = st.src ? 'Отзывы: ' + p.querySelector('span:not(.rw-ico)').textContent + ' · <button type="button" class="rwo__all">показать все</button>' : '';
          apply(); return;
        }
        if (e.target.closest('.rwo__all')) { const b = panel.querySelector('[data-rwo-src][aria-pressed="true"]'); if (b) b.click(); return; }
        if (e.target.closest('.rwo__showmore')) {
          const before = st.shown; st.shown += step; apply();
          cards.filter(c => !c.hidden).slice(before).forEach(c => c.classList.add('is-in'));
        }
      });
    });
    box.addEventListener('click', e => {
      const m = e.target.closest('.rwo__more');
      if (!m) return;
      const t = m.previousElementSibling, open = t.classList.toggle('is-open');
      m.setAttribute('aria-expanded', String(open)); m.textContent = open ? 'Свернуть' : 'Читать полностью';
    });
  });

  // Отзывы из книги: «Читать полностью»
  d.addEventListener('click', e => {
    const b = e.target.closest('.brev__more');
    if (!b) return;
    const t = b.previousElementSibling, open = t.classList.toggle('is-open');
    b.setAttribute('aria-expanded', String(open));
    b.textContent = open ? 'Свернуть' : 'Читать полностью';
  });


  // Каталог и «Наши работы»: сортировка без перезагрузки
  const sortBy = (items, dir) => items.sort((a, b) => {
    if (!dir) return a.dataset.i - b.dataset.i;
    if (dir === 'new') return (+b.dataset.year || 0) - (+a.dataset.year || 0) || a.dataset.i - b.dataset.i;
    const pa = +a.dataset.price, pb = +b.dataset.price;
    if (!pa !== !pb) return pa ? -1 : 1; // «по запросу» — в конце
    return dir === 'asc' ? pa - pb : pb - pa;
  });
  d.querySelectorAll('[data-mcat-sort]').forEach(sel => sel.addEventListener('change', () => {
    sel.closest('[data-mcat]').querySelectorAll('.mcat__grid').forEach(g => sortBy([...g.children], sel.value).forEach(li => g.appendChild(li)));
  }));
  d.querySelectorAll('[data-works]').forEach(box => {
    const list = box.querySelector('.works__list');
    box.querySelector('[data-works-sort]')?.addEventListener('change', e => sortBy([...list.children], e.target.value).forEach(w => list.appendChild(w)));
    box.querySelectorAll('[data-type]').forEach(b => b.addEventListener('click', () => {
      box.querySelectorAll('[data-type]').forEach(x => x.classList.toggle('is-on', x === b));
      list.querySelectorAll('.work').forEach(w => w.classList.toggle('is-hidden', !!b.dataset.type && !(' ' + w.dataset.types + ' ').includes(' ' + b.dataset.type + ' ')));
    }));
  });

  // «Наши работы»: миниатюры и стрелки меняют большое фото; крупный просмотр — по клику на большое фото
  const workGo = (work, t) => {
    const thumbs = [...work.querySelectorAll('.work__thumb')];
    if (!t || !thumbs.length) return;
    const main = work.querySelector('.work__img'), im = main.querySelector('img');
    const ti = t.querySelector('img');
    im.removeAttribute('srcset'); im.removeAttribute('sizes');
    if (ti && ti.currentSrc) im.src = ti.currentSrc; // сразу показываем то, что уже загружено, затем крупное
    const big = new Image(); big.onload = () => { if (main.href === t.href) im.src = t.href; }; big.src = t.href;
    main.href = t.href;
    if (ti) main.style.setProperty('--wbg', 'url("' + (ti.currentSrc || ti.src) + '")'); // размытый фон — от нового фото
    thumbs.forEach(x => { x.classList.toggle('is-on', x === t); x.setAttribute('aria-current', x === t ? 'true' : 'false'); });
  };
  const workStep = (work, dir) => {
    const thumbs = [...work.querySelectorAll('.work__thumb')];
    const i = thumbs.findIndex(x => x.classList.contains('is-on'));
    workGo(work, thumbs[(i + dir + thumbs.length) % thumbs.length]);
  };
  d.addEventListener('click', e => {
    const t = e.target.closest('.work__thumb');
    if (t) { e.preventDefault(); e.stopPropagation(); workGo(t.closest('.work'), t); return; }
    const arr = e.target.closest('.work__arr');
    if (arr) { e.preventDefault(); e.stopPropagation(); workStep(arr.closest('.work'), +arr.dataset.dir); }
  }, true);
  d.querySelectorAll('.work__media').forEach(m => {
    let x0 = null;
    const main = m.querySelector('.work__img');
    if (!main || !m.querySelector('.work__thumb')) return;
    main.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
    main.addEventListener('touchend', e => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 40) { workStep(m.closest('.work'), dx < 0 ? 1 : -1); main.dataset.swiped = '1'; setTimeout(() => delete main.dataset.swiped, 400); }
    });
    main.addEventListener('click', e => { if (main.dataset.swiped) { e.preventDefault(); e.stopImmediatePropagation(); } }, true);
  });


  /* Ряд кнопок-разделов: человек листает его сам последние 2,5 с? */
  function navBusy(nav) { return nav._touched && Date.now() - nav._touched < 2500; }
  /* Показать кнопку в ряду, только если она не видна целиком */
  function chipIntoView(nav, a, smooth) {
    if (nav.scrollWidth <= nav.clientWidth) return;
    const l = a.offsetLeft - nav.offsetLeft, r = l + a.offsetWidth;
    if (l >= nav.scrollLeft + 8 && r <= nav.scrollLeft + nav.clientWidth - 8) return;
    nav.scrollTo({ left: Math.max(0, l - 40), behavior: smooth ? 'smooth' : 'auto' });
  }

  // Кнопки-разделы каталога: пока панель в тексте — все кнопки видны (в несколько строк);
  // когда прилипла к верху — одна строка с прокруткой и стрелками
  d.querySelectorAll('.mcat__bar').forEach(bar => {
    const pin = bar.previousElementSibling, wrap = bar.querySelector('.mcat__navwrap'), nav = wrap && wrap.querySelector('.mcat__nav');
    if (!nav) return;
    const edges = () => {
      wrap.classList.toggle('has-l', nav.scrollLeft > 4);
      wrap.classList.toggle('has-r', nav.scrollLeft + nav.clientWidth < nav.scrollWidth - 4);
    };
    ['l', 'r'].forEach(s => {
      const b = d.createElement('button');
      b.type = 'button'; b.className = 'mcat__arr mcat__arr--' + s; b.tabIndex = -1;
      b.setAttribute('aria-label', s === 'l' ? 'Прокрутить влево' : 'Прокрутить вправо'); b.textContent = s === 'l' ? '‹' : '›';
      b.addEventListener('click', () => nav.scrollBy({ left: (s === 'l' ? -1 : 1) * nav.clientWidth * .7 }));
      wrap.appendChild(b);
    });
    let tick = false;
    const check = () => {
      tick = false;
      const stuck = pin.getBoundingClientRect().top < parseFloat(getComputedStyle(bar).top) - 1;
      if (stuck !== bar.classList.contains('is-stuck')) {
        bar.classList.toggle('is-stuck', stuck);
        const on = nav.querySelector('.is-on');
        if (stuck && on && !navBusy(nav)) chipIntoView(nav, on, false);
      }
      edges();
    };
    addEventListener('scroll', () => { if (!tick) { tick = true; requestAnimationFrame(check); } }, { passive: true });
    // разделы ниже панели: при переходе по кнопке (и по ссылке с #раздел) заголовок встаёт под панелью, а не под неё
    const box = bar.closest('.mcat, .works') || bar.parentElement;
    const margin = () => {
      const h = bar.offsetHeight + 12;
      box.querySelectorAll('[id]').forEach(el => { if (!bar.contains(el)) el.style.scrollMarginTop = h + 'px'; });
    };
    addEventListener('resize', () => { check(); margin(); });
    nav.addEventListener('scroll', edges, { passive: true });
    check(); margin();
    // нажатие на раздел: плавно к заголовку раздела, сразу под панель. Адрес страницы не меняем —
    // иначе при обновлении страницы или возврате «назад» она сама прыгает к этому разделу
    nav.addEventListener('click', e => {
      const a = e.target.closest('a[href^="#"]'); if (!a) return;
      const t = d.getElementById(decodeURIComponent(a.hash.slice(1))); if (!t) return;
      e.preventDefault();
      margin();
      // выбранная кнопка — в середину ряда и сразу подсвечена
      nav.querySelectorAll('a.is-on').forEach(x => x.classList.remove('is-on'));
      a.classList.add('is-on');
      nav._touched = 0;
      const l = a.offsetLeft - nav.offsetLeft;
      nav.scrollTo({ left: Math.max(0, l - (nav.clientWidth - a.offsetWidth) / 2), behavior: 'smooth' });
      nav._chosen = Date.now();
      t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      // панель прилипает и может стать другой высоты — доводим ещё раз
      setTimeout(() => { const top = t.getBoundingClientRect().top, need = bar.getBoundingClientRect().bottom + 12;
        if (Math.abs(top - need) > 6) scrollBy({ top: top - need, behavior: 'smooth' }); }, 700);
    });
  });

  // Подсветка текущего раздела. Ряд кнопок сам подкручивается к текущей, только если её не видно
  // и человек сейчас не листает кнопки пальцем; при открытии страницы ряд не дёргается
  d.querySelectorAll('.mcat__nav').forEach(nav => {
    const links = [...nav.querySelectorAll('a[href^="#"]')];
    const secs = links.map(a => d.getElementById(decodeURIComponent(a.hash.slice(1)))).filter(Boolean);
    if (!secs.length || !window.IntersectionObserver) return;
    // «листает сам» — только когда палец двигается по ряду или крутится колесо; простое нажатие не считается
    ['touchmove', 'wheel'].forEach(ev => nav.addEventListener(ev, () => { nav._touched = Date.now(); }, { passive: true }));
    let first = true;
    const io = new IntersectionObserver(es => {
      es.forEach(en => {
        if (!en.isIntersecting) return;
        if (nav._chosen && Date.now() - nav._chosen < 1200) return; // едем к выбранному разделу — промежуточные не подсвечиваем
        links.forEach(a => {
          const on = a.hash.slice(1) === en.target.id;
          a.classList.toggle('is-on', on);
          if (on && !first && !navBusy(nav)) chipIntoView(nav, a, true);
        });
      });
      first = false;
    }, { rootMargin: '-40% 0px -55% 0px' });
    secs.forEach(s => io.observe(s));
  });
})();

/* Плитки каталога и карточки-разделы: выбранная точка фото — в центре (насколько позволяют края фото) */
(function () {
  var els = document.querySelectorAll('.cat[data-focus], img[data-focus]');
  if (!els.length) return;
  function calc(el, nw, nh) {
    var f = el.getAttribute('data-focus').split(' '), fx = f[0] / 100, fy = f[1] / 100;
    var box = el.querySelector('.cat__ph') || el;          // у плитки фото — в отдельном блоке над подписью
    var w = box.clientWidth, h = box.clientHeight;
    if (!w || !h || !nw || !nh) return null;
    var k = Math.max(w / nw, h / nh), W = nw * k, H = nh * k;
    var px = W - w > 1 ? Math.min(100, Math.max(0, (fx * W - w / 2) / (W - w) * 100)) : 50;
    var py = H - h > 1 ? Math.min(100, Math.max(0, (fy * H - h / 2) / (H - h) * 100)) : 50;
    return px.toFixed(1) + '% ' + py.toFixed(1) + '%';
  }
  function place(el, nw, nh) {
    var v = calc(el, nw, nh);
    if (!v) return;
    if (el.tagName === 'IMG') el.style.objectPosition = v; else el.style.setProperty('--pos', v);
  }
  var list = [];
  els.forEach(function (el) {
    if (el.tagName === 'IMG') {
      var go = function () { list.push([el, el.naturalWidth, el.naturalHeight]); place(el, el.naturalWidth, el.naturalHeight); };
      if (el.complete && el.naturalWidth) go(); else el.addEventListener('load', go, { once: true });
      return;
    }
    var m = /url\(["']?([^"')]+)["']?\)/.exec(el.style.getPropertyValue('--img'));
    if (!m) return;
    var im = new Image();
    im.onload = function () { list.push([el, im.naturalWidth, im.naturalHeight]); place(el, im.naturalWidth, im.naturalHeight); };
    im.src = m[1];
  });
  var t;
  addEventListener('resize', function () { clearTimeout(t); t = setTimeout(function () { list.forEach(function (a) { place(a[0], a[1], a[2]); }); }, 100); });
})();

/* [mem_works vid="карточки" poryadok="случайно"]: показываем N случайных работ — при каждом открытии другие (и из кэша) */
(function () {
  document.querySelectorAll('[data-wcards-rand]').forEach(function (box) {
    var n = +box.getAttribute('data-wcards-rand') || 6, cards = [].slice.call(box.children);
    for (var i = cards.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = cards[i]; cards[i] = cards[j]; cards[j] = t; }
    cards.forEach(function (c, k) { if (k < n) { c.hidden = false; box.appendChild(c); } else c.remove(); });
  });
})();
