/* «Наши работы → Работы» и «Виды работ»: правка на месте, всё сохраняется само */
jQuery(function ($) {
  if (!window.memWM) return;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const post = (action, data) => $.post(memWM.ajax, Object.assign({ action, _ajax_nonce: memWM.nonce }, data));
  const rub = n => (+n || 0).toLocaleString('ru-RU') + ' ₽';
  const word = (n, a, b, c) => { n = Math.abs(n) % 100; const d = n % 10; return n > 10 && n < 20 ? c : d === 1 ? a : d >= 2 && d <= 4 ? b : c; };
  let D = memWM.data;
  const root = document.querySelector(memWM.screen === 'works' ? '#mwm' : '#mwt');
  const app = root && root.querySelector(memWM.screen === 'works' ? '.mwm__app' : '.mwt__app');
  if (!app) return;

  let pending = 0, failed = false;
  const status = () => {
    const el = root.querySelector('.mcm__status'); if (!el) return;
    el.className = 'mcm__status' + (failed ? ' is-err' : pending ? ' is-busy' : ' is-ok');
    el.textContent = failed ? 'Не всё сохранилось — проверьте и повторите' : pending ? 'Сохраняю…' : 'Все изменения сохранены';
  };
  const track = xhr => { pending++; failed = false; status(); return xhr.done(r => { if (!r || !r.success) failed = true; }).fail(() => { failed = true; }).always(() => { pending--; status(); }); };
  const later = (() => { const t = {}; return (k, fn, ms = 600) => { clearTimeout(t[k]); t[k] = setTimeout(fn, ms); }; })();
  window.addEventListener('beforeunload', e => { if (pending) { e.preventDefault(); e.returnValue = ''; } });

  /* всплывающее окно */
  let pop = null;
  const closePop = () => { if (pop) { pop.remove(); pop = null; } };
  const openPop = (anchor, html, cls = '') => {
    closePop();
    pop = document.createElement('div'); pop.className = 'mcm__pop ' + cls; pop.innerHTML = html;
    document.body.appendChild(pop);
    const r = anchor.getBoundingClientRect();
    pop.style.top = (window.scrollY + r.bottom + 4) + 'px';
    pop.style.left = Math.max(8, Math.min(window.scrollX + r.left, window.scrollX + document.documentElement.clientWidth - pop.offsetWidth - 8)) + 'px';
    return pop;
  };
  document.addEventListener('mousedown', e => { if (pop && !pop.contains(e.target) && !e.target.closest('[data-pop]') && !e.target.closest('.media-modal')) closePop(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closePop(); });

  /* ================= Виды работ ================= */
  if (memWM.screen === 'kinds') {
    const draw = () => {
      app.innerHTML = `
        <div class="mcm__bar"><a class="button button-primary" href="${esc(memWM.worksUrl)}">Все работы →</a><span class="mcm__status is-ok">Все изменения сохранены</span></div>
        <section class="mct__card" style="max-width:760px">
          <h2>Виды работ</h2>
          <p class="description">На странице «Наши работы» это кнопки-фильтры над списком. Перетаскивайте, чтобы поменять порядок; название правится прямо в строке.</p>
          <ul class="mct__cats">${D.kinds.map(k => `<li data-id="${k.id}"><span class="mcm__drag">⋮⋮</span><input type="text" class="mct__cname" value="${esc(k.name)}"><span class="mct__ccount">${k.count} ${word(k.count, 'работа', 'работы', 'работ')}</span><button type="button" class="mcm__dots mct__cdel" title="Удалить">×</button></li>`).join('') || '<li class="mcm__none">Пока нет видов работ</li>'}</ul>
          <p class="mct__add"><input type="text" class="mct__cnew" placeholder="Новый вид работ, например «Укладка плитки»"> <button type="button" class="button" data-add>Добавить</button></p>
        </section>`;
      $(app.querySelector('.mct__cats')).sortable({ handle: '.mcm__drag', axis: 'y', items: '> li[data-id]', update: function () {
        track(post('mem_wt', { op: 'order', ids: [...this.querySelectorAll('li[data-id]')].map(li => +li.dataset.id) }));
      } });
    };
    const add = () => {
      const inp = app.querySelector('.mct__cnew'), name = inp.value.trim(); if (!name) return inp.focus();
      track(post('mem_wt', { op: 'add', name })).done(r => { if (r.success) { D.kinds = r.data.kinds; draw(); app.querySelector('.mct__cnew').focus(); } });
    };
    $(app).on('click', '[data-add]', add);
    $(app).on('keydown', '.mct__cnew', e => { if (e.key === 'Enter') { e.preventDefault(); add(); } });
    $(app).on('input', '.mct__cname', function () {
      const id = this.closest('li').dataset.id, name = this.value.trim(); if (!name) return;
      later('k' + id, () => track(post('mem_wt', { op: 'rename', id, name })));
    });
    $(app).on('click', '.mct__cdel', function () {
      const k = D.kinds.find(x => x.id === +this.closest('li').dataset.id);
      if (!confirm('Удалить вид работ «' + k.name + '»?' + (k.count ? ' Сами работы (' + k.count + ') останутся.' : ''))) return;
      track(post('mem_wt', { op: 'delete', id: k.id })).done(r => { if (r.success) { D.kinds = r.data.kinds; draw(); } });
    });
    draw();
    return;
  }

  /* ================= Работы ================= */
  let filter = 0, query = '';
  const sel = new Set();
  const work = id => D.works.find(w => w.id === +id);
  const total = w => w.rows.reduce((s, r) => s + (+r[1] || 0), 0);
  const filled = w => w.rows.filter(r => String(r[0]).trim() || r[1]).length;
  const kindChips = w => {
    const n = w.kinds.map(id => (D.kinds.find(k => k.id === id) || {}).name).filter(Boolean);
    return n.length ? n.map(x => `<span class="mcm__chip">${esc(x)}</span>`).join('') : '<span class="mcm__none">без вида</span>';
  };
  const visible = () => D.works.filter(w => (!filter || w.kinds.includes(filter)) &&
    (!query || (w.title + ' ' + w.size + ' ' + w.text + ' ' + w.rows.map(r => r[0]).join(' ')).toLowerCase().includes(query)));

  const row = w => {
    const t = total(w), main = w.photos[0];
    return `
    <tr data-id="${w.id}" class="${w.status !== 'publish' ? 'is-hidden' : ''}${sel.has(w.id) ? ' is-sel' : ''}">
      <td class="mcm__drag" title="Перетащите, чтобы поменять порядок">⋮⋮</td>
      <td class="mcm__chk"><input type="checkbox" class="mcm__sel" aria-label="Отметить"${sel.has(w.id) ? ' checked' : ''}></td>
      <td class="mcm__ph"><button type="button" class="mcm__photo" data-pop="photos" title="Фото работы">${main ? `<img src="${esc(main.thumb)}" alt="">` : '<span>+ фото</span>'}${w.photos.length > 1 ? `<b class="mwm__more">+${w.photos.length - 1}</b>` : ''}</button></td>
      <td class="mcm__name">
        <input type="text" data-f="title" value="${esc(w.title)}" placeholder="Название работы" aria-label="Название">
        <button type="button" class="mwm__textbtn" data-pop="text">${w.text.trim() ? esc(w.text.replace(/<[^>]+>/g, '').slice(0, 70)) + (w.text.length > 70 ? '…' : '') : '+ описание'}</button>
        ${w.status !== 'publish' ? '<span class="mcm__badge">скрыта</span>' : ''}
        ${w.ex ? '<span class="mcm__badge mwm__ex" title="Показывается в карточках «Примеры наших работ»">★ в примерах</span>' : ''}
      </td>
      <td class="mwm__year"><input type="number" data-f="year" value="${esc(w.year)}" min="1990" max="${new Date().getFullYear() + 1}" placeholder="год" aria-label="Год"></td>
      <td class="mwm__size"><input type="text" data-f="size" value="${esc(w.size)}" placeholder="2,5 × 2 м" aria-label="Размер участка"></td>
      <td class="mwm__rows"><button type="button" class="mcm__catbtn" data-pop="rows">${filled(w) ? `${filled(w)} ${word(filled(w), 'позиция', 'позиции', 'позиций')} · <b>${rub(t)}</b>` : '<span class="mcm__none">+ что сделано и цены</span>'}</button></td>
      <td class="mcm__cats"><button type="button" class="mcm__catbtn" data-pop="kinds">${kindChips(w)}</button></td>
      <td class="mcm__more"><button type="button" class="mcm__dots" data-pop="menu" aria-label="Действия">⋯</button></td>
    </tr>`;
  };

  function render() {
    const list = visible();
    app.innerHTML = `
      <div class="mcm__drop" tabindex="0" role="button" aria-label="Новая работа из фото">
        <input type="file" accept="image/*" multiple hidden>
        <strong>Перетащите сюда фото одной работы</strong>
        <span>или <u>выберите на компьютере</u> · можно несколько фото сразу · <button type="button" class="button-link" data-act="lib">из медиатеки</button> · <button type="button" class="button-link" data-act="blank">работа без фото</button></span>
        <em>Все фото станут одной новой работой (первое — главное). Останется вписать название, размер и цены.</em>
        <div class="mcm__queue"></div>
      </div>
      <div class="mcm__bar">
        <input type="search" class="mcm__search" placeholder="Поиск по названию, размеру, составу" value="${esc(query)}">
        <div class="mcm__filters">
          <button type="button" class="mcm__f${!filter ? ' is-on' : ''}" data-kind="0">Все <b>${D.works.length}</b></button>
          ${D.kinds.map(k => `<button type="button" class="mcm__f${filter === k.id ? ' is-on' : ''}" data-kind="${k.id}">${esc(k.name)} <b>${D.works.filter(w => w.kinds.includes(k.id)).length}</b></button>`).join('')}
        </div>
        <a class="button" href="${esc(memWM.kindsUrl)}">⚙ Виды работ</a>
        <span class="mcm__status is-ok">Все изменения сохранены</span>
      </div>
      <div class="mcm__bulk"${sel.size ? '' : ' hidden'}>
        <b>Отмечено: <span class="mcm__bulkn">${sel.size}</span></b>
        <button type="button" class="button" data-pop="bulkkinds">Виды работ…</button>
        <button type="button" class="button" data-bulk="draft">Скрыть с сайта</button>
        <button type="button" class="button" data-bulk="publish">Показать на сайте</button>
        <button type="button" class="button mcm__danger" data-bulk="trash">Удалить</button>
        <button type="button" class="button-link" data-bulk="none">снять отметку</button>
      </div>
      ${D.works.length ? `
      <div class="mcm__wrap"><table class="mcm__table">
        <thead><tr><th></th><th class="mcm__chk"><input type="checkbox" class="mcm__selall" aria-label="Отметить все"${list.length && list.every(w => sel.has(w.id)) ? ' checked' : ''}></th>
          <th>Фото</th><th>Название и описание</th><th>Год</th><th>Размер участка</th><th>Что сделано и цены</th><th>Виды работ</th><th></th></tr></thead>
        <tbody>${list.map(row).join('')}</tbody></table></div>
        ${list.length ? '' : '<p class="mcm__empty">Ничего не найдено.</p>'}` : '<p class="mcm__empty">Пока нет ни одной работы — перетащите фото в рамку выше.</p>'}`;
    const sortable = !filter && !query, tb = app.querySelector('tbody');
    if (tb && sortable) $(tb).sortable({ handle: '.mcm__drag', axis: 'y', helper: (e, tr) => { tr.children().each(function () { $(this).width($(this).width()); }); return tr; },
      update: () => { const ids = [...tb.children].map(tr => +tr.dataset.id); D.works.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id)); track(post('mem_wm_order', { ids })); } });
    root.classList.toggle('no-sort', !sortable);
  }
  const replace = nw => { const i = D.works.findIndex(w => w.id === nw.id); if (i >= 0) D.works[i] = nw; };
  const save = (w, f, v) => track(post('mem_wm_save', { id: w.id, f, v }));

  /* поля в строке */
  $(app).on('input', 'tbody [data-f]', function () {
    const w = work(this.closest('tr').dataset.id), f = this.dataset.f, v = this.value;
    w[f] = v; later(w.id + f, () => save(w, f, v.trim()));
  });
  $(app).on('keydown', 'tbody input[data-f]', function (e) {
    if (e.key !== 'Enter') return; e.preventDefault();
    const tr = this.closest('tr').nextElementSibling, n = tr && tr.querySelector(`[data-f="${this.dataset.f}"]`);
    n ? (n.focus(), n.select && n.select()) : this.blur();
  });

  /* поиск, фильтр, отметки */
  $(app).on('input', '.mcm__search', function () { query = this.value.trim().toLowerCase(); sel.clear(); const p = this.selectionStart; render(); const s = app.querySelector('.mcm__search'); s.focus(); s.setSelectionRange(p, p); });
  $(app).on('click', '.mcm__f', function () { filter = +this.dataset.kind; sel.clear(); render(); });
  const bulkBar = () => { const b = app.querySelector('.mcm__bulk'); b.hidden = !sel.size; b.querySelector('.mcm__bulkn').textContent = sel.size; };
  let lastIdx = null;
  $(app).on('click', '.mcm__sel', function (e) {
    const tr = this.closest('tr'), rows = [...app.querySelectorAll('tbody tr')], idx = rows.indexOf(tr);
    const range = e.shiftKey && lastIdx !== null ? rows.slice(Math.min(lastIdx, idx), Math.max(lastIdx, idx) + 1) : [tr];
    range.forEach(r => { r.querySelector('.mcm__sel').checked = this.checked; this.checked ? sel.add(+r.dataset.id) : sel.delete(+r.dataset.id); r.classList.toggle('is-sel', this.checked); });
    lastIdx = idx; bulkBar();
  });
  $(app).on('change', '.mcm__selall', function () {
    visible().forEach(w => this.checked ? sel.add(w.id) : sel.delete(w.id));
    app.querySelectorAll('tbody tr').forEach(tr => { tr.querySelector('.mcm__sel').checked = this.checked; tr.classList.toggle('is-sel', this.checked); });
    bulkBar();
  });
  const bulk = (op, extra = {}) => track(post('mem_wm_bulk', Object.assign({ op, ids: [...sel] }, extra))).done(r => {
    if (!r.success) return;
    r.data.works.forEach(replace); D.kinds = r.data.kinds;
    if (r.data.trashed.length) { D.works = D.works.filter(w => !r.data.trashed.includes(w.id)); sel.clear(); }
    render();
  });
  $(app).on('click', '[data-bulk]', function () {
    const op = this.dataset.bulk;
    if (op === 'none') { sel.clear(); return render(); }
    if (op === 'trash' && !confirm('Удалить отмеченные работы (' + sel.size + ')? Их можно будет вернуть из корзины в классическом списке.')) return;
    bulk(op);
  });

  /* загрузка фото → одна новая работа */
  function addWork(w) {
    D.works.push(w); filter = 0; query = ''; render();
    const tr = app.querySelector(`tr[data-id="${w.id}"]`);
    if (tr) { tr.classList.add('is-new'); tr.scrollIntoView({ block: 'center' }); const t = tr.querySelector('[data-f="title"]'); t.focus(); t.select(); }
  }
  async function uploadFiles(files, onDone) {
    files = [...files].filter(f => /^image\//.test(f.type));
    if (!files.length) return;
    const q = app.querySelector('.mcm__queue'), ids = [];
    q.innerHTML = files.map((f, i) => `<span data-i="${i}">${esc(f.name)} — ждёт</span>`).join('');
    for (let i = 0; i < files.length; i++) {
      const el = q.querySelector(`[data-i="${i}"]`);
      if (files[i].size > memWM.maxUpload) { el.textContent = files[i].name + ' — слишком большой файл'; el.className = 'is-err'; continue; }
      el.textContent = files[i].name + ' — загружаю…';
      const fd = new FormData(); fd.append('action', 'mem_wm_upload'); fd.append('_ajax_nonce', memWM.nonce); fd.append('file', files[i]);
      pending++; status();
      try {
        const r = await (await fetch(memWM.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })).json();
        if (r && r.success) { ids.push(r.data); el.textContent = files[i].name + ' — готово'; el.className = 'is-ok'; }
        else { el.textContent = files[i].name + ' — ' + ((r && r.data) || 'ошибка'); el.className = 'is-err'; }
      } catch (e) { el.textContent = files[i].name + ' — нет связи'; el.className = 'is-err'; }
      pending--; status();
    }
    if (ids.length) onDone(ids);
  }
  $(app).on('click', '.mcm__drop', function (e) { if (!e.target.closest('[data-act], input')) this.querySelector('input[type=file]').click(); });
  $(app).on('keydown', '.mcm__drop', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.querySelector('input[type=file]').click(); } });
  const fromAtts = atts => track(post('mem_wm_new', { atts: atts.map(a => a.id), kind: filter })).done(r => { if (r.success) addWork(r.data); });
  $(app).on('change', '.mcm__drop input[type=file]', function () { uploadFiles(this.files, fromAtts); this.value = ''; });
  $(app).on('dragover dragenter', '.mcm__drop', function (e) { e.preventDefault(); this.classList.add('is-over'); });
  $(app).on('dragleave drop', '.mcm__drop', function (e) { e.preventDefault(); this.classList.remove('is-over'); if (e.type === 'drop') uploadFiles(e.originalEvent.dataTransfer.files, fromAtts); });
  window.addEventListener('dragover', e => e.preventDefault());
  window.addEventListener('drop', e => { if (!e.target.closest('.mcm__drop, .mwm__phdrop')) e.preventDefault(); });
  const pickMedia = (title, btn, cb) => {
    const fr = wp.media({ title, multiple: 'add', library: { type: 'image' }, button: { text: btn } });
    fr.on('select', () => cb(fr.state().get('selection').map(a => ({ id: a.id, thumb: (a.get('sizes') && a.get('sizes').thumbnail || a.toJSON()).url }))));
    fr.open();
  };
  $(app).on('click', '[data-act="lib"]', () => pickMedia('Фото новой работы', 'Создать работу', fromAtts));
  $(app).on('click', '[data-act="blank"]', () => fromAtts([]));

  /* всплывающие окна в строке */
  $(app).on('click', '[data-pop]', function () {
    const kind = this.dataset.pop, tr = this.closest('tr'), w = tr && work(tr.dataset.id), btn = this;
    if (kind === 'photos') {
      const draw = el => {
        el.innerHTML = `<p class="mcm__poph">Фото работы — первое главное. Перетаскивайте, чтобы поменять порядок</p>
          <ul class="mwm__phlist">${w.photos.map(p => `<li data-id="${p.id}"><img src="${esc(p.thumb)}" alt=""><button type="button" aria-label="Убрать">×</button></li>`).join('')}</ul>
          <div class="mwm__phdrop">Перетащите фото сюда или <button type="button" class="button-link" data-ph="add">выберите</button></div>`;
        $(el.querySelector('.mwm__phlist')).sortable({ update: function () {
          const order = [...this.children].map(li => +li.dataset.id);
          w.photos.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id)); savePhotos();
        } });
      };
      const savePhotos = () => { tr.querySelector('.mcm__photo').outerHTML = row(w).match(/<button type="button" class="mcm__photo"[\s\S]*?<\/button>/)[0]; save(w, 'photos', JSON.stringify(w.photos.map(p => p.id))); };
      const el = openPop(btn, '', 'mwm__phpop'); draw(el);
      el.addEventListener('click', e => {
        const x = e.target.closest('.mwm__phlist button');
        if (x) { const id = +x.closest('li').dataset.id; w.photos = w.photos.filter(p => p.id !== id); draw(el); savePhotos(); }
        if (e.target.closest('[data-ph="add"]')) pickMedia('Добавить фото к работе', 'Добавить', atts => { w.photos.push(...atts); draw(el); savePhotos(); });
      });
      el.addEventListener('dragover', e => { e.preventDefault(); el.querySelector('.mwm__phdrop').classList.add('is-over'); });
      el.addEventListener('drop', e => { e.preventDefault(); uploadFiles(e.dataTransfer.files, atts => { w.photos.push(...atts); draw(el); savePhotos(); }); });
    }
    if (kind === 'text') {
      const el = openPop(btn, `<p class="mcm__poph">Описание работы: что хотели заказчики, что сделали</p><textarea class="mwm__text" rows="7">${esc(w.text)}</textarea>`, 'mwm__wide');
      const ta = el.querySelector('textarea'); ta.focus();
      ta.addEventListener('input', () => { w.text = ta.value; later('t' + w.id, () => save(w, 'text', w.text)); btn.textContent = w.text.trim() ? w.text.slice(0, 70) + (w.text.length > 70 ? '…' : '') : '+ описание'; });
    }
    if (kind === 'rows') {
      const draw = el => {
        el.innerHTML = `<p class="mcm__poph">Что сделано и стоимость — итог считается сам</p>
          <table class="mwm__rtable">${w.rows.map((r, i) => `<tr data-i="${i}"><td><input type="text" class="mwm__rn" value="${esc(r[0])}" placeholder="Что сделано"></td><td><span class="mcm__num"><input type="text" inputmode="numeric" class="mwm__rp" value="${esc(r[1])}" placeholder="0"><i>₽</i></span></td><td><button type="button" class="mcm__dots" data-rdel aria-label="Удалить строку">×</button></td></tr>`).join('')}</table>
          <p class="mwm__rfoot"><button type="button" class="button" data-radd>+ Строка</button> <span>Итого: <b class="mwm__rt">${rub(total(w))}</b></span></p>`;
      };
      const saveRows = () => {
        btn.innerHTML = filled(w) ? `${filled(w)} ${word(filled(w), 'позиция', 'позиции', 'позиций')} · <b>${rub(total(w))}</b>` : '<span class="mcm__none">+ что сделано и цены</span>';
        pop.querySelector('.mwm__rt').textContent = rub(total(w));
        later('r' + w.id, () => save(w, 'rows', JSON.stringify(w.rows)));
      };
      if (!w.rows.length) w.rows.push(['', '']);
      const el = openPop(btn, '', 'mwm__wide'); draw(el); el.querySelector('.mwm__rn').focus();
      el.addEventListener('input', e => {
        const r = e.target.closest('tr'); if (!r) return;
        const i = +r.dataset.i;
        if (e.target.matches('.mwm__rn')) w.rows[i][0] = e.target.value;
        if (e.target.matches('.mwm__rp')) { e.target.value = e.target.value.replace(/\D/g, ''); w.rows[i][1] = e.target.value ? +e.target.value : ''; }
        saveRows();
      });
      el.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('input')) { e.preventDefault(); el.querySelector('[data-radd]').click(); } });
      el.addEventListener('click', e => {
        if (e.target.closest('[data-radd]')) { w.rows.push(['', '']); draw(el); const n = el.querySelectorAll('.mwm__rn'); n[n.length - 1].focus(); }
        const d = e.target.closest('[data-rdel]'); if (d) { w.rows.splice(+d.closest('tr').dataset.i, 1); draw(el); saveRows(); }
      });
    }
    if (kind === 'kinds' || kind === 'bulkkinds') {
      const isBulk = kind === 'bulkkinds';
      const el = openPop(btn, `<p class="mcm__poph">${isBulk ? 'Виды работ для отмеченных (' + sel.size + ')' : 'Виды работ'}</p>
        <div class="mcm__catlist">${D.kinds.map(k => `<label><input type="checkbox" value="${k.id}"${!isBulk && w.kinds.includes(k.id) ? ' checked' : ''}> ${esc(k.name)}</label>`).join('') || '<em>Пока нет видов работ</em>'}</div>
        ${memWM.canManage ? '<input type="text" class="mcm__newcat" placeholder="+ новый вид работ, Enter">' : ''}
        ${isBulk ? '<div class="mcm__popbtns"><button type="button" class="button button-primary" data-kop="kinds_add">Добавить</button> <button type="button" class="button" data-kop="kinds_remove">Убрать</button></div>' : ''}`);
      const apply = () => {
        if (isBulk) return;
        w.kinds = [...el.querySelectorAll('.mcm__catlist input:checked')].map(i => +i.value);
        btn.innerHTML = kindChips(w); save(w, 'kinds', JSON.stringify(w.kinds));
      };
      el.addEventListener('change', e => { if (e.target.matches('.mcm__catlist input')) apply(); });
      el.addEventListener('click', e => {
        const b = e.target.closest('[data-kop]'); if (!b) return;
        const kinds = [...el.querySelectorAll('.mcm__catlist input:checked')].map(i => +i.value);
        if (!kinds.length) return alert('Отметьте виды работ.');
        closePop(); bulk(b.dataset.kop, { kinds: JSON.stringify(kinds) });
      });
      el.querySelector('.mcm__newcat')?.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !this.value.trim()) return;
        e.preventDefault(); const name = this.value.trim(); this.disabled = true;
        track(post('mem_wt', { op: 'add', name })).done(r => {
          this.disabled = false; if (!r.success) return;
          D.kinds = r.data.kinds;
          const lab = document.createElement('label'); lab.innerHTML = `<input type="checkbox" value="${r.data.id}" checked> ${esc(name)}`;
          const list = el.querySelector('.mcm__catlist'); list.querySelector('em')?.remove(); list.appendChild(lab);
          this.value = ''; apply();
        });
      });
    }
    if (kind === 'menu') {
      const el = openPop(btn, `
        <a href="${esc(w.edit)}">Открыть в редакторе</a>
        <button type="button" data-do="copy">Сделать копию</button>
        <button type="button" data-do="ex">${w.ex ? 'Убрать из примеров' : 'Показывать в примерах'}</button>
        <button type="button" data-do="status">${w.status === 'publish' ? 'Скрыть с сайта' : 'Показать на сайте'}</button>
        <button type="button" data-do="trash" class="is-danger">Удалить</button>`);
      el.addEventListener('click', e => {
        const b = e.target.closest('[data-do]'); if (!b) return; closePop();
        if (b.dataset.do === 'copy') track(post('mem_wm_copy', { id: w.id })).done(r => { if (r.success) { D.works.splice(D.works.indexOf(w) + 1, 0, r.data); render(); app.querySelector(`tr[data-id="${r.data.id}"]`)?.classList.add('is-new'); } });
        if (b.dataset.do === 'ex') { w.ex = !w.ex; save(w, 'ex', w.ex ? '1' : '0'); render(); }
        if (b.dataset.do === 'status') { w.status = w.status === 'publish' ? 'draft' : 'publish'; save(w, 'status', w.status); render(); }
        if (b.dataset.do === 'trash' && confirm('Удалить «' + w.title + '»? Её можно будет вернуть из корзины в классическом списке.'))
          track(post('mem_wm_bulk', { op: 'trash', ids: [w.id] })).done(r => { if (r.success) { D.works = D.works.filter(x => x.id !== w.id); render(); } });
      });
    }
  });

  render();
});
