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


  // ---------- Поиск с подсказками (общий для «Вопрос–ответ» и «Полезно знать») ----------
  // Без учёта окончаний («похороны» = «похорон»), с синонимами («стоимость» = «цена»), служебные слова не учитываются.
  const sNorm = s => (s || '').toLowerCase().replace(/ё/g, 'е');
  const sStop = new Set('как что где кто когда какие какой какая каких можно ли не на в во и или по за для от до из у о об при это мне нам вы вас ваш если а но то же бы нужно надо есть ли'.split(' '));
  const sStem = w => w.length >= 8 ? w.slice(0, -3) : w.length >= 6 ? w.slice(0, -2) : w.length >= 5 ? w.slice(0, -1) : w;
  const sSyn = [['цен', 'стоим', 'стоит', 'скольк', 'прайс'], ['докум', 'справк', 'бумаг', 'свидетельст'], ['пособ', 'выплат', 'компенсац'],
    ['кладбищ', 'могил', 'захоронен'], ['крема', 'прах'], ['памятник', 'надгроб'], ['перевоз', 'транспорт', 'катафалк', 'доставк'], ['поминк', 'поминал', 'обед'],
    ['умер', 'смерт', 'скончал'], ['морг']];
  const sWords = q => sNorm(q).split(/[^a-zа-я0-9]+/).filter(w => w.length > 1 && !sStop.has(w)).map(w => {
    const st = sStem(w);
    const g = sSyn.find(g => g.some(x => st.startsWith(x) || x.startsWith(st)));
    return g ? [st, ...g] : [st];
  });
  const sEsc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const sHtml = s => s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const sMark = (text, words) => {
    const alts = words.flat().filter(x => x.length > 1);
    if (!alts.length) return sHtml(text);
    const re = new RegExp('([а-яёa-z0-9]*(?:' + alts.map(a => sEsc(a).replace(/е/g, '[её]')).join('|') + ')[а-яёa-z0-9]*)', 'gi');   // слово целиком
    return sHtml(text).replace(re, '<mark>$1</mark>');
  };
  const sWord = (n, a, b, c) => { const m = n % 100, k = n % 10; return (m > 10 && m < 20) ? c : k === 1 ? a : (k > 1 && k < 5) ? b : c; };

  /**
   * box — [data-msearch]; items — [{q, t, g?, ...}]; opts.pick(item) — выбрали подсказку; opts.filter(words, found[]) — показать найденное;
   * opts.status(found, words) — текст под полем.
   */
  const memSearch = (box, items, opts) => {
    const q = box.querySelector('[data-msearch-q]'), list = box.querySelector('.msearch__list'), x = box.querySelector('[data-msearch-clear]');
    const st = box.querySelector('[data-msearch-status]'), none = box.querySelector('[data-msearch-none]'), pop = box.querySelector('[data-msearch-pop]');
    items.forEach(it => { it._t = sNorm(it.t || it.q); it._q = sNorm(it.q); });
    const score = (words) => {
      let best = 0;
      items.forEach(it => {
        it.n = words.filter(alts => alts.some(w => it._t.includes(w))).length;
        it.k = words.filter(alts => alts.some(w => it._q.includes(w))).length * 4 + it.n * 2 + (it.k0 || 0) * .5;   // совпало в самом вопросе — выше
        if (it.n > best) best = it.n;
      });
      // все слова — точно; если так ничего нет — где совпало больше всего
      return best ? items.filter(it => it.n === best) : [];
    };
    let sel = -1, shown = [], timer;
    // статистика «Что ищут на сайте»: итоговый запрос (не каждую букву) и сколько нашлось
    let sent = '', lt, last = ['', 0];
    const log = () => {
      clearTimeout(lt);
      const [val, n] = last;
      if (!box.dataset.log || val.length < 3 || val === sent) return;
      sent = val;
      const f = new FormData();
      f.append('action', 'mem_sq'); f.append('q', val); f.append('n', n); f.append('w', box.dataset.w || 'f');
      try { navigator.sendBeacon ? navigator.sendBeacon(box.dataset.log, f) : fetch(box.dataset.log, { method: 'POST', body: f, keepalive: true }); } catch (e) {}
    };
    addEventListener('pagehide', log);
    const close = () => { list.hidden = true; q.setAttribute('aria-expanded', 'false'); sel = -1; };
    const fitList = () => {   // на телефоне список не прячется под клавиатурой
      const vh = window.visualViewport ? visualViewport.height + visualViewport.offsetTop : innerHeight;
      list.style.maxHeight = Math.max(160, vh - list.getBoundingClientRect().top - 12) + 'px';
    };
    const render = (words, found) => {
      shown = found.slice().sort((a, b) => b.k - a.k).slice(0, innerWidth < 700 ? 5 : 7);
      list.innerHTML = shown.map((it, i) => '<li role="option" id="' + q.id + '-o' + i + '" class="msearch__opt"><span class="msearch__oq">' + sMark(it.q, words) + '</span>'
        + (it.g ? '<span class="msearch__og">' + sHtml(it.g) + '</span>' : '') + '</li>').join('');
      list.hidden = !shown.length || d.activeElement !== q;
      q.setAttribute('aria-expanded', String(!list.hidden));
      if (!list.hidden) fitList();
      sel = -1;
    };
    const run = () => {
      const val = q.value.trim(), words = sWords(val);
      x.hidden = !val;
      (box.closest('[data-faq],[data-guide]') || box).classList.toggle('is-searching', !!words.length);
      const found = words.length ? score(words) : [];
      opts.filter(words, found);
      render(words, found);
      if (st) st.innerHTML = opts.status(found, words, val);
      if (none) none.hidden = !words.length || found.length > 0;
      if (pop) pop.hidden = !!val;
      clearTimeout(lt);
      if (words.length && !pre0) { last = [val, found.length]; lt = setTimeout(log, 2500); }   // перестал печатать — запрос итоговый
      pre0 = false;
    };
    let pre0 = false;
    const go = i => { const it = shown[i]; if (!it) return; log(); close(); q.blur(); opts.pick(it); };
    q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 90); });
    q.addEventListener('focus', () => {
      if (innerWidth < 700) {
        // на телефоне поле — под шапку, чтобы подсказки поместились над клавиатурой.
        // Браузер сам прокручивает к полю, когда выезжает клавиатура, — поэтому поднимаем после этого, без плавности.
        const bar = box.querySelector('.msearch__bar');
        const lift = () => {
          const hh = d.querySelector('.header')?.getBoundingClientRect().bottom || 0;
          const top = bar.getBoundingClientRect().top;
          if (Math.abs(top - hh) > 16) scrollTo({ top: scrollY + top - hh, behavior: 'instant' });
          if (!list.hidden) fitList();
        };
        setTimeout(lift, 60); setTimeout(lift, 450);
        if (window.visualViewport) visualViewport.addEventListener('resize', () => setTimeout(lift, 30), { once: true });
      }
      if (q.value.trim() && shown.length) { list.hidden = false; q.setAttribute('aria-expanded', 'true'); fitList(); }
    });
    q.addEventListener('blur', () => setTimeout(close, 180));
    q.addEventListener('keydown', e => {
      const opts2 = [...list.children];
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (list.hidden || !opts2.length) return;
        e.preventDefault();
        sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + opts2.length) % opts2.length;
        opts2.forEach((o, i) => o.classList.toggle('is-sel', i === sel));
        q.setAttribute('aria-activedescendant', opts2[sel].id);
        opts2[sel].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (sel >= 0) go(sel);
        else { clearTimeout(timer); run(); log(); close(); q.blur(); opts.enter && opts.enter(); }   // Enter — убрать клавиатуру и показать найденное
      } else if (e.key === 'Escape') close();
    });
    list.addEventListener('mousedown', e => e.preventDefault());
    list.addEventListener('click', e => { const o = e.target.closest('.msearch__opt'); if (o) go([...list.children].indexOf(o)); });
    x.addEventListener('click', () => { q.value = ''; run(); q.focus(); });
    if (pop) pop.querySelectorAll('[data-q]').forEach(b => {
      if (!score(sWords(b.dataset.q)).length) { b.remove(); return; }   // слово без результатов не показываем
      b.addEventListener('click', () => { q.value = b.dataset.q; run(); log(); close(); opts.enter && opts.enter(); });
    });
    if (pop && !pop.querySelector('[data-q]')) pop.remove();
    if (window.visualViewport) visualViewport.addEventListener('resize', () => { if (!list.hidden) fitList(); });
    const pre = new URLSearchParams(location.search).get('q');   // /vopros-otvet/?q=пособие
    if (pre) { q.value = pre; pre0 = true; run(); close(); } else run();   // ?q= из ссылки не считаем
    return { run };
  };
  // плавная прокрутка к найденному и короткая подсветка
  const sFlash = el => { el.classList.remove('is-flash'); void el.offsetWidth; el.classList.add('is-flash'); };
  const sTo = el => {   // под шапкой и прилипшим полем поиска
    const hh = (d.querySelector('.header')?.getBoundingClientRect().bottom || 0) + (el.closest('[data-faq],[data-guide]')?.querySelector('.msearch__bar')?.offsetHeight || 0) + 12;
    scrollTo({ top: scrollY + el.getBoundingClientRect().top - hh, behavior: 'smooth' });
  };

  // Вопрос–ответ [mem_faq]: темы, поиск с подсказками
  d.querySelectorAll('[data-faq]').forEach(box => {
    const chips = [...box.querySelectorAll('[data-faq-group]')];
    const groups = [...box.querySelectorAll('.mfaq__group')];
    const dets = [...box.querySelectorAll('details')];
    const toc = box.querySelector('.mfaq__toc');
    const setChip = id => chips.forEach(c => { const on = c.dataset.faqGroup === id; c.classList.toggle('is-on', on); c.setAttribute('aria-pressed', String(on)); });
    let ms = null;
    chips.forEach(c => c.addEventListener('click', () => {
      const id = c.dataset.faqGroup;
      const inp = box.querySelector('[data-msearch-q]');
      if (inp && inp.value) { inp.value = ''; ms && ms.run(); }
      dets.forEach(el => { el.hidden = false; el.open = false; });
      groups.forEach(g => g.hidden = !!id && g.id !== id);
      setChip(id);
      const top = (groups.find(g => !g.hidden) || box).getBoundingClientRect().top;
      if (id || top < 0) sTo(groups.find(g => !g.hidden) || box);
    }));
    // на ПК много тем — сначала несколько, остальные по кнопке
    if (toc && chips.length > 7) {
      const more = d.createElement('button');
      more.type = 'button'; more.className = 'chip mfaq__more-t';
      more.textContent = 'Ещё темы: ' + (chips.length - 6);
      more.addEventListener('click', () => { toc.classList.remove('is-cut'); more.remove(); });
      toc.classList.add('is-cut'); toc.append(more);
    }
    const sbox = box.querySelector('[data-msearch]');
    if (!sbox) return;
    const items = dets.map(el => {
      const s = el.querySelector('summary');
      return { el, s, html: s.innerHTML, q: s.textContent.trim(), t: el.textContent, g: el.dataset.g || '' };
    });
    ms = memSearch(sbox, items, {
      filter: (words, found) => {
        setChip('');
        const ok = new Set(found);
        items.forEach(it => {
          const vis = !words.length || ok.has(it);
          it.el.hidden = !vis; it.el.open = false;
          it.s.innerHTML = words.length && vis ? sMark(it.q, words) : it.html;
        });
        if (words.length && found.length && found.length <= 2) found.forEach(it => { it.el.open = true; });
        groups.forEach(g => g.hidden = !g.querySelector('details:not([hidden])'));
        box.classList.remove('is-upd'); void box.offsetWidth; box.classList.add('is-upd');
      },
      status: (found, words, val) => !words.length
        ? items.length + ' ' + sWord(items.length, 'вопрос', 'вопроса', 'вопросов') + ' с ответами. Начните вводить — подскажем.'
        : found.length ? 'По запросу «' + sHtml(val) + '» — ' + found.length + ' ' + sWord(found.length, 'вопрос', 'вопроса', 'вопросов') + ' ниже. <button type="button" class="msearch__reset">Показать все</button>' : '',
      pick: it => { it.el.hidden = false; it.el.closest('.mfaq__group').hidden = false; it.el.open = true; sTo(it.el); sFlash(it.el); },
      enter: () => { const f = box.querySelector('details:not([hidden])'); if (f) sTo(f.closest('.mfaq__group') || f); }
    });
    sbox.addEventListener('click', e => { if (e.target.closest('.msearch__reset')) { const i = sbox.querySelector('[data-msearch-q]'); i.value = ''; ms.run(); } });
  });

  // Полезно знать [mem_guide]: карточки статей и поиск по ним (и по вопросам со всего сайта)
  d.querySelectorAll('[data-guide]').forEach(box => {
    const sbox = box.querySelector('[data-msearch]');
    let idx = [];
    try { idx = JSON.parse(box.querySelector('[data-guide-idx]').textContent); } catch (e) { return; }
    const cards = [...box.querySelectorAll('[data-card]')];
    idx.forEach(it => { it.k0 = it.k === 'p' ? 2 : it.k === 'h' ? 1 : 0; });
    const ms = memSearch(sbox, idx, {
      filter: (words, found) => {
        const hit = new Set(found.map(it => it.c));
        cards.forEach(c => { c.hidden = !!words.length && !hit.has(+c.dataset.card); });
        box.classList.remove('is-upd'); void box.offsetWidth; box.classList.add('is-upd');
      },
      status: (found, words, val) => {
        if (!words.length) return '';
        if (!found.length) return '';
        const arts = new Set(found.filter(it => it.c >= 0).map(it => it.c)).size;
        const other = found.filter(it => it.c < 0).length;
        return 'По запросу «' + sHtml(val) + '»: ' + (arts ? arts + ' ' + sWord(arts, 'статья', 'статьи', 'статей') : '')
          + (arts && other ? ' и ' : '') + (other ? other + ' ' + sWord(other, 'ответ', 'ответа', 'ответов') + ' на других страницах' : '')
          + '. Нажмите на подсказку, чтобы открыть нужное место. <button type="button" class="msearch__reset">Показать все</button>';
      },
      pick: it => { location.href = it.u; },
      enter: () => { const c = cards.find(c => !c.hidden); if (c) sTo(c); }
    });
    sbox.addEventListener('click', e => { if (e.target.closest('.msearch__reset')) { const i = sbox.querySelector('[data-msearch-q]'); i.value = ''; ms.run(); } });
  });

  // Ссылка на вопрос (#v-…) — раскрыть его
  const openHash = () => {
    let el = location.hash.length > 1 && d.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (!el) return;
    if (el.tagName === 'DETAILS') { el.hidden = false; el.open = true; sFlash(el); setTimeout(() => sTo(el), 250); }
    for (let p = el.parentElement; p; p = p.parentElement) if (p.tagName === 'DETAILS') p.open = true;
  };
  openHash();
  addEventListener('hashchange', openHash);


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
  // Каталог: в больших разделах сначала часть товаров, остальные — «Показать ещё»
  d.querySelectorAll('.mcat__grid[data-more]').forEach(g => {
    const step = +g.dataset.more;
    let shown = step;
    const btn = d.createElement('button');
    btn.type = 'button'; btn.className = 'btn btn--line mcat__more';
    g.after(btn);
    const apply = () => {
      const li = [...g.children];
      li.forEach((x, i) => { x.hidden = i >= shown; });
      const rest = li.length - shown;
      btn.hidden = rest <= 0;
      btn.textContent = 'Показать ещё ' + Math.min(step, rest) + (rest > step ? ' из ' + rest : '');
    };
    btn.addEventListener('click', () => {
      const first = g.children[shown];
      shown += step; apply();
      first?.querySelector('a,button')?.focus({ preventScroll: true });   // для клавиатуры — на первый из новых
    });
    g.memMore = apply;
    // ссылка на товар (#p-123) из скрытой части — показать до него
    const h = location.hash.length > 1 && g.querySelector(location.hash.replace(/[^\w#-]/g, ''));
    if (h) { const i = [...g.children].indexOf(h.closest('li')); if (i >= shown) shown = Math.ceil((i + 1) / step) * step; }
    apply();
    if (h) setTimeout(() => h.scrollIntoView({ block: 'center' }), 100);
  });
  d.querySelectorAll('[data-mcat-sort]').forEach(sel => sel.addEventListener('change', () => {
    sel.closest('[data-mcat]').querySelectorAll('.mcat__grid').forEach(g => { sortBy([...g.children], sel.value).forEach(li => g.appendChild(li)); g.memMore?.(); });
  }));
  d.querySelectorAll('[data-works]').forEach(box => {
    const list = box.querySelector('.works__list');
    box.querySelector('[data-works-sort]')?.addEventListener('change', e => sortBy([...list.children], e.target.value).forEach(w => list.appendChild(w)));
    box.querySelectorAll('[data-type]').forEach(b => b.addEventListener('click', () => {
      box.querySelectorAll('[data-type]').forEach(x => x.classList.toggle('is-on', x === b));
      list.querySelectorAll('.work').forEach(w => w.classList.toggle('is-hidden', !!b.dataset.type && !(' ' + w.dataset.types + ' ').includes(' ' + b.dataset.type + ' ')));
    }));
  });

  // Карточка товара с несколькими фото: стрелки, точки, листание пальцем.
  // Ссылка карточки всегда ведёт на текущее фото — крупный просмотр открывается с него.
  const cardGo = (box, dir) => {
    const phs = [...box.querySelectorAll('.mcard__ph')];
    if (phs.length < 2) return;
    const i = phs.findIndex(x => x.classList.contains('is-on'));
    const n = (i + dir + phs.length) % phs.length, ph = phs[n];
    phs.forEach(x => x.classList.toggle('is-on', x === ph));
    const after = phs[(n + dir + phs.length) % phs.length];
    if (after) after.loading = 'eager';             // следующее фото — заранее, чтобы не ждать
    if (box.dataset.mbg0 === undefined) box.dataset.mbg0 = box.style.getPropertyValue('--mbg') || '';
    const bg = n ? (ph.dataset.bg ? 'url("' + ph.dataset.bg + '")' : '') : box.dataset.mbg0;
    box.classList.toggle('has-bg', !!bg);
    bg ? box.style.setProperty('--mbg', bg) : box.style.removeProperty('--mbg');
    const link = box.closest('.mcard__link');
    if (link && ph.dataset.big && link.hasAttribute('data-lb')) link.href = ph.dataset.big;
    box.closest('.mcard')?.querySelectorAll('.mcard__dots i').forEach((d, k) => d.classList.toggle('is-on', k === n));
  };
  d.addEventListener('click', e => {
    const a = e.target.closest('.mcard__arr');
    if (!a) return;
    e.preventDefault(); e.stopPropagation();
    cardGo(a.closest('.mcard__img'), +a.dataset.dir);
  }, true);
  d.addEventListener('keydown', e => {
    const a = e.target.closest?.('.mcard__arr');
    if (a && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); e.stopPropagation(); cardGo(a.closest('.mcard__img'), +a.dataset.dir); }
  }, true);
  d.querySelectorAll('.mcard__img').forEach(box => {
    if (!box.querySelector('.mcard__arr')) return;
    const link = box.closest('a');
    // наведение на карточку — подгрузить второе фото заранее
    box.closest('.mcard')?.addEventListener('pointerenter', () => { const p = box.querySelectorAll('.mcard__ph')[1]; if (p) p.loading = 'eager'; }, { once: true });
    let x0 = null, y0 = 0;
    box.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    box.addEventListener('touchend', e => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0; x0 = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) { cardGo(box, dx < 0 ? 1 : -1); if (link) { link.dataset.swiped = '1'; setTimeout(() => delete link.dataset.swiped, 400); } }
    });
    if (link) link.addEventListener('click', e => { if (link.dataset.swiped) { e.preventDefault(); e.stopImmediatePropagation(); } }, true);
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
