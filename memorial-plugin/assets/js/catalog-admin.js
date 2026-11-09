/* Каталог в админке: «Свойства» с ценой, перетаскивание порядка, загрузка пачкой */
jQuery(function ($) {
  // «Свойства»: подставляем текущие значения
  if (window.inlineEditPost) {
    const orig = inlineEditPost.edit;
    inlineEditPost.edit = function (id) {
      orig.apply(this, arguments);
      const pid = typeof id === 'object' ? this.getId(id) : id;
      const d = $('#post-' + pid + ' .mem-qe').data() || {};
      const row = $('#edit-' + pid);
      row.find('[name=mem_qe_price]').val(d.price || '');
      row.find('[name=mem_qe_sub]').val(d.sub || '');
      row.find('[name=mem_qe_unit]').val(d.unit || '');
      row.find('[name=mem_qe_from]').prop('checked', String(d.from) === '1');
    };
  }

  // Порядок перетаскиванием (когда выбран фильтр по категории)
  if (window.memCat && memCat.sortable && $('#the-list tr').length > 1) {
    $('body').addClass('mem-sortable');
    $('#the-list').sortable({
      items: '> tr', axis: 'y', cursor: 'move', helper: (e, tr) => { tr.children().each(function () { $(this).width($(this).width()); }); return tr; },
      update: function () {
        const ids = $('#the-list > tr').map(function () { return this.id.replace('post-', ''); }).get();
        $.post(ajaxurl, { action: 'mem_sort', _ajax_nonce: memCat.nonce, ids: ids, offset: memCat.offset })
          .fail(() => alert('Не удалось сохранить порядок. Обновите страницу и попробуйте ещё раз.'));
      }
    });
  }

  // Цена и характеристики прямо в списке: сохраняются сами
  const timers = new WeakMap();
  const save = el => {
    const box = el.closest('.mem-ie'), st = box.querySelector('.mem-ie__st');
    const v = el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value;
    el.classList.add('is-saving'); st.className = 'mem-ie__st'; st.textContent = 'сохраняю…';
    $.post(ajaxurl, { action: 'mem_inline', _ajax_nonce: memCat.nonce, id: box.dataset.id, f: el.dataset.f, v: v })
      .done(r => { st.textContent = r && r.success ? 'сохранено ✓' : 'не сохранилось'; st.classList.toggle('is-err', !(r && r.success)); })
      .fail(() => { st.textContent = 'нет связи — не сохранилось'; st.classList.add('is-err'); })
      .always(() => { el.classList.remove('is-saving'); setTimeout(() => { if (st.textContent === 'сохранено ✓') st.textContent = ''; }, 2500); });
  };
  $(document).on('input', '.mem-ie [data-f]', function () {
    if (this.type === 'checkbox') return;
    clearTimeout(timers.get(this)); timers.set(this, setTimeout(() => save(this), 700));
  });
  $(document).on('change', '.mem-ie [data-f]', function () { clearTimeout(timers.get(this)); save(this); });
  // Enter в поле цены — к следующему товару
  $(document).on('keydown', '.mem-ie input[data-f="price"]', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const all = $('.mem-ie input[data-f="price"]'); const n = all.get(all.index(this) + 1); if (n) n.focus();
  });

  // Загрузить фото → новые товары в текущей категории
  $('#mem-add-pick').on('click', function () {
    const btn = this, st = $('.mem-add__st');
    const frame = wp.media({ title: 'Фото новых товаров', multiple: 'add', library: { type: 'image' }, button: { text: 'Создать товары' } });
    frame.on('select', function () {
      const ids = frame.state().get('selection').map(a => a.id);
      if (!ids.length) return;
      btn.disabled = true; st.text('Создаю товары: ' + ids.length + '…');
      $.post(ajaxurl, { action: 'mem_bulk_add', _ajax_nonce: memCat.nonce, cat: btn.dataset.cat, ids: ids,
        names: $('input[name=mem_add_names]:checked').val(), prefix: $('#mem-add-prefix').val() })
        .done(r => {
          if (r && r.success && r.data.ids.length) {
            const u = new URL(location.href); u.searchParams.set('mem_new', r.data.ids.join(',')); u.searchParams.delete('paged');
            location.href = u.toString();
          } else { st.text('Не получилось создать товары.'); btn.disabled = false; }
        })
        .fail(() => { st.text('Нет связи с сайтом — попробуйте ещё раз.'); btn.disabled = false; });
    });
    frame.open();
  });

  // Загрузить пачкой
  $('#mem-bulk-pick').on('click', function (e) {
    e.preventDefault();
    const frame = wp.media({ title: 'Фото для товаров', multiple: 'add', library: { type: 'image' }, button: { text: 'Выбрать' } });
    frame.on('select', function () {
      const sel = frame.state().get('selection').toJSON();
      $('#mem-bulk-ids').val(sel.map(a => a.id).join(','));
      $('#mem-bulk-prev').html(sel.map(a => {
        const u = (a.sizes && (a.sizes.thumbnail || a.sizes.medium) || a).url;
        return '<img src="' + u + '" style="width:70px;height:70px;object-fit:cover;border-radius:4px">';
      }).join(''));
      $('#mem-bulk-pick').text('Выбрано фото: ' + sel.length + ' — изменить');
    });
    frame.open();
  });

  // Работа: фото
  const $list = $('#mem-photos-list');
  if ($list.length) {
    const sync = () => $('#mem-photos').val($list.children().map(function () { return $(this).data('id'); }).get().join(','));
    $list.sortable({ update: sync });
    $list.on('click', 'button', function () { $(this).parent().remove(); sync(); });
    $('#mem-photos-add').on('click', function () {
      const frame = wp.media({ title: 'Фото работы', multiple: 'add', library: { type: 'image' }, button: { text: 'Добавить' } });
      frame.on('select', function () {
        frame.state().get('selection').toJSON().forEach(a => {
          const u = (a.sizes && (a.sizes.thumbnail || a.sizes.medium) || a).url;
          $list.append('<li data-id="' + a.id + '"><img src="' + u + '" alt=""><button type="button" aria-label="Убрать">×</button></li>');
        });
        sync();
      });
      frame.open();
    });
  }

  // Работа: строки цен
  const $rows = $('#mem-rows tbody');
  if ($rows.length) {
    const total = () => {
      let s = 0; $rows.find('[name="mem_rows_price[]"]').each(function () { s += parseInt(this.value, 10) || 0; });
      $('#mem-rows-total').text(s.toLocaleString('ru-RU') + ' ₽');
    };
    $('#mem-row-add').on('click', () => { const tr = $rows.children().last().clone(); tr.find('input').val(''); $rows.append(tr); });
    $rows.on('click', '.mem-row-del', function () { $rows.children().length > 1 ? $(this).closest('tr').remove() : $(this).closest('tr').find('input').val(''); total(); });
    $rows.on('input', 'input', total);
  }
});
