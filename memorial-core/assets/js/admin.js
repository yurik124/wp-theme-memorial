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

  // Порядок блоков главной: строку тянут мышью или пальцем (за любое место), либо кнопками ↑ ↓.
  // Без jQuery UI — работает, даже если библиотека не загрузилась.
  document.querySelectorAll('.mem-order').forEach(list => {
    const note = list.parentElement.querySelector('.mem-order__note');
    const changed = () => { if (note) note.hidden = false; list.classList.add('is-changed'); };
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
