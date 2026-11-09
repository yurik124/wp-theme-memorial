/* «Каталог → Типы и категории»: настройки типа товара, всё сохраняется само */
jQuery(function ($) {
  const root = document.getElementById('mct');
  if (!root || !window.memCT) return;
  const app = root.querySelector('.mct__app');
  let D = memCT.data;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const post = (action, data) => $.post(memCT.ajax, Object.assign({ action, _ajax_nonce: memCT.nonce }, data));

  let pending = 0, failed = false;
  const status = () => {
    const el = root.querySelector('.mcm__status'); if (!el) return;
    el.className = 'mcm__status' + (failed ? ' is-err' : pending ? ' is-busy' : ' is-ok');
    el.textContent = failed ? 'Не всё сохранилось — проверьте и повторите' : pending ? 'Сохраняю…' : 'Все изменения сохранены';
  };
  const track = xhr => { pending++; failed = false; status(); return xhr.done(r => { if (!r || !r.success) { failed = true; if (r && r.data && typeof r.data === 'string') alert(r.data); } }).fail(() => { failed = true; }).always(() => { pending--; status(); }); };
  const later = (() => { const t = {}; return (k, fn, ms = 600) => { clearTimeout(t[k]); t[k] = setTimeout(fn, ms); }; })();

  /* ---------- отрисовка ---------- */
  let fields = [];
  const copyFields = () => { fields = D.fields.map(f => Object.assign({ orig: f.label }, f, { options: f.options.slice(), default: f.default || '' })); };

  const defInput = f => {
    if (f.type === 'list') return `<select class="mct__fdef"><option value="">— нет —</option>${f.options.map(o => `<option${o === f.default ? ' selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
    return `<span class="mcm__num"><input type="${f.type === 'number' ? 'number' : 'text'}" step="any" class="mct__fdef" value="${esc(f.default)}" placeholder="— нет —">${f.type === 'number' && f.unit ? `<i>${esc(f.unit)}</i>` : ''}</span>`;
  };
  const fieldRow = (f, i) => `
    <tr data-i="${i}">
      <td class="mcm__drag" title="Перетащите, чтобы поменять порядок">⋮⋮</td>
      <td><input type="text" class="mct__flabel" value="${esc(f.label)}" placeholder="Например: Высота"></td>
      <td><select class="mct__ftype">
        <option value="text"${f.type === 'text' ? ' selected' : ''}>Текст</option>
        <option value="number"${f.type === 'number' ? ' selected' : ''}>Число</option>
        <option value="list"${f.type === 'list' ? ' selected' : ''}>Выбор из списка</option></select></td>
      <td>${f.type === 'number' ? `<input type="text" class="mct__funit" value="${esc(f.unit)}" placeholder="см, мм, кг">`
          : f.type === 'list' ? `<input type="text" class="mct__fopts" value="${esc(f.options.join(', '))}" placeholder="варианты через запятую">` : '<span class="mcm__none">—</span>'}</td>
      <td>${defInput(f)}</td>
      <td><button type="button" class="mcm__dots mct__fdel" aria-label="Удалить характеристику" title="Удалить">×</button></td>
    </tr>`;
  const catRow = c => `
    <li data-id="${c.id}" style="--d:${c.depth}">
      <span class="mcm__drag" title="Перетащите">⋮⋮</span>
      <input type="text" class="mct__cname" value="${esc(c.name)}" aria-label="Название категории">
      <a class="mct__ccount" href="${esc(D.products)}&cat=${c.id}" title="Открыть товары этой категории">${c.count} ${c.count % 10 === 1 && c.count % 100 !== 11 ? 'товар' : (c.count % 10 >= 2 && c.count % 10 <= 4 && (c.count % 100 < 10 || c.count % 100 >= 20)) ? 'товара' : 'товаров'} →</a>
      <button type="button" class="mcm__dots mct__cdel" aria-label="Удалить категорию" title="Удалить">×</button>
      <textarea class="mct__cdesc" rows="2" placeholder="Описание раздела под его заголовком на сайте (необязательно)">${esc(c.desc || '')}</textarea>
    </li>`;

  function render() {
    const d = D.defaults;
    app.innerHTML = `
      <div class="mcm__bar">
        <a class="button button-primary" href="${esc(D.products)}">Товары типа «${esc(D.name)}» (${D.count}) →</a>
        ${D.page ? (D.page.draft
          ? `<a class="button" href="${esc(D.page.edit)}">Дозаполнить страницу (черновик) →</a>`
          : `<a class="button" href="${esc(D.page.url)}" target="_blank">Страница на сайте ↗</a> <a class="button-link" href="${esc(D.page.edit)}">редактировать</a>`)
          : `<button type="button" class="button" data-mkpage>Создать страницу для «${esc(D.name)}»</button>`}
        <span class="mcm__status is-ok">Все изменения сохранены</span>
      </div>
      <div class="mct__grid">
        <section class="mct__card">
          <h2>Тип товара</h2>
          <label class="mct__lbl">Название<input type="text" class="mct__name" value="${esc(D.name)}"></label>
          <h3>Новый товар по умолчанию</h3>
          <p class="description">Что сразу вписывается в новый товар этого типа. Остаётся поправить отличия.</p>
          <div class="mct__defs">
            <label class="mct__lbl">Цена<span class="mcm__num"><input type="text" inputmode="numeric" class="mct__dprice" value="${esc(d.price)}" placeholder="по запросу"><i>₽</i></span></label>
            <label class="mcm__from mct__dfromw"><input type="checkbox" class="mct__dfrom"${d.from ? ' checked' : ''}> «от»</label>
            <label class="mct__lbl">Единица<input type="text" class="mct__dunit" value="${esc(d.unit)}" placeholder="за шт."></label>
          </div>
          <h3>Вид каталога на сайте</h3>
          <div class="mct__defs">
            <label class="mct__lbl">Фото в карточках<select class="mct__ratio"><option value="">Обычные 4:3</option>${Object.entries(D.ratios).map(([k, v]) => `<option value="${esc(k)}"${D.ratio === k ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select></label>
            <label class="mct__lbl">Колонок на компьютере<input type="number" min="2" max="6" class="mct__cols" value="${esc(D.cols)}" placeholder="4"></label>
          </div>
          <p class="mct__danger"><button type="button" class="button-link mct__deltype">Удалить тип «${esc(D.name)}»</button>${D.count ? ' <span class="description">— можно, когда в нём не останется товаров</span>' : ''}</p>
        </section>
        <section class="mct__card">
          <h2>Категории</h2>
          <p class="description">Разделы внутри типа — на сайте это кнопки над каталогом. Перетаскивайте, чтобы поменять порядок; название правится прямо в строке.</p>
          <p class="description">Под заголовком раздела на сайте каталог сам пишет «47 моделей, от 800 ₽». Ниже — ваше описание раздела; в нём тоже можно писать [mem_cat_count] и [mem_cat_price].</p>
          <ul class="mct__cats">${D.cats.map(catRow).join('') || '<li class="mcm__none">Пока нет категорий</li>'}</ul>
          <p class="mct__add"><input type="text" class="mct__cnew" placeholder="Новая категория"> <button type="button" class="button" data-addcat>Добавить</button></p>
        </section>
      </div>
      <section class="mct__card">
        <h2>Характеристики</h2>
        <p class="description">У каждого товара типа — своя колонка в «Товарах» и строка в карточке на сайте. «По умолчанию» — значение, которое сразу ставится новым товарам.</p>
        <div class="mcm__wrap"><table class="mcm__table mct__ftable">
          <thead><tr><th></th><th>Название</th><th>Вид</th><th>Единица / варианты</th><th>По умолчанию</th><th></th></tr></thead>
          <tbody>${fields.map(fieldRow).join('')}</tbody></table></div>
        ${fields.length ? '' : '<p class="mcm__empty">Пока нет характеристик.</p>'}
        <p><button type="button" class="button" data-addf>+ Добавить характеристику</button></p>
      </section>`;
    $(app.querySelector('.mct__ftable tbody')).sortable({ handle: '.mcm__drag', axis: 'y', update: () => {
      sync(); const order = [...app.querySelectorAll('.mct__ftable tbody tr')].map(tr => +tr.dataset.i);
      fields = order.map(i => fields[i]); render(); saveFields();
    } });
    $(app.querySelector('.mct__cats')).sortable({ handle: '.mcm__drag', axis: 'y', items: '> li[data-id]', update: function () {
      track(post('mem_ct_order', { ids: [...this.querySelectorAll('li[data-id]')].map(li => +li.dataset.id) }));
    } });
  }

  /* ---------- характеристики ---------- */
  function sync() {
    app.querySelectorAll('.mct__ftable tbody tr').forEach(tr => {
      const f = fields[+tr.dataset.i];
      f.label = tr.querySelector('.mct__flabel').value;
      f.type = tr.querySelector('.mct__ftype').value;
      const u = tr.querySelector('.mct__funit'); if (u) f.unit = u.value;
      const o = tr.querySelector('.mct__fopts'); if (o) f.options = o.value.split(',').map(s => s.trim()).filter(Boolean);
      const df = tr.querySelector('.mct__fdef'); if (df) f.default = df.value;
    });
  }
  function saveFields() {
    later('fields', () => {
      const clean = fields.filter(f => f.label.trim());
      const renames = {}; clean.forEach(f => { if (f.orig && f.orig !== f.label.trim()) renames[f.orig] = f.label.trim(); });
      track(post('mem_cm_fields', { type: D.id, for: 'types',
        fields: JSON.stringify(clean.map(({ label, type, unit, options, default: def }) => ({ label: label.trim(), type, unit, options, default: def }))),
        renames: JSON.stringify(renames) }))
        .done(r => { if (r.success) { clean.forEach(f => { f.orig = f.label.trim(); }); D.fields = r.data.fields; } });
    }, 700);
  }
  $(app).on('input', '.mct__ftable input', () => { sync(); saveFields(); });
  $(app).on('change', '.mct__ftable select', function () {
    sync();
    if (this.classList.contains('mct__ftype')) render(); // поменялся вид — другие поля в строке
    saveFields();
  });
  $(app).on('click', '[data-addf]', () => {
    sync(); fields.push({ label: '', type: 'text', unit: '', options: [], default: '' }); render();
    const l = app.querySelectorAll('.mct__flabel'); l[l.length - 1].focus();
  });
  $(app).on('click', '.mct__fdel', function () {
    sync(); const f = fields[+this.closest('tr').dataset.i];
    if (f.orig && !confirm('Убрать характеристику «' + f.label + '»? Уже вписанные значения у товаров сохранятся, но колонки и строки в карточке больше не будет.')) return;
    fields.splice(fields.indexOf(f), 1); render(); saveFields();
  });

  /* ---------- тип: название, по умолчанию, вид ---------- */
  const saveType = (f, v) => track(post('mem_ct_type', { type: D.id, f, v }));
  $(app).on('input', '.mct__name', function () {
    const v = this.value.trim(); if (!v) return;
    later('name', () => saveType('name', v).done(r => { if (r.success) { D.name = v; const t = root.querySelector(`.mct__tabs [data-id="${D.id}"]`); if (t) t.querySelector('.mct__tname').textContent = v; } }));
  });
  const saveDefaults = () => later('defs', () => saveType('defaults', JSON.stringify({
    price: app.querySelector('.mct__dprice').value, from: app.querySelector('.mct__dfrom').checked ? 1 : 0, unit: app.querySelector('.mct__dunit').value })));
  $(app).on('input change', '.mct__dprice, .mct__dfrom, .mct__dunit', saveDefaults);
  $(app).on('change', '.mct__ratio', function () { saveType('ratio', this.value); });
  $(app).on('input', '.mct__cols', function () { later('cols', () => saveType('cols', this.value)); });
  $(app).on('click', '.mct__deltype', () => {
    if (D.count) return alert('В типе «' + D.name + '» есть товары (' + D.count + '). Сначала перенесите их в другой тип или удалите.');
    if (!confirm('Удалить тип «' + D.name + '» вместе с его категориями?')) return;
    track(post('mem_ct_type_delete', { type: D.id })).done(r => { if (r.success) location.href = r.data.url; });
  });

  $(app).on('click', '[data-mkpage]', function () {
    this.disabled = true;
    track(post('mem_ct_page', { type: D.id })).done(r => { if (r.success) { D = r.data; copyFields(); render(); } else this.disabled = false; });
  });
  /* ---------- категории ---------- */
  $(app).on('input', '.mct__cname', function () {
    const id = this.closest('li').dataset.id, v = this.value.trim(); if (!v) return;
    later('cat' + id, () => track(post('mem_ct_cat', { id, name: v })));
  });
  $(app).on('input', '.mct__cdesc', function () {
    const li = this.closest('li'), id = li.dataset.id, c = D.cats.find(x => x.id === +id), v = this.value;
    if (c) c.desc = v;
    later('desc' + id, () => track(post('mem_ct_cat', { id, desc: v })));
  });
  $(app).on('click', '.mct__cdel', function () {
    const li = this.closest('li'), c = D.cats.find(x => x.id === +li.dataset.id);
    if (!confirm('Удалить категорию «' + c.name + '»?' + (c.count ? ' Её товары (' + c.count + ') останутся в типе «' + D.name + '».' : ''))) return;
    track(post('mem_ct_cat_delete', { id: c.id })).done(r => { if (r.success) { D = r.data; copyFields(); render(); } });
  });
  const addCat = () => {
    const inp = app.querySelector('.mct__cnew'), name = inp.value.trim(); if (!name) return inp.focus();
    track(post('mem_cm_term', { name, parent: D.id })).done(r => {
      if (!r.success) return;
      sync(); D.cats.push({ id: r.data.id, name, depth: 0, count: 0 }); render();
      app.querySelector('.mct__cnew').focus();
    });
  };
  $(app).on('click', '[data-addcat]', addCat);
  $(app).on('keydown', '.mct__cnew', e => { if (e.key === 'Enter') { e.preventDefault(); addCat(); } });

  /* ---------- вкладки: порядок и новый тип ---------- */
  // порядок типов — только за стрелочки ⇆ слева от названия; нажатие на само название открывает тип
  $(root).on('click', '.mct__grip', e => e.preventDefault());
  $(root.querySelector('.mct__tabs')).sortable({ items: '> a.mcm__tab', handle: '.mct__grip', axis: 'x', distance: 4, update: function () {
    track(post('mem_ct_order', { ids: [...this.querySelectorAll('a.mcm__tab')].map(a => +a.dataset.id) }));
  } });
  $(root).on('click', '[data-act="new-type"]', function () {
    const btn = this, inp = document.createElement('input');
    inp.type = 'text'; inp.className = 'mcm__typeinp'; inp.placeholder = 'Название типа, Enter';
    btn.replaceWith(inp); inp.focus();
    inp.addEventListener('keydown', e => {
      if (e.key === 'Escape') inp.replaceWith(btn);
      if (e.key !== 'Enter' || !inp.value.trim()) return;
      inp.disabled = true;
      post('mem_cm_term', { name: inp.value.trim(), parent: 0 }).done(r => {
        if (r.success) location.href = location.pathname + '?post_type=mem_product&page=mem-types&type=' + r.data.id;
        else { inp.disabled = false; alert(r.data || 'Не получилось'); }
      });
    });
  });

  window.addEventListener('beforeunload', e => { if (pending) { e.preventDefault(); e.returnValue = ''; } });
  if (D) { copyFields(); render(); }
});
