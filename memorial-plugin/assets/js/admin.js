/* Настройки сайта: списки с добавлением, удалением и перетаскиванием; выбор фото */
jQuery(function ($) {
  let uid = Date.now();

  const sortable = scope => $(scope).find('.rep__rows').addBack('.rep__rows').sortable({
    handle: '> .rep__handle, > summary > .rep__handle', items: '> .rep__row', axis: 'y', tolerance: 'pointer',
    placeholder: 'rep__ph', forcePlaceholderSize: true
  });
  sortable(document);

  // Цвета
  // Пустое поле цвета = цвет из схемы: показываем его прямо на кнопке палитры
  const paint = input => {
    const wrap = input.closest('.wp-picker-container'), btn = wrap && wrap.querySelector('.wp-color-result');
    const row = input.closest('.mem-color__row'), sc = input.dataset.schemeColor;
    if (!btn || !sc) return;
    const empty = !input.value.trim();
    if (empty) btn.style.backgroundColor = sc;
    btn.classList.toggle('is-scheme', empty);
    btn.title = empty ? 'Цвет из схемы: ' + sc : '';
    row?.querySelector('.mem-color__from')?.toggleAttribute('hidden', !empty);
  };
  const colors = scope => $(scope).find('.mem-color').each(function () {
    if ($(this).closest('template').length || !$.fn.wpColorPicker) return;
    const input = this;
    $(input).wpColorPicker({
      change: () => setTimeout(() => paint(input)),
      clear: () => setTimeout(() => paint(input)),
    });
    $(input).on('input', () => paint(input));
    paint(input);
  });
  colors(document);

  // Схема: подсказки «из схемы» меняются вместе с выбором схемы
  $(document).on('change', 'select[data-presets]', function () {
    const c = JSON.parse(this.dataset.presets)[this.value] || {};
    $('.mem-color[data-scheme]').each(function () {
      const v = c[this.dataset.scheme]; if (!v) return;
      this.dataset.schemeColor = v; paint(this);
    });
  });

  // Файл шрифта
  $(document).on('click', '.file-pick__choose', function () {
    const input = this.parentElement.querySelector('input');
    const f = wp.media({ title: 'Файл шрифта', button: { text: 'Выбрать' }, multiple: false });
    f.on('select', () => { input.value = f.state().get('selection').first().toJSON().url; });
    f.open();
  });

  // Карточки настроек: сворачиваются щелчком по заголовку; что свёрнуто — запоминается в этом браузере
  const CK = 'mem_admin_collapsed';
  const getC = () => { try { return JSON.parse(localStorage.getItem(CK) || '[]'); } catch (x) { return []; } };
  const setC = list => { try { localStorage.setItem(CK, JSON.stringify(list)); } catch (x) {} };
  const fold = (card, closed, save = true) => {
    card.classList.toggle('is-collapsed', closed);
    card.querySelector('.mem-card__toggle')?.setAttribute('aria-expanded', String(!closed));
    if (!save) return;
    const key = card.dataset.card, list = getC().filter(k => k !== key);
    if (closed) list.push(key);
    setC(list);
  };
  const saved = getC();
  document.querySelectorAll('.mem-card[data-card]').forEach(card => { if (saved.includes(card.dataset.card)) fold(card, true, false); });
  document.addEventListener('click', e => {
    const t = e.target.closest('.mem-card__toggle');
    if (t) { const card = t.closest('.mem-card'); fold(card, !card.classList.contains('is-collapsed')); return; }
    const all = e.target.closest('[data-cards]');
    if (all) document.querySelectorAll('.mem-card[data-card]').forEach(c => fold(c, all.dataset.cards === 'collapse'));
  });

  // Порядок блоков главной: строку тянут мышью или пальцем (за любое место), либо кнопками ↑ ↓.
  // Без jQuery UI — работает, даже если библиотека не загрузилась.
  document.querySelectorAll('.mem-order').forEach(list => {
    const note = list.parentElement.querySelector('.mem-order__note');
    const changed = () => { if (note) note.hidden = false; list.classList.add('is-changed'); list.dispatchEvent(new Event('mem:changed')); };
    let drag = null, moved = false;
    list.addEventListener('pointerdown', e => {
      const li = e.target.closest('li');
      if (!li || e.target.closest('button') || (e.pointerType === 'mouse' && e.button !== 0)) return;
      e.preventDefault();
      drag = li; moved = false;
      li.classList.add('is-drag');
      try { list.setPointerCapture(e.pointerId); } catch (x) {}
    });
    list.addEventListener('pointermove', e => {
      if (!drag) return;
      const over = [...list.children].find(li => { if (li === drag) return false; const r = li.getBoundingClientRect(); return e.clientY >= r.top && e.clientY <= r.bottom; });
      if (!over) return;
      const r = over.getBoundingClientRect();
      if (e.clientY < r.top + r.height / 2) { if (over.previousElementSibling !== drag) { over.before(drag); moved = true; } }
      else if (over.nextElementSibling !== drag) { over.after(drag); moved = true; }
    });
    const end = () => { if (!drag) return; drag.classList.remove('is-drag'); drag = null; if (moved) changed(); };
    list.addEventListener('pointerup', end);
    list.addEventListener('pointercancel', end);
    list.addEventListener('lostpointercapture', end);
    // карточки блоков ниже идут в том же порядке, что и список
    const cards = document.querySelector('.mem-blocks');
    const syncCards = () => { if (!cards) return; [...list.querySelectorAll('input')].forEach(i => { const c = cards.querySelector(':scope > [data-block="' + i.value + '"]'); if (c) cards.appendChild(c); }); };
    list.addEventListener('mem:changed', syncCards);
    list.addEventListener('click', e => {
      const b = e.target.closest('[data-mv]');
      if (!b) return;
      const li = b.closest('li'), prev = li.previousElementSibling, next = li.nextElementSibling;
      if (b.dataset.mv === '-1' && prev) prev.before(li);
      else if (b.dataset.mv === '1' && next) next.after(li);
      else return;
      b.focus();
      changed();
    });
  });

  // Карточки блоков главной: тянуть за ⋮⋮ или кнопками ↑ ↓ в заголовке; список «Порядок блоков» меняется вместе с ними
  document.querySelectorAll('.mem-blocks').forEach(box => {
    const list = document.querySelector('.mem-order');
    const syncList = () => {
      if (!list) return;
      [...box.children].forEach(c => { const i = list.querySelector('input[value="' + c.dataset.block + '"]'); if (i) list.appendChild(i.closest('li')); });
      const note = list.parentElement.querySelector('.mem-order__note');
      if (note) note.hidden = false;
      document.querySelectorAll('.mem-blocks__note').forEach(n => n.hidden = false);
    };
    let drag = null, moved = false;
    box.addEventListener('pointerdown', e => {
      const h = e.target.closest('.mem-card__drag');
      if (!h || (e.pointerType === 'mouse' && e.button !== 0)) return;
      e.preventDefault();
      drag = h.closest('.mem-card'); moved = false;
      drag.classList.add('is-drag');
      try { box.setPointerCapture(e.pointerId); } catch (x) {}
    });
    box.addEventListener('pointermove', e => {
      if (!drag) return;
      const over = [...box.children].find(c => { if (c === drag) return false; const r = c.getBoundingClientRect(); return e.clientY >= r.top && e.clientY <= r.bottom; });
      if (!over) return;
      const r = over.getBoundingClientRect();
      if (e.clientY < r.top + r.height / 2) { if (over.previousElementSibling !== drag) { over.before(drag); moved = true; } }
      else if (over.nextElementSibling !== drag) { over.after(drag); moved = true; }
    });
    const end = () => { if (!drag) return; drag.classList.remove('is-drag'); drag = null; if (moved) syncList(); };
    box.addEventListener('pointerup', end);
    box.addEventListener('pointercancel', end);
    box.addEventListener('lostpointercapture', end);
    box.addEventListener('click', e => {
      const b = e.target.closest('[data-cmv]');
      if (!b) return;
      const c = b.closest('.mem-card'), prev = c.previousElementSibling, next = c.nextElementSibling;
      if (b.dataset.cmv === '-1' && prev) prev.before(c);
      else if (b.dataset.cmv === '1' && next) next.after(c);
      else return;
      b.focus();
      c.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      syncList();
    });
  });

  // Добавить пункт
  $(document).on('click', '.rep__add', function () {
    const rep = this.closest('.rep');
    const tpl = rep.querySelector(':scope > template');
    const html = tpl.innerHTML.split(rep.dataset.key).join(String(++uid));
    const rows = rep.querySelector(':scope > .rep__rows');
    rows.insertAdjacentHTML('beforeend', html);
    const row = rows.lastElementChild;
    sortable(row);
    colors(row);
    row.querySelector('input:not([type=hidden]),textarea')?.focus();
  });

  // Удалить пункт
  $(document).on('click', '.rep__del', function (e) {
    e.preventDefault(); // внутри <summary> не сворачиваем
    const row = this.closest('.rep__row');
    const filled = [...row.querySelectorAll('input:not([type=checkbox]):not([type=hidden]),textarea')].some(el => el.value.trim());
    if (filled && !confirm('Удалить этот пункт?')) return;
    row.remove();
  });

  // Заголовок свёрнутого пункта повторяет его название
  $(document).on('input', '[data-title-src] input', function () {
    const row = this.closest('.rep__row');
    const t = row.querySelector(':scope > summary .rep__title');
    if (t) t.textContent = this.value.trim() || 'Новый пункт';
  });

  // Итог пакета считается на лету
  const total = row => {
    const out = row.querySelector(':scope > summary .rep__sum');
    if (!out) return;
    let s = 0;
    row.querySelectorAll('input[type=number][name$="[price]"]').forEach(i => { s += +i.value || 0; });
    out.textContent = s ? s.toLocaleString('ru-RU') + ' ₽' : '';
  };
  document.querySelectorAll('.rep__row').forEach(r => {
    if (r.querySelector('input[name$="[price]"]') && r.querySelector(':scope > summary')) {
      r.querySelector(':scope > summary .rep__title').insertAdjacentHTML('afterend', '<span class="rep__sum"></span>');
      total(r);
    }
  });
  $(document).on('input', 'input[name$="[price]"]', function () {
    total(this.closest('details.rep__row'));
  });

  // Строка «Итого» под списком позиций с ценами (пакеты похорон, кремация)
  const itogo = rep => {
    const out = rep.querySelector(':scope > .rep__itogo b');
    if (!out) return;
    let s = 0;
    rep.querySelectorAll(':scope > .rep__rows > .rep__row input[type=number][name$="[price]"]').forEach(i => { s += +i.value || 0; });
    out.textContent = s.toLocaleString('ru-RU') + ' ₽';
  };
  document.querySelectorAll('.rep--inline').forEach(rep => {
    const rows = rep.querySelector(':scope > .rep__rows');
    if (!rows || !rep.querySelector('input[type=number][name$="[price]"]')) return;
    rows.insertAdjacentHTML('afterend', '<div class="rep__itogo"><span>Итого</span><b></b></div>');
    itogo(rep);
    new MutationObserver(() => itogo(rep)).observe(rows, { childList: true });
  });
  $(document).on('input', '.rep--inline input[name$="[price]"]', function () {
    itogo(this.closest('.rep--inline'));
  });

  // Выбор фото из медиатеки
  let frame;
  $(document).on('click', '.img-pick__choose', function () {
    const box = this.closest('.img-pick');
    frame = wp.media({ title: 'Фоновое фото', library: { type: 'image' }, button: { text: 'Выбрать' }, multiple: false });
    frame.on('select', () => {
      const a = frame.state().get('selection').first().toJSON();
      const url = (a.sizes && a.sizes['2048x2048'] || a.sizes && a.sizes.full || a).url;
      box.querySelector('input').value = url;
      box.querySelector('.img-pick__prev').style.backgroundImage = 'url(' + url + ')';
      box.querySelector('.img-pick__clear').hidden = false;
    });
    frame.open();
  });
  $(document).on('click', '.img-pick__clear', function () {
    const box = this.closest('.img-pick');
    box.querySelector('input').value = '';
    box.querySelector('.img-pick__prev').style.backgroundImage = '';
    this.hidden = true;
  });

  // Предупреждение о несохранённых изменениях
  let dirty = false;
  $('.mem-admin form').on('input change', () => { dirty = true; }).on('submit', () => { dirty = false; });
  $(document).on('click', '.rep__add,.rep__del', () => { dirty = true; });
  $('.mem-admin .rep__rows').on('sortupdate', () => { dirty = true; });
  addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
});
