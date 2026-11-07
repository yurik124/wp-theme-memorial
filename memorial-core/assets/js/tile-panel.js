/* Панель «Фото в плитке каталога» в редакторе страницы: точка фокуса и «показать целиком» с живым предпросмотром */
(function (wp) {
  if (!wp || !wp.plugins) return;
  var el = wp.element.createElement, C = wp.components, useSelect = wp.data.useSelect, useDispatch = wp.data.useDispatch;
  var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
  if (!Panel) return;

  function parse(v) {
    var m = /^([\d.]+)% ([\d.]+)%$/.exec(v || '');
    return m ? { x: m[1] / 100, y: m[2] / 100 } : { x: 0.5, y: 0.5 };
  }

  /* та же формула, что на сайте: выбранная точка — в центр плитки */
  function centerPos(fx, fy, nw, nh, w, h) {
    if (!nw || !nh) return Math.round(fx * 100) + '% ' + Math.round(fy * 100) + '%';
    var k = Math.max(w / nw, h / nh), W = nw * k, H = nh * k;
    var px = W - w > 1 ? Math.min(100, Math.max(0, (fx * W - w / 2) / (W - w) * 100)) : 50;
    var py = H - h > 1 ? Math.min(100, Math.max(0, (fy * H - h / 2) / (H - h) * 100)) : 50;
    return px.toFixed(1) + '% ' + py.toFixed(1) + '%';
  }

  function TilePanel() {
    var d = useSelect(function (select) {
      var ed = select('core/editor'), id = ed.getEditedPostAttribute('featured_media');
      var media = id ? select('core').getMedia(id) : null;
      var md = media && media.media_details || {};
      var src = media ? ((md.sizes && md.sizes.medium_large && md.sizes.medium_large.source_url) || media.source_url) : '';
      return { id: id, src: src, nw: md.width || 0, nh: md.height || 0, meta: ed.getEditedPostAttribute('meta') || {} };
    }, []);
    var edit = useDispatch('core/editor').editPost;
    var set = function (k, v) { var o = {}; o[k] = v; edit({ meta: o }); };
    // название и подпись в карточках [mem_cards] на других страницах
    function cardFields() {
      return el('div', null,
        el('p', { style: { color: '#757575', marginTop: 0 } }, 'Как эта страница выглядит в карточках разделов ([mem_cards]) и плитках на других страницах.'),
        el(C.TextControl, { __nextHasNoMarginBottom: true, label: 'Название в карточке', placeholder: 'как в меню', value: d.meta._mem_card_title || '', onChange: function (v) { set('_mem_card_title', v); } }),
        el('div', { style: { height: '8px' } }),
        el(C.TextControl, { __nextHasNoMarginBottom: true, label: 'Подпись в карточке', placeholder: 'например: Примеры работ с ценами', value: d.meta._mem_card_text || '',
          help: 'Для страниц каталога «96 моделей, от 35 000 ₽» пишется само — здесь можно добавить вторую строку.', onChange: function (v) { set('_mem_card_text', v); } })
      );
    }

    if (!d.id) return el(Panel, { name: 'mem-tile', title: 'Плитка и карточка раздела' },
      cardFields(),
      el('p', { style: { color: '#757575' } }, 'Фото: сначала выберите «Изображение записи» — затем здесь можно настроить, какая часть фото видна в плитке на главной и в каталоге.'));

    var pos = d.meta._mem_tile_pos || '50% 50%', fit = !!d.meta._mem_tile_fit, bg = d.meta._mem_tile_bg || '';
    var fp = parse(pos);
    // плитка на сайте ≈ 285×260 — превью той же пропорции
    var shown = fit ? 'center' : centerPos(fp.x, fp.y, d.nw, d.nh, 248, 226);
    var preview = el('div', { style: {
      width: '248px', maxWidth: '100%', aspectRatio: '248 / 226', borderRadius: '10px', marginTop: '8px', backgroundColor: bg || 'transparent', border: '1px solid #e0e0e0',
      backgroundImage: 'linear-gradient(180deg,rgba(23,24,28,.05),rgba(23,24,28,.88)),url(' + d.src + ')',
      backgroundPosition: 'center,' + shown, backgroundSize: 'auto,' + (fit ? 'contain' : 'cover'), backgroundRepeat: 'no-repeat',
      display: 'flex', alignItems: 'flex-end', padding: '12px', color: '#fff', fontWeight: 600
    } }, 'Так выглядит плитка');

    return el(Panel, { name: 'mem-tile', title: 'Плитка и карточка раздела' },
      cardFields(),
      el('p', { style: { fontWeight: 500, margin: '14px 0 4px' } }, 'Фото'),
      el('p', { style: { color: '#757575', marginTop: 0 } }, 'Кликните по фото в точку, которая должна быть в центре плитки (например, верх памятника). Если точка у самого края фото, плитка сдвинется насколько возможно.'),
      d.src && !fit ? el(C.FocalPointPicker, {
        __nextHasNoMarginBottom: true, url: d.src, value: parse(pos),
        onChange: function (v) { set('_mem_tile_pos', Math.round(v.x * 100) + '% ' + Math.round(v.y * 100) + '%'); }
      }) : null,
      el(C.ToggleControl, {
        __nextHasNoMarginBottom: true, label: 'Показать фото целиком, без обрезки', checked: fit,
        help: fit ? 'По бокам или сверху/снизу будет тёмный фон.' : 'Фото заполняет плитку, края обрезаются.',
        onChange: function (v) { set('_mem_tile_fit', v); }
      }),
      d.src ? preview : null,
      el('p', { style: { margin: '12px 0 6px', fontWeight: 500 } }, 'Цвет фона плитки'),
      el('p', { style: { color: '#757575', margin: '0 0 8px', fontSize: '12px' } }, 'Виден за фото с прозрачным фоном (PNG) и по краям в режиме «целиком». Пусто — без фона.'),
      el(C.ColorPalette, {
        __experimentalIsRenderedInSidebar: true, value: bg || undefined, clearable: true, enableAlpha: false,
        colors: [
          { name: 'Тёмный', color: '#17181c' }, { name: 'Графит', color: '#2a2d33' }, { name: 'Белый', color: '#ffffff' },
          { name: 'Светлый', color: '#f4f2ee' }, { name: 'Бежевый', color: '#ebe6df' }, { name: 'Серый', color: '#d9d9d9' }
        ],
        onChange: function (v) { set('_mem_tile_bg', v || ''); }
      }),
      el(C.Button, { variant: 'link', style: { marginTop: '8px' }, onClick: function () { edit({ meta: { _mem_tile_pos: '', _mem_tile_fit: false, _mem_tile_bg: '' } }); } }, 'Сбросить')
    );
  }
  wp.plugins.registerPlugin('mem-tile', { render: TilePanel, icon: null });

  /* Переключатель под «Изображением записи»: показывать ли фото на самой странице */
  function ShowToggle() {
    var meta = useSelect(function (select) {
      var ed = select('core/editor');
      return { type: ed.getCurrentPostType(), hide: !!(ed.getEditedPostAttribute('meta') || {})._mem_hide_thumb };
    }, []);
    var edit = useDispatch('core/editor').editPost;
    if (meta.type !== 'page') return null;
    return el('div', { style: { marginTop: '12px' } }, el(C.ToggleControl, {
      __nextHasNoMarginBottom: true, label: 'Показывать на странице', checked: !meta.hide,
      help: meta.hide ? 'Фото используется только для плитки и превью в соцсетях.' : 'Фото выводится под заголовком страницы.',
      onChange: function (v) { edit({ meta: { _mem_hide_thumb: !v } }); }
    }));
  }
  if (wp.hooks) wp.hooks.addFilter('editor.PostFeaturedImage', 'memorial/show-thumb', function (Orig) {
    return function (props) { return el(wp.element.Fragment, null, el(Orig, props), el(ShowToggle)); };
  });
})(window.wp);
