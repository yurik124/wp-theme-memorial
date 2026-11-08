/* «Каталог → Товары»: таблица товаров типа с правкой на месте, загрузка фото перетаскиванием */
jQuery(function ($) {
  const root = document.getElementById('mcm');
  if (!root || !window.memCM) return;
  const app = root.querySelector('.mcm__app');
  let D = memCM.data;              // данные вкладки: id, name, fields, cats, products
  let filterCat = +(new URLSearchParams(location.search).get('cat') || 0), query = '';   // ?cat=… — ссылка «товары категории» со страницы «Типы и категории»
  let sortKey = '', sortDir = 1;    // сортировка по столбцу: photo | title | price | cats
  const sel = new Set();             // отмеченные товары
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const post = (action, data) => $.post(memCM.ajax, Object.assign({ action, _ajax_nonce: memCM.nonce }, data));

  /* ---------- индикатор сохранения ---------- */
  let pending = 0, failed = false;
  const status = () => {
    const el = root.querySelector('.mcm__status'); if (!el) return;
    el.className = 'mcm__status' + (failed ? ' is-err' : pending ? ' is-busy' : ' is-ok');
    el.textContent = failed ? 'Не всё сохранилось — проверьте связь и повторите' : pending ? 'Сохраняю…' : 'Все изменения сохранены';
  };
  const track = xhr => { pending++; failed = false; status(); return xhr.done(r => { if (!r || !r.success) failed = true; }).fail(() => { failed = true; }).always(() => { pending--; status(); }); };

  /* ---------- отрисовка ---------- */
  const numOf = v => { const m = String(v || '').replace(',', '.').match(/-?\d+(\.\d+)?/); return m ? m[0] : ''; };
  // больше двух характеристик — не колонками (уходят за экран), а одной колонкой: подпись + поле, в несколько рядов
  const manyF = () => D.fields.length > 2;
  const fieldInput = (f, p) => {
    const v = p.specs[f.label] || '';
    const k = 'spec:' + f.label;
    if (f.type === 'number') {
      return `<span class="mcm__num"><input type="number" step="any" data-f="${esc(k)}" data-unit="${esc(f.unit)}" value="${esc(numOf(v))}" placeholder="—">${f.unit ? `<i>${esc(f.unit)}</i>` : ''}</span>`;
    }
    if (f.type === 'list') {
      const opts = f.options.slice(); if (v && !opts.includes(v)) opts.push(v);
      return `<select data-f="${esc(k)}"><option value="">—</option>${opts.map(o => `<option${o === v ? ' selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
    }
    return `<input type="text" data-f="${esc(k)}" value="${esc(v)}" placeholder="—">`;
  };
  const catChips = p => {
    const names = p.cats.map(id => (D.cats.find(c => c.id === id) || {}).name).filter(Boolean).map(n => n.replace(/^(— )+/, ''));
    return names.length ? names.map(n => `<span class="mcm__chip">${esc(n)}</span>`).join('') : '<span class="mcm__none">без категории</span>';
  };
  const row = p => `
    <tr data-id="${p.id}" class="${p.status !== 'publish' ? 'is-hidden' : ''}${sel.has(p.id) ? ' is-sel' : ''}">
      <td class="mcm__drag" title="Перетащите, чтобы поменять порядок">⋮⋮</td>
      <td class="mcm__chk"><input type="checkbox" class="mcm__sel" aria-label="Отметить"${sel.has(p.id) ? ' checked' : ''}></td>
      <td class="mcm__ph"><div class="mcm__phw"><button type="button" class="mcm__photo" data-act="photo" title="Фото товара: нажмите, чтобы выбрать или добавить (можно несколько, первое — главное)">${p.thumb ? `<img src="${esc(p.thumb)}" alt="">` : '<span>+ фото</span>'}</button>${p.tid ? `<button type="button" class="mcm__phx" data-act="phdel" data-pid="${p.tid}" title="Убрать это фото" aria-label="Убрать главное фото">×</button>` : ''}</div></td>
      <td class="mcm__name">
        <input type="text" data-f="title" value="${esc(p.title)}" placeholder="Название" aria-label="Название">
        <input type="text" data-f="sub" value="${esc(p.sub)}" placeholder="подпись (необязательно)" class="mcm__sub" aria-label="Подпись">
        ${p.status !== 'publish' ? '<span class="mcm__badge">скрыт</span>' : ''}
      </td>
      <td class="mcm__price">
        <span class="mcm__num"><input type="text" inputmode="numeric" data-f="price" value="${esc(p.price)}" placeholder="по запросу" aria-label="Цена"><i>₽</i></span>
        <span class="mcm__pline"><label class="mcm__from"><input type="checkbox" data-f="from"${p.from ? ' checked' : ''}> от</label><input type="text" data-f="unit" value="${esc(p.unit)}" placeholder="за шт." class="mcm__unit" aria-label="Единица"></span>
      </td>
      ${manyF() ? `<td class="mcm__flds"><div class="mcm__fgrid">${D.fields.map(f => `<label class="mcm__fl"><span>${esc(f.label)}${f.unit ? ` <small>${esc(f.unit)}</small>` : ''}</span>${fieldInput(f, p)}</label>`).join('')}</div></td>` : D.fields.map(f => `<td class="mcm__fld">${fieldInput(f, p)}</td>`).join('')}
      <td class="mcm__cats"><button type="button" class="mcm__catbtn" data-act="cats">${catChips(p)}</button></td>
      <td class="mcm__more"><button type="button" class="mcm__dots" data-act="menu" aria-label="Действия">⋯</button></td>
    </tr>${(p.pids || []).length > 1 ? `
    <tr class="mcm__phrow${p.status !== 'publish' ? ' is-hidden' : ''}" data-for="${p.id}"><td colspan="2"></td><td colspan="${(manyF() ? 1 : D.fields.length) + 5}"><div class="mcm__phs" aria-label="Все фото товара — перетащите, чтобы поменять порядок">${p.pids.map((id, k) => `<span class="mcm__pht${k ? '' : ' is-main'}" data-pid="${id}" title="Перетащите, чтобы поменять порядок"><img src="${esc((p.pth || [])[k] || '')}" alt="" draggable="false">${k ? `<button type="button" class="mcm__phmain" data-act="phmain" data-pid="${id}" title="Сделать главным" aria-label="Сделать главным">★</button>` : '<b class="mcm__phbadge">главное</b>'}<button type="button" class="mcm__phx" data-act="phdel" data-pid="${id}" title="Убрать это фото" aria-label="Убрать фото">×</button></span>`).join('')}</div></td></tr>` : ''}`;

  const catName = p => { const c = p.cats.map(id => (D.cats.find(x => x.id === id) || {}).name).filter(Boolean)[0]; return c ? c.replace(/^(— )+/, '') : ''; };
  const visible = () => {
    const list = D.products.filter(p =>
      (!filterCat || p.cats.includes(filterCat)) &&
      (!query || (p.title + ' ' + p.sub + ' ' + Object.values(p.specs).join(' ')).toLowerCase().includes(query)));
    if (!sortKey) return list;
    const cmp = {
      photo: (a, b) => (b.thumb ? 1 : 0) - (a.thumb ? 1 : 0),
      title: (a, b) => a.title.localeCompare(b.title, 'ru', { numeric: true }),
      price: (a, b) => (a.price === '' ? Infinity : +a.price) - (b.price === '' ? Infinity : +b.price),
      cats:  (a, b) => (catName(a) || '\uffff').localeCompare(catName(b) || '\uffff', 'ru'),
    }[sortKey];
    // пустые (нет цены / нет категории) — всегда в конце
    return list.slice().sort((a, b) => {
      if (sortKey === 'price' && (a.price === '') !== (b.price === '')) return a.price === '' ? 1 : -1;
      if (sortKey === 'cats' && !catName(a) !== !catName(b)) return catName(a) ? -1 : 1;
      return cmp(a, b) * sortDir;
    });
  };
  const th = (k, label) => `<th><button type="button" class="mcm__sort${sortKey === k ? ' is-on' : ''}" data-sort="${k}" title="Сортировать">${label}<i>${sortKey === k ? (sortDir > 0 ? '▲' : '▼') : '↕'}</i></button></th>`;

  function render() {
    const list = visible();
    const total = D.products.length;
    app.innerHTML = `
      <div class="mcm__drop" tabindex="0" role="button" aria-label="Загрузить фото товаров">
        <input type="file" accept="image/*" multiple hidden>
        <strong>Перетащите сюда фото товаров</strong>
        <span>или <u>выберите на компьютере</u> · можно сразу много · <button type="button" class="button-link" data-act="lib">из медиатеки</button> · <button type="button" class="button-link" data-act="blank">товар без фото</button></span>
        <em>Каждое фото станет товаром «${esc(D.name)}» — останется вписать название, цену и характеристики.</em>
        <div class="mcm__queue"></div>
      </div>
      <div class="mcm__bar">
        <input type="search" class="mcm__search" placeholder="Поиск по названию и характеристикам" value="${esc(query)}">
        <div class="mcm__filters">
          <button type="button" class="mcm__f${!filterCat ? ' is-on' : ''}" data-cat="0">Все <b>${total}</b></button>
          ${D.cats.map(c => { const n = D.products.filter(p => p.cats.includes(c.id)).length; return `<button type="button" class="mcm__f${filterCat === c.id ? ' is-on' : ''}" data-cat="${c.id}">${esc(c.name.replace(/^(— )+/, ''))} <b>${n}</b></button>`; }).join('')}
        </div>
        <button type="button" class="button" data-act="prices">Изменить цены</button>
        <a class="button" href="${esc(memCM.typesUrl + '&type=' + D.id)}">⚙ Характеристики и категории</a>
        <span class="mcm__status is-ok">Все изменения сохранены</span>
      </div>
      <div class="mcm__bulk"${sel.size ? '' : ' hidden'}>
        <b>Отмечено: <span class="mcm__bulkn">${sel.size}</span></b>
        <button type="button" class="button" data-bulk="cats">Категории…</button>
        <button type="button" class="button" data-act="prices">Цены…</button>
        <button type="button" class="button" data-bulk="draft">Скрыть с сайта</button>
        <button type="button" class="button" data-bulk="publish">Показать на сайте</button>
        <button type="button" class="button" data-bulk="merge" title="Фото всех отмеченных соберутся в одном товаре">Объединить в один товар</button>
        <button type="button" class="button mcm__danger" data-bulk="trash">Удалить</button>
        <button type="button" class="button-link" data-bulk="none">снять отметку</button>
      </div>
      ${total ? `
      <div class="mcm__wrap"><table class="mcm__table">${sortKey ? `<caption class="mcm__sortnote">Отсортировано по столбцу, на сайте порядок прежний. <button type="button" class="button-link" data-sort="">Вернуть порядок сайта</button></caption>` : ''}
        <thead><tr><th></th><th class="mcm__chk"><input type="checkbox" class="mcm__selall" aria-label="Отметить все"${list.length && list.every(p => sel.has(p.id)) ? ' checked' : ''}></th>${th('photo', 'Фото')}${th('title', 'Название')}${th('price', 'Цена')}
          ${manyF() ? '<th>Характеристики</th>' : D.fields.map(f => `<th>${esc(f.label)}${f.unit ? ` <small>${esc(f.unit)}</small>` : ''}</th>`).join('')}
          ${th('cats', 'Категории')}<th></th></tr></thead>
        <tbody>${list.map(row).join('')}</tbody>
      </table></div>
      ${list.length ? '' : '<p class="mcm__empty">Ничего не найдено.</p>'}
      ${!D.fields.length ? `<p class="mcm__hint">У типа «${esc(D.name)}» пока нет характеристик. Нажмите «⚙ Характеристики и категории», чтобы добавить, например, «Высота» или «Материал» — для каждого товара появится своя колонка.</p>` : ''}` :
      '<p class="mcm__empty">В этом типе пока нет товаров — перетащите фото в рамку выше.</p>'}`;
    const sortable = !filterCat && !query && !sortKey;
    const tb = app.querySelector('tbody');
    if (tb && sortable) {
      $(tb).sortable({ items: '> tr[data-id]', handle: '.mcm__drag', axis: 'y', helper: (e, tr) => { tr.children().each(function () { $(this).width($(this).width()); }); return tr; },
        update: () => {
          const ids = [...tb.querySelectorAll(':scope > tr[data-id]')].map(tr => +tr.dataset.id);
          D.products.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));
          track(post('mem_cm_order', { ids }));
          render();   // строки с фото — снова под своими товарами
        } });
    }
    root.classList.toggle('no-sort', !sortable);
    photoSort();
  }

  const prod = id => D.products.find(p => p.id === +id);

  /* ---------- сохранение полей ---------- */
  const timers = {};
  function save(el) {
    const tr = el.closest('tr'), p = prod(tr.dataset.id), f = el.dataset.f;
    let v = el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value.trim();
    if (f === 'price') { v = v.replace(/\D/g, ''); p.price = v ? +v : ''; }
    else if (f === 'from') p.from = !!v;
    else if (f.startsWith('spec:')) {
      if (el.dataset.unit && v !== '') v = v + ' ' + el.dataset.unit;
      const k = f.slice(5); if (v) p.specs[k] = v; else delete p.specs[k];
    } else p[f] = v;
    el.classList.add('is-saving');
    track(post('mem_cm_save', { id: p.id, f, v, type: D.id })).always(() => el.classList.remove('is-saving'));
  }
  $(app).on('input', 'tbody [data-f]', function () {
    if (this.type === 'checkbox' || this.tagName === 'SELECT') return;
    const key = this.closest('tr').dataset.id + this.dataset.f;
    clearTimeout(timers[key]); timers[key] = setTimeout(() => save(this), 600);
  });
  $(app).on('change', 'tbody [data-f]', function () {
    clearTimeout(timers[this.closest('tr').dataset.id + this.dataset.f]); save(this);
  });
  // Enter — то же поле у следующего товара (удобно вбивать цены подряд)
  $(app).on('keydown', 'tbody input[data-f]', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    let tr = this.closest('tr').nextElementSibling;
    while (tr && !tr.dataset.id) tr = tr.nextElementSibling;   // строку с фото пропускаем
    const next = tr && tr.querySelector(`[data-f="${CSS.escape(this.dataset.f)}"]`);
    if (next) { next.focus(); next.select && next.select(); } else this.blur();
  });

  /* ---------- поиск и фильтр ---------- */
  $(app).on('input', '.mcm__search', function () {
    query = this.value.trim().toLowerCase(); sel.clear();
    const pos = this.selectionStart; render();
    const s = app.querySelector('.mcm__search'); s.focus(); s.setSelectionRange(pos, pos);
  });
  $(app).on('click', '.mcm__f', function () { filterCat = +this.dataset.cat; sel.clear(); render(); });
  // сортировка: первый щелчок — по возрастанию, второй — по убыванию, третий — как на сайте
  $(app).on('click', '[data-sort]', function () {
    const k = this.dataset.sort;
    if (!k) sortKey = '';
    else if (sortKey !== k) { sortKey = k; sortDir = 1; }
    else if (sortDir > 0) sortDir = -1;
    else sortKey = '';
    render();
  });

  /* ---------- загрузка фото ---------- */
  function addRows(list) {
    list.forEach(p => D.products.push(p));
    render();
    const first = list[0] && app.querySelector(`tr[data-id="${list[0].id}"] [data-f="title"]`);
    if (first) { first.focus(); first.select(); first.closest('tr').scrollIntoView({ block: 'center' }); }
    list.forEach(p => { const tr = app.querySelector(`tr[data-id="${p.id}"]`); if (tr) tr.classList.add('is-new'); });
  }
  async function upload(files) {
    files = [...files].filter(f => /^image\//.test(f.type));
    if (!files.length) return;
    const q = app.querySelector('.mcm__queue');
    const made = [];
    q.innerHTML = files.map((f, i) => `<span data-i="${i}">${esc(f.name)} — ждёт</span>`).join('');
    for (let i = 0; i < files.length; i++) {
      const el = q.querySelector(`[data-i="${i}"]`);
      if (files[i].size > memCM.maxUpload) { el.textContent = files[i].name + ' — слишком большой файл'; el.className = 'is-err'; continue; }
      el.textContent = files[i].name + ' — загружаю…';
      const fd = new FormData();
      fd.append('action', 'mem_cm_upload'); fd.append('_ajax_nonce', memCM.nonce); fd.append('type', D.id); fd.append('file', files[i]);
      pending++; status();
      try {
        const r = await (await fetch(memCM.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })).json();
        if (r && r.success) { made.push(r.data); el.textContent = files[i].name + ' — готово'; el.className = 'is-ok'; }
        else { el.textContent = files[i].name + ' — ' + ((r && r.data) || 'ошибка'); el.className = 'is-err'; }
      } catch (e) { el.textContent = files[i].name + ' — нет связи'; el.className = 'is-err'; }
      pending--; status();
    }
    const errs = q.querySelectorAll('.is-err').length;
    if (made.length) addRows(made);
    const q2 = app.querySelector('.mcm__queue');
    if (q2 && errs) q2.innerHTML = q.innerHTML;
  }
  $(app).on('click', '.mcm__drop', function (e) {
    if (e.target.closest('[data-act], input')) return;
    this.querySelector('input[type=file]').click();
  });
  $(app).on('keydown', '.mcm__drop', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.querySelector('input[type=file]').click(); } });
  $(app).on('change', '.mcm__drop input[type=file]', function () { upload(this.files); this.value = ''; });
  $(app).on('dragover dragenter', '.mcm__drop', function (e) { e.preventDefault(); this.classList.add('is-over'); });
  $(app).on('dragleave drop', '.mcm__drop', function (e) { e.preventDefault(); this.classList.remove('is-over'); if (e.type === 'drop') upload(e.originalEvent.dataTransfer.files); });
  // фото, брошенное мимо рамки, не должно открывать файл в браузере
  window.addEventListener('dragover', e => e.preventDefault());
  window.addEventListener('drop', e => { if (!e.target.closest('.mcm__drop')) e.preventDefault(); });

  $(app).on('click', '[data-act="lib"]', function () {
    const fr = wp.media({ title: 'Фото новых товаров', multiple: 'add', library: { type: 'image' }, button: { text: 'Создать товары' } });
    fr.on('select', () => {
      const atts = fr.state().get('selection').map(a => a.id);
      track(post('mem_cm_new', { type: D.id, atts })).done(r => { if (r.success) addRows(r.data); });
    });
    fr.open();
  });
  $(app).on('click', '[data-act="blank"]', () => track(post('mem_cm_new', { type: D.id })).done(r => { if (r.success) addRows(r.data); }));

  /* ---------- фото товара ---------- */
  const savePhotos = (p, ids) => track(post('mem_cm_save', { id: p.id, f: 'photos', v: ids.join(',') })).done(r => {
    if (r.success) { p.pids = r.data.pids; p.pth = r.data.pth; p.tid = r.data.tid; p.thumb = r.data.thumb; p.big = r.data.big; render(); }
  });
  // × на миниатюре — убрать фото у товара (из медиатеки оно не удаляется). Убрали главное — главным станет следующее
  $(app).on('click', '[data-act="phdel"]', function () {
    const tr = this.closest('tr'), p = prod(tr.dataset.id || tr.dataset.for), pid = +this.dataset.pid;
    savePhotos(p, (p.pids && p.pids.length ? p.pids : [p.tid]).filter(x => x && x !== pid));
  });
  // ★ — сделать фото главным (встаёт первым)
  $(app).on('click', '[data-act="phmain"]', function () {
    const tr = this.closest('tr'), p = prod(tr.dataset.for), pid = +this.dataset.pid;
    savePhotos(p, [pid, ...p.pids.filter(x => x !== pid)]);
  });
  // порядок фото — перетаскиванием в строке фото; первое = главное
  function photoSort() { $(app).find('.mcm__phs').each(function () {
    const tr = this.closest('tr'), p = prod(tr.dataset.for);
    $(this).sortable({ items: '> .mcm__pht', axis: 'x', tolerance: 'pointer', distance: 4, placeholder: 'mcm__phph', forcePlaceholderSize: true,
      cancel: 'button', update: () => savePhotos(p, [...this.querySelectorAll('.mcm__pht')].map(x => +x.dataset.pid)) });
  }); }
  $(app).on('click', '[data-act="photo"]', function () {
    const tr = this.closest('tr'), p = prod(tr.dataset.id);
    // несколько фото: отмечайте по порядку — первое станет главным, остальные листаются в карточке на сайте
    const fr = wp.media({ title: 'Фото товара «' + p.title + '» — можно несколько, первое главное', multiple: 'add', library: { type: 'image' }, button: { text: 'Поставить' } });
    fr.on('select', () => savePhotos(p, fr.state().get('selection').map(a => a.id)));
    // в окне: текущие фото товара отмечены и стоят первыми в медиатеке (внизу окна — их миниатюры по порядку)
    fr.on('open', () => {
      const ids = (p.pids && p.pids.length) ? p.pids : (p.tid ? [p.tid] : []);
      if (!ids.length) return;
      const atts = ids.map(id => wp.media.attachment(id));
      fr.state().get('selection').reset(atts);
      fr.content.mode('browse');
      const lib = fr.state().get('library');
      const base = lib.comparator;
      const pos = m => { const i = ids.indexOf(m.id); return i < 0 ? 1e9 : i; };
      lib.comparator = (a, b) => (pos(a) - pos(b)) || (base ? base.call(lib, a, b) : 0);
      Promise.all(atts.map(a => a.fetch())).then(() => { lib.add(atts); lib.sort(); });   // старые фото могли ещё не загрузиться в список — добавляем
    });
    fr.open();
  });

  /* ---------- всплывающие окна: категории и действия ---------- */
  let pop = null;
  const closePop = () => { if (pop) { pop.remove(); pop = null; } };
  const openPop = (anchor, html) => {
    closePop();
    pop = document.createElement('div'); pop.className = 'mcm__pop'; pop.innerHTML = html;
    document.body.appendChild(pop);
    const r = anchor.getBoundingClientRect();
    pop.style.top = (window.scrollY + r.bottom + 4) + 'px';
    pop.style.left = Math.max(8, Math.min(window.scrollX + r.left, window.scrollX + document.documentElement.clientWidth - pop.offsetWidth - 8)) + 'px';
    return pop;
  };
  document.addEventListener('click', e => { if (pop && !pop.contains(e.target) && !e.target.closest('[data-act="cats"],[data-act="menu"],[data-act="prices"],[data-bulk="cats"]')) closePop(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closePop(); });

  $(app).on('click', '[data-act="cats"]', function () {
    const tr = this.closest('tr'), p = prod(tr.dataset.id), btn = this;
    const html = `<p class="mcm__poph">Категории товара</p>
      <div class="mcm__catlist">${D.cats.length ? D.cats.map(c => `<label><input type="checkbox" value="${c.id}"${p.cats.includes(c.id) ? ' checked' : ''}> ${esc(c.name)}</label>`).join('') : '<em>У типа пока нет категорий</em>'}</div>
      ${memCM.canManage ? '<input type="text" class="mcm__newcat" placeholder="+ новая категория, Enter">' : ''}`;
    const el = openPop(btn, html);
    const apply = () => {
      p.cats = [...el.querySelectorAll('.mcm__catlist input:checked')].map(i => +i.value);
      btn.innerHTML = catChips(p);
      track(post('mem_cm_save', { id: p.id, f: 'cats', v: JSON.stringify(p.cats), type: D.id }));
    };
    el.addEventListener('change', e => { if (e.target.matches('.mcm__catlist input')) apply(); });
    el.querySelector('.mcm__newcat')?.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || !this.value.trim()) return;
      e.preventDefault();
      const name = this.value.trim(); this.disabled = true;
      track(post('mem_cm_term', { name, parent: D.id })).done(r => {
        if (!r.success) { this.disabled = false; return; }
        D.cats.push({ id: r.data.id, name });
        const lab = document.createElement('label');
        lab.innerHTML = `<input type="checkbox" value="${r.data.id}" checked> ${esc(name)}`;
        const list = el.querySelector('.mcm__catlist'); list.querySelector('em')?.remove(); list.appendChild(lab);
        this.value = ''; this.disabled = false; this.focus();
        apply();
      });
    });
  });

  $(app).on('click', '[data-act="menu"]', function () {
    const tr = this.closest('tr'), p = prod(tr.dataset.id);
    const el = openPop(this, `
      ${p.view ? `<a href="${esc(p.view)}" target="_blank">Открыть на сайте ↗</a>` : ''}
      <button type="button" data-do="copy">Сделать копию</button>
      <button type="button" data-do="status">${p.status === 'publish' ? 'Скрыть с сайта' : 'Показать на сайте'}</button>
      <button type="button" data-do="trash" class="is-danger">Удалить</button>`);
    el.addEventListener('click', e => {
      const b = e.target.closest('[data-do]'); if (!b) return;
      closePop();
      if (b.dataset.do === 'copy') track(post('mem_cm_copy', { id: p.id, type: D.id })).done(r => {
        if (!r.success) return;
        D.products.splice(D.products.indexOf(p) + 1, 0, r.data); render();
        app.querySelector(`tr[data-id="${r.data.id}"]`)?.classList.add('is-new');
      });
      if (b.dataset.do === 'status') {
        p.status = p.status === 'publish' ? 'draft' : 'publish';
        track(post('mem_cm_save', { id: p.id, f: 'status', v: p.status })); render();
      }
      if (b.dataset.do === 'trash') {
        if (!confirm('Удалить «' + p.title + '»? Его можно будет вернуть из корзины в классическом списке.')) return;
        track(post('mem_cm_trash', { id: p.id })).done(r => { if (r.success) { D.products.splice(D.products.indexOf(p), 1); render(); } });
      }
    });
  });

  /* ---------- отметки и действия с отмеченными ---------- */
  const bulkBar = () => {
    const b = app.querySelector('.mcm__bulk'); if (!b) return;
    b.hidden = !sel.size; b.querySelector('.mcm__bulkn').textContent = sel.size;
    const all = app.querySelector('.mcm__selall'); if (all) { const v = visible(); all.checked = v.length > 0 && v.every(p => sel.has(p.id)); }
  };
  let lastIdx = null;
  $(app).on('click', '.mcm__sel', function (e) {
    const tr = this.closest('tr'), id = +tr.dataset.id;
    const rows = [...app.querySelectorAll('tbody tr[data-id]')], idx = rows.indexOf(tr);
    if (e.shiftKey && lastIdx !== null) { // Shift — отметить диапазон
      const [a, b] = [Math.min(lastIdx, idx), Math.max(lastIdx, idx)];
      rows.slice(a, b + 1).forEach(r => { r.querySelector('.mcm__sel').checked = this.checked; this.checked ? sel.add(+r.dataset.id) : sel.delete(+r.dataset.id); r.classList.toggle('is-sel', this.checked); });
    } else { this.checked ? sel.add(id) : sel.delete(id); tr.classList.toggle('is-sel', this.checked); }
    lastIdx = idx; bulkBar();
  });
  $(app).on('change', '.mcm__selall', function () {
    visible().forEach(p => this.checked ? sel.add(p.id) : sel.delete(p.id));
    app.querySelectorAll('tbody tr[data-id]').forEach(tr => { tr.querySelector('.mcm__sel').checked = this.checked; tr.classList.toggle('is-sel', this.checked); });
    bulkBar();
  });
  const bulk = (op, extra = {}) => track(post('mem_cm_bulk', Object.assign({ type: D.id, op, ids: [...sel] }, extra))).done(r => {
    if (!r.success) return;
    r.data.products.forEach(np => { const i = D.products.findIndex(p => p.id === np.id); if (i >= 0) D.products[i] = np; });
    if (r.data.trashed.length) { D.products = D.products.filter(p => !r.data.trashed.includes(p.id)); sel.clear(); }
    render();
  });
  $(app).on('click', '[data-bulk]', function () {
    const op = this.dataset.bulk;
    if (op === 'none') { sel.clear(); render(); return; }
    if (op === 'trash') { if (confirm('Удалить отмеченные товары (' + sel.size + ')? Их можно будет вернуть из корзины в классическом списке.')) bulk('trash'); return; }
    if (op === 'draft' || op === 'publish') return bulk(op);
    if (op === 'merge') {
      // главный — первый из отмеченных по порядку в таблице
      const list = D.products.filter(p => sel.has(p.id));
      if (list.length < 2) return alert('Отметьте хотя бы два товара.');
      const main = list[0];
      if (!confirm(`Объединить ${list.length} ${list.length < 5 ? 'товара' : 'товаров'} в «${main.title}»?\n\nФото всех соберутся в нём по порядку, недостающие цена, подпись и характеристики возьмутся у остальных. Остальные товары уйдут в корзину (их можно вернуть).`)) return;
      track(post('mem_cm_merge', { type: D.id, ids: list.map(p => p.id) })).done(r => {
        if (!r.success) return alert(r.data || 'Не получилось');
        if (r.data.conflicts) return alert('Не объединено — у товаров разные значения:\n\n• ' + r.data.conflicts.join('\n• ') + '\n\nИсправьте, чтобы совпадали (или сотрите у лишних), и объедините снова.');
        const i = D.products.findIndex(p => p.id === r.data.product.id);
        if (i >= 0) D.products[i] = r.data.product;
        D.products = D.products.filter(p => !r.data.trashed.includes(p.id));
        sel.clear(); render();
      });
      return;
    }
    if (op === 'cats') {
      const el = openPop(this, `<p class="mcm__poph">Категории для отмеченных (${sel.size})</p>
        <div class="mcm__catlist">${D.cats.length ? D.cats.map(c => `<label><input type="checkbox" value="${c.id}"> ${esc(c.name)}</label>`).join('') : '<em>У типа пока нет категорий</em>'}</div>
        <div class="mcm__popbtns"><button type="button" class="button button-primary" data-cop="cats_add">Добавить в них</button> <button type="button" class="button" data-cop="cats_remove">Убрать из них</button></div>`);
      el.addEventListener('click', e => {
        const b = e.target.closest('[data-cop]'); if (!b) return;
        const cats = [...el.querySelectorAll('input:checked')].map(i => +i.value);
        if (!cats.length) return alert('Отметьте категории.');
        closePop(); bulk(b.dataset.cop, { cats: JSON.stringify(cats) });
      });
    }
  });

  /* ---------- изменить цены: отмеченным или всем показанным ---------- */
  $(app).on('click', '[data-act="prices"]', function () {
    const targets = sel.size ? D.products.filter(p => sel.has(p.id)) : visible();
    if (!targets.length) return;
    const who = sel.size ? `отмеченных (${targets.length})` : (filterCat || query ? `показанных (${targets.length})` : `всех «${D.name}» (${targets.length})`);
    const el = openPop(this, `<p class="mcm__poph">Цены ${esc(who)}</p>
      <div class="mcm__pricef">
        <select class="mcm__pmode"><option value="pct">Изменить на %</option><option value="add">Прибавить / отнять ₽</option><option value="set">Поставить цену ₽</option></select>
        <input type="text" inputmode="decimal" class="mcm__pval" placeholder="например: 10 или -5">
        <label>Округлить до <select class="mcm__pround"><option value="1">1 ₽</option><option value="10">10 ₽</option><option value="50" selected>50 ₽</option><option value="100">100 ₽</option></select></label>
      </div>
      <p class="mcm__pprev description">Впишите число — здесь появится пример.</p>
      <p class="description">Товары с ценой «по запросу» при наценке не меняются.</p>
      <div class="mcm__popbtns"><button type="button" class="button button-primary" data-papply>Применить</button></div>`);
    const calc = (cur, mode, v, r) => { let n = mode === 'pct' ? cur * (1 + v / 100) : mode === 'add' ? cur + v : v; return Math.max(0, Math.round(n / r) * r); };
    const prev = () => {
      const mode = el.querySelector('.mcm__pmode').value, v = parseFloat(el.querySelector('.mcm__pval').value.replace(',', '.')), r = +el.querySelector('.mcm__pround').value;
      const ex = targets.filter(p => p.price || mode === 'set').slice(0, 3);
      el.querySelector('.mcm__pprev').textContent = isNaN(v) ? 'Впишите число — здесь появится пример.' :
        'Например: ' + ex.map(p => `${p.title}: ${p.price ? p.price.toLocaleString('ru-RU') : '—'} → ${calc(+p.price || 0, mode, v, r).toLocaleString('ru-RU')} ₽`).join('; ');
    };
    el.addEventListener('input', prev); el.addEventListener('change', prev);
    el.querySelector('.mcm__pval').focus();
    el.querySelector('[data-papply]').addEventListener('click', () => {
      const mode = el.querySelector('.mcm__pmode').value, raw = el.querySelector('.mcm__pval').value.replace(',', '.');
      if (isNaN(parseFloat(raw))) return el.querySelector('.mcm__pval').focus();
      if (!confirm('Изменить цены ' + who + '?')) return;
      closePop();
      track(post('mem_cm_bulk', { type: D.id, op: 'price', ids: targets.map(p => p.id), mode, value: raw, round: el.querySelector('.mcm__pround').value })).done(r => {
        if (!r.success) return;
        r.data.products.forEach(np => { const i = D.products.findIndex(p => p.id === np.id); if (i >= 0) D.products[i] = np; });
        render();
      });
    });
  });

  /* ---------- новый тип ---------- */
  $(root).on('click', '[data-act="new-type"]', function () {
    if (!memCM.canManage) return alert('Создавать типы может администратор.');
    const btn = this;
    const inp = document.createElement('input'); inp.type = 'text'; inp.className = 'mcm__typeinp'; inp.placeholder = 'Название типа, Enter';
    btn.replaceWith(inp); inp.focus();
    inp.addEventListener('keydown', e => {
      if (e.key === 'Escape') inp.replaceWith(btn);
      if (e.key !== 'Enter' || !inp.value.trim()) return;
      inp.disabled = true;
      post('mem_cm_term', { name: inp.value.trim(), parent: 0 }).done(r => { if (r.success) location.href = r.data.url; else { inp.disabled = false; alert(r.data || 'Не получилось'); } });
    });
  });

  // предупреждение, если закрывают страницу во время сохранения
  window.addEventListener('beforeunload', e => { if (pending) { e.preventDefault(); e.returnValue = ''; } });

  if (D) render();
});
