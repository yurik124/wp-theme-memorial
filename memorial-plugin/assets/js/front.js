/**
 * Behaviour of the plugin blocks on the site (works in any theme): catalog sorting, Metrika goals,
 * the “Call me back” window, MAX, phone and e-mail clicks, Yandex reviews and maps loaded on demand,
 * counters, rows of buttons, embedded frames, the photo viewer, anchors.
 * Texts: window.memorialPluginFrontI18n (core/assets.php).
 */
(() => {
  const T = k => (window.memorialPluginFrontI18n || {})[k] || k;
  const d = document, body = d.body;
  // Сортировка каталога: применяется сразу при выборе
  d.querySelectorAll('[data-sort] select').forEach(sel => sel.addEventListener('change', () => {
    const u = new URL(sel.form.action);
    if (sel.value) u.searchParams.set('sort', sel.value);
    location.href = u;
  }));

  // Яндекс.Метрика: цель «Звонок» / «Письмо» + где именно нажали
  const place = a => {
    const pkg = a.closest('.pkg');
    if (pkg) return 'Пакет «' + (pkg.querySelector('h3')?.textContent.trim() || '') + '»';
    const map = [['.mbar', 'Нижняя панель (телефон)'], ['.fab', 'Плавающая кнопка'], ['.header', 'Шапка'], ['.hero', 'Первый экран'],
      ['.card', 'Карточка товара в каталоге'], ['.product', 'Страница товара'], ['#contacts', 'Блок «Контакты»'],
      ['.footer', 'Подвал'], ['.nf', 'Страница 404'], ['.empty', 'Пустой раздел каталога']];
    for (const [sel, name] of map) if (a.closest(sel)) return name;
    return 'Другое';
  };
  const goal = (name, params) => {
    if (window.memYm && typeof window.ym === 'function') window.ym(window.memYm, 'reachGoal', name, params);
  };
  const where = a => ({ 'Где нажали': place(a), 'Страница': decodeURI(location.pathname) });

  // Звонить умеет только телефон. Планшет без SIM и компьютер — нет.
  const canCall = () => /Android.+Mobile|iPhone|iPod|Windows Phone|Mobile.+Firefox|Opera Mini/i.test(navigator.userAgent)
    || (matchMedia('(pointer: coarse)').matches && Math.min(screen.width, screen.height) < 600);

  /* ---------- Окно «Перезвоните мне» ---------- */
  const cfg = window.memCall || {};
  const dlg = d.getElementById('callback');
  let openedAt = 0, from = '';
  const openCallback = (status, src) => {
    if (!dlg) return;
    from = src || '';
    const st = dlg.querySelector('[data-cb-status]');
    st.textContent = status || '';
    st.hidden = !status;
    dlg.querySelector('.cb__form').hidden = false;
    dlg.querySelector('.cb__done').hidden = true;
    dlg.querySelector('.cb__err').hidden = true;
    openedAt = Date.now();
    if (!dlg.open) dlg.showModal();
    setTimeout(() => dlg.querySelector('input[name=phone]').focus(), 50);
    goal('callback_open', { 'Окно звонка': { 'Откуда': from } });
  };
  if (dlg) {
    dlg.querySelectorAll('[data-cb-close]').forEach(b => b.addEventListener('click', () => dlg.close()));
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); }); // клик по затемнению
    const form = dlg.querySelector('.cb__form');
    const err = dlg.querySelector('.cb__err');
    const phone = form.elements.phone;
    // Маска +7 (900) 000-00-00
    phone.addEventListener('input', () => {
      let v = phone.value.replace(/\D/g, '');
      if (!v) { phone.value = ''; return; }
      if (v[0] === '8') v = '7' + v.slice(1);
      if (v[0] !== '7') v = '7' + v;
      v = v.slice(0, 11);
      const p = [v.slice(1, 4), v.slice(4, 7), v.slice(7, 9), v.slice(9, 11)];
      phone.value = '+7' + (p[0] ? ' (' + p[0] : '') + (p[0].length === 3 && p[1] ? ') ' + p[1] : '') + (p[2] ? '-' + p[2] : '') + (p[3] ? '-' + p[3] : '');
    });
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const fail = m => { err.textContent = m; err.hidden = false; };
      if (phone.value.replace(/\D/g, '').length < 11) return fail(T('fullNumber'));
      if (!form.elements.consent.checked) return fail(T('consent'));
      err.hidden = true;
      const btn = form.querySelector('[type=submit]');
      btn.disabled = true;
      try {
        const r = await fetch(cfg.api, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            phone: phone.value, name: form.elements.name.value, website: form.elements.website.value,
            consent: 1, page: location.href, elapsed: Math.round((Date.now() - openedAt) / 1000)
          })
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(j.message || T('notSent'));
        form.hidden = true;
        dlg.querySelector('.cb__done').hidden = false;
        dlg.querySelector('[data-cb-status]').hidden = true;
        form.reset();
        goal('callback_sent', { 'Заявка на звонок': { 'Откуда': from, 'Страница': decodeURI(location.pathname) } });
      } catch (x) {
        fail((x.message || T('notSent')) + ' ' + T('callUs'));
      } finally { btn.disabled = false; }
    });
  }
  // Любая ссылка/кнопка с data-callback открывает окно
  d.addEventListener('click', e => {
    const b = e.target.closest('[data-callback]');
    if (!b) return;
    e.preventDefault();
    if (dlg) openCallback('', place(b));
    else { const t = d.querySelector('a[href^="tel:"]'); if (t) location.href = t.href; }   // заявки выключены в плагине — кнопка звонит
  });

  /* ---------- Попытка открыть приложение MAX ---------- */
  const tryApp = url => new Promise(resolve => {
    let left = false;
    const mark = () => { left = true; };
    addEventListener('blur', mark, { once: true });          // браузер ушёл в приложение или спросил «Открыть MAX?»
    d.addEventListener('visibilitychange', mark, { once: true });
    if (/Firefox/i.test(navigator.userAgent)) {               // Firefox показывает ошибку на всю страницу — пробуем в скрытом фрейме
      const f = d.createElement('iframe');
      f.hidden = true; f.src = url; d.body.append(f);
      setTimeout(() => f.remove(), 2500);
    } else {
      location.href = url;
    }
    setTimeout(() => {
      removeEventListener('blur', mark);
      d.removeEventListener('visibilitychange', mark);
      resolve(left);
    }, 1500);
  });

  /* ---------- Клики по телефонам и почте ---------- */
  d.addEventListener('click', async e => {
    const a = e.target.closest('a[href^="tel:"], a[href^="mailto:"]');
    if (!a) return;
    if (a.protocol === 'mailto:') { goal('email_click', { 'Письмо': where(a) }); return; }
    if (canCall()) { goal('phone_click', { 'Звонок': where(a) }); return; } // телефон — обычный звонок

    // Компьютер: MAX → если не вышло, окно обратного звонка
    if (!dlg) return;
    e.preventDefault();
    const src = place(a);
    if (cfg.maxApp) {
      goal('max_click', { 'MAX': where(a) });
      const opened = await tryApp(cfg.maxApp);
      if (!opened) openCallback(T('maxFailed'), src);
    } else if (cfg.max) {
      goal('max_click', { 'MAX': where(a) });
      const w = window.open(cfg.max, '_blank');
      if (!w) { // браузер не дал открыть вкладку
        openCallback(T('maxFailed'), src);
        return;
      }
      try { w.opener = null; } catch (x) {}
      // Посетитель быстро вернулся на сайт — значит, в MAX написать не вышло: предлагаем звонок
      const t0 = Date.now();
      const back = () => {
        if (d.visibilityState !== 'visible') return;
        d.removeEventListener('visibilitychange', back);
        removeEventListener('focus', back);
        if (Date.now() - t0 < 60000) openCallback(T('maxBack'), src);
      };
      setTimeout(() => {
        // вкладка открылась в фоне и сайт остался перед глазами
        if (d.visibilityState === 'visible' && d.hasFocus()) back();
        else { d.addEventListener('visibilitychange', back); addEventListener('focus', back); }
      }, 1200);
    } else {
      openCallback('', src);
    }
  });

  // Отзывы с Яндекс Карт: сами — после согласия на cookie (или если согласие не спрашивается), иначе по кнопке
  d.querySelectorAll('[data-reviews]').forEach(box => {
    const load = () => {
      if (box.classList.contains('is-loaded')) return;
      const f = d.createElement('iframe');
      f.src = box.dataset.reviews;
      f.title = T('reviewsFrame');
      f.loading = 'lazy';
      box.querySelector('.reviews__ph')?.replaceWith(f);
      box.classList.add('is-loaded');
    };
    let ok = false;
    try { ok = localStorage.getItem('mem_consent_v1') === 'yes'; } catch (x) {}
    const auto = ok || !(window.memTrack && window.memTrack.need);
    if (auto) {
      const io = new IntersectionObserver(es => { if (es.some(e => e.isIntersecting)) { io.disconnect(); load(); } }, { rootMargin: '400px' });
      io.observe(box);
    }
    box.querySelector('[data-reviews-load]')?.addEventListener('click', load);
    d.addEventListener('mem:consent', load);
  });

  // Появление блоков и цифр (цифры не «пересчитываются» — сразу верные и плавно проявляются, см. .stat в main.css)
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (!en.isIntersecting) return;
      const el = en.target;
      el.classList.add('is-in');
      io.unobserve(el);
    });
  }, { threshold: 0.15 });
  d.querySelectorAll('.reveal, [data-count]').forEach(el => io.observe(el));

  // Карты: вкладки адресов, загрузка по требованию
  d.querySelectorAll('.maps').forEach(box => {
    const tabs = [...box.querySelectorAll('[role="tab"]')];
    const load = panel => {
      if (!panel || panel.classList.contains('is-loaded')) return;
      const f = d.createElement('iframe');
      f.src = panel.dataset.src;
      f.title = T('mapFrame');
      f.loading = 'lazy';
      f.allowFullscreen = true;
      panel.replaceChildren(f);
      panel.classList.add('is-loaded');
      box.dataset.loaded = '1';
    };
    const select = tab => {
      tabs.forEach(t => {
        const on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        d.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
      // если карту уже открывали, следующая вкладка грузится сразу
      if (box.dataset.loaded) load(d.getElementById(tab.getAttribute('aria-controls')));
    };
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => select(t));
      t.addEventListener('keydown', e => {
        const dir = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (!dir) return;
        const next = tabs[(i + dir + tabs.length) % tabs.length];
        next.focus();
        select(next);
      });
    });
    box.querySelectorAll('.map__load').forEach(b =>
      b.addEventListener('click', () => load(b.closest('.map')), { once: true }));
  });
  // Ряды кнопок с прокруткой вбок: стрелки и затухание по краям, когда есть куда листать
  d.querySelectorAll('.srow').forEach(row => {
    const sc = row.querySelector('.srow__in');
    if (!sc) return;
    ['l', 'r'].forEach(s => {
      const b = d.createElement('button');
      b.type = 'button'; b.className = 'srow__arr srow__arr--' + s; b.tabIndex = -1;
      b.setAttribute('aria-label', s === 'l' ? T('scrollLeft') : T('scrollRight'));
      b.textContent = s === 'l' ? '‹' : '›';
      b.addEventListener('click', () => sc.scrollBy({ left: (s === 'l' ? -1 : 1) * sc.clientWidth * .7, behavior: 'smooth' }));
      row.appendChild(b);
    });
    const upd = () => {
      row.classList.toggle('has-l', sc.scrollLeft > 4);
      row.classList.toggle('has-r', sc.scrollLeft + sc.clientWidth < sc.scrollWidth - 4);
    };
    sc.addEventListener('scroll', upd, { passive: true });
    addEventListener('resize', upd);
    upd();
    // выбранная кнопка — в зону видимости
    sc.addEventListener('click', e => { const c = e.target.closest('button, a'); if (c) c.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }); });
  });

  // Встроенные окна (панорама и т.п.): грузятся по кнопке, чтобы не тормозить страницу
  d.querySelectorAll('[data-embed]').forEach(box => {
    box.querySelector('[data-embed-load]')?.addEventListener('click', () => {
      const f = d.createElement('iframe');
      f.src = box.dataset.embed;
      f.title = box.dataset.title || '';
      f.allowFullscreen = true;
      f.allow = 'fullscreen';
      box.replaceChildren(f);
      box.classList.add('is-loaded');
    }, { once: true });
  });

  // Крупное фото с листанием: карточки каталога, работы и фото в тексте страниц со ссылкой на файл.
  // У товара с несколькими фото (data-lb-set) листаются все его фото, затем — следующий товар.
  // Пока крупное фото грузится, видно уже загруженную уменьшенную копию; соседние фото подгружаются заранее.
  const lb = d.getElementById('lb');
  if (lb && lb.showModal) {
    const isImg = a => /\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i.test(a.getAttribute('href') || '');
    d.querySelectorAll('.content a:not(.work__thumb)').forEach(a => { if (isImg(a) && a.querySelector('img')) a.setAttribute('data-lb', ''); });
    let group = [], idx = 0;
    const img = lb.querySelector('img'), cap = lb.querySelector('figcaption'), count = lb.querySelector('.lb__count');
    const loaded = new Set(), loading = new Map();
    const preload = it => {
      if (!it || loaded.has(it.href) || loading.has(it.href)) return;
      const im = new Image();
      loading.set(it.href, im);
      im.onload = () => { loaded.add(it.href); loading.delete(it.href); if (group[idx] === it) { img.src = it.href; img.classList.remove('is-low'); } };
      im.onerror = () => loading.delete(it.href);
      im.decoding = 'async';
      im.src = it.href;
    };
    const idle = window.requestIdleCallback || (f => setTimeout(f, 120));
    const show = i => {
      idx = (i + group.length) % group.length;
      const it = group[idx];
      if (loaded.has(it.href)) { img.src = it.href; img.classList.remove('is-low'); }
      else { if (it.low) { img.src = it.low; img.classList.add('is-low'); } preload(it); }
      img.alt = it.alt || '';
      cap.textContent = it.cap || '';
      const many = group.length > 1;
      lb.querySelectorAll('.lb__nav').forEach(b => b.hidden = !many);
      if (count) { count.hidden = !many; count.textContent = (idx + 1) + ' / ' + group.length; }
      idle(() => [1, -1, 2].forEach(k => preload(group[(idx + k + group.length) % group.length])));
    };
    // ссылка → список фото: [{href, low (уже загруженная копия), cap, alt}]
    const itemsOf = a => {
      let set = null;
      try { set = a.dataset.lbSet ? JSON.parse(a.dataset.lbSet) : null; } catch (e) {}
      const cap = a.dataset.cap || a.closest('figure')?.querySelector('figcaption')?.textContent.trim() || '';
      const phs = [...a.querySelectorAll('img')];
      if (!set) return [{ href: a.href, low: phs.find(x => x.classList.contains('is-on'))?.currentSrc || phs[0]?.currentSrc || '', cap, alt: phs[0]?.alt || '', a }];
      return set.map((h, k) => ({ href: new URL(h, location.href).href, low: phs[k]?.currentSrc || '', cap: cap + (set.length > 1 ? ' · ' + T('photoOf').replace('%1$d', k + 1).replace('%2$d', set.length) : ''), alt: phs[k]?.alt || '', a }));
    };
    d.addEventListener('click', e => {
      const a = e.target.closest('a[data-lb]');
      if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
      e.preventDefault();
      const items = a.closest('.work')?.querySelectorAll('[data-lb-item]');
      let start = 0;
      if (items && items.length) {
        group = [...items].map(x => ({ href: x.href, low: x.querySelector('img')?.currentSrc || '', cap: x.dataset.cap || '', alt: x.querySelector('img')?.alt || '' }));
        start = Math.max(0, group.findIndex(x => x.href === a.href));
      } else {
        const scope = a.closest('.mcat__grid, .wp-block-gallery, .works__list, .mem-photos') || a.parentElement;
        group = [];
        [...scope.querySelectorAll('a[data-lb]')].filter(x => x.offsetParent !== null).forEach(x => {
          const its = itemsOf(x);
          if (x === a) start = group.length + Math.max(0, its.findIndex(t => t.href === a.href));
          group.push(...its);
        });
      }
      show(start);
      d.documentElement.classList.add('lb-open');     // страница под фото не прокручивается
      lb.showModal();
    });
    lb.querySelector('.lb__x').addEventListener('click', () => lb.close());
    lb.querySelector('.lb__prev').addEventListener('click', () => show(idx - 1));
    lb.querySelector('.lb__next').addEventListener('click', () => show(idx + 1));
    lb.addEventListener('click', e => { if (e.target === lb || e.target.classList.contains('lb__stage')) lb.close(); });
    lb.addEventListener('keydown', e => { if (e.key === 'ArrowLeft') show(idx - 1); if (e.key === 'ArrowRight') show(idx + 1); });
    let x0 = null;
    lb.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchmove', e => { if (e.cancelable) e.preventDefault(); }, { passive: false });   // без прокрутки страницы
    lb.addEventListener('touchend', e => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 50 && group.length > 1) show(idx + (dx < 0 ? 1 : -1)); x0 = null; });
    lb.addEventListener('close', () => { img.removeAttribute('src'); d.documentElement.classList.remove('lb-open'); });
  }


  // Переход по ссылке с якорем (#раздел): пока догружаются фото и каталог, страница сдвигается —
  // доводим прокрутку до нужного места ещё пару раз, если человек сам не начал листать
  const navType = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0] || {}).type;
  // при обновлении страницы и «назад» браузер сам возвращает на прежнее место — не перебиваем его
  if (location.hash.length > 1 && navType !== 'reload' && navType !== 'back_forward') {
    let user = false;
    ['wheel', 'touchstart', 'keydown'].forEach(ev => addEventListener(ev, () => { user = true; }, { once: true, passive: true }));
    const go = () => {
      if (user) return;
      let id; try { id = decodeURIComponent(location.hash.slice(1)); } catch (x) { return; }
      // нет такого раздела (заголовок переименовали или заменили каталогом) — ведём к каталогу на странице
      const t = d.getElementById(id) || d.querySelector('.mcat, .works');
      if (t) t.scrollIntoView({ block: 'start' });
    };
    addEventListener('load', () => { go(); setTimeout(go, 500); setTimeout(go, 1500); });
  }
})();

/* Ряды кнопок (разделы каталога, филиалы, темы вопросов) можно листать, потянув мышью.
   Если потянули — щелчок по кнопке после этого не срабатывает */
(function () {
  document.querySelectorAll('.mcat__nav, .srow__in').forEach(function (el) {
    var x0 = 0, s0 = 0, down = false, moved = false;
    el.addEventListener('dragstart', function (e) { e.preventDefault(); });
    el.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse' || e.button !== 0 || el.scrollWidth <= el.clientWidth) return;
      down = true; moved = false; x0 = e.clientX; s0 = el.scrollLeft;
    });
    addEventListener('pointermove', function (e) {
      if (!down) return;
      var dx = e.clientX - x0;
      if (!moved && Math.abs(dx) > 5) { moved = true; el.classList.add('is-drag'); el._touched = Date.now(); }
      if (moved) { el.scrollLeft = s0 - dx; el._touched = Date.now(); }
    });
    addEventListener('pointerup', function () {
      if (!down) return;
      down = false;
      if (moved) setTimeout(function () { el.classList.remove('is-drag'); }, 0);
    });
    el.addEventListener('click', function (e) {
      if (el.classList.contains('is-drag')) { e.preventDefault(); e.stopPropagation(); }
    }, true);
  });
})();
