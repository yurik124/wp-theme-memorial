(() => {
  const T = k => (window.memorialThemeI18n || {})[k] || k; // texts translated by the theme (functions.php)
  const d = document, body = d.body;
  const header = d.getElementById('header');
  const burger = d.getElementById('burger');
  // меню — блок «Навигация» в шапке (id ему ставит скрипт шапки, parts/header.html)
  const nav = header?.querySelector('.wp-block-navigation') || d.getElementById('nav');

  function setMenu(open) {
    if (!burger) return;
    burger.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
    body.classList.toggle('menu-open', open);
    // закрытое меню не должно оставлять раскрытых подменю (иначе их ссылки ловят нажатия поверх страницы)
    if (!open) nav.querySelectorAll('li.is-open').forEach(li => { li.classList.remove('is-open'); li.querySelector(':scope > .sub-toggle')?.setAttribute('aria-expanded', 'false'); });
  }

  burger?.addEventListener('click', () => setMenu(burger.getAttribute('aria-expanded') !== 'true'));
  // Пункт-заголовок без ссылки («О нас» с адресом # или пустым): не прыгаем наверх страницы и не закрываем меню —
  // на телефоне раскрываем его подменю, на компьютере подменю и так открывается при наведении
  const isEmpty = a => { const h = (a.getAttribute('href') || '').trim(); return h === '' || h === '#'; };
  nav?.querySelectorAll('a').forEach(a => { if (isEmpty(a)) { a.classList.add('is-empty'); a.setAttribute('role', 'button'); } });
  nav?.addEventListener('click', e => {
    const a = e.target.closest('a');
    if (!a) return;
    if (isEmpty(a)) {
      e.preventDefault();
      if (a.closest('.is-compact')) {
        a.parentElement.querySelector(':scope > .sub-toggle')?.click(); // телефон: раскрыть подменю
      } else if (e.detail) {
        a.blur(); // компьютер, щелчок мышью: без фокуса подменю закроется, когда уведут курсор
      }
      return;
    }
    if (a.hash) setMenu(false); // якорь на этой же странице — меню закрываем
  });

  // Подменю в мобильном меню раскрываются стрелкой, а не висят все сразу
  nav?.querySelectorAll('.has-child, .menu-item-has-children').forEach((li, i) => {
    const sub = li.querySelector(':scope > .wp-block-navigation__submenu-container, :scope > .sub-menu');
    if (!sub) return;
    sub.id ||= 'sub-' + i;
    const b = d.createElement('button');
    b.type = 'button';
    b.className = 'sub-toggle';
    b.setAttribute('aria-expanded', 'false');
    b.setAttribute('aria-controls', sub.id);
    const link = li.querySelector(':scope > a, :scope > .wp-block-navigation-item__content');
    b.setAttribute('aria-label', T('expand').replace('%s', link?.textContent.trim() || T('submenu')));
    b.addEventListener('click', () => {
      const open = !li.classList.contains('is-open');
      li.classList.toggle('is-open', open);
      b.setAttribute('aria-expanded', String(open));
    });
    (link || sub).after(b);
    // ветка текущей страницы раскрыта — только в мобильном меню; на компьютере подменю открывается наведением
    if (li.matches('.current-menu-ancestor') && header?.classList.contains('is-compact')) b.click();
  });
  d.addEventListener('keydown', e => { if (e.key === 'Escape') { setMenu(false); } });

  // Кнопка «Наверх» — только там, где у браузера нет своей (в Яндекс Браузере она встроена)
  const totop = d.getElementById('totop');
  if (totop && /YaBrowser|YaSearchBrowser|YandexSearch/i.test(navigator.userAgent)) totop.remove();
  else if (totop) {
    totop.hidden = false;
    totop.addEventListener('click', () => scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }));
  }

  // Тень у шапки
  const onScroll = () => {
    header?.classList.toggle('is-scrolled', scrollY > 10);
    totop?.classList.toggle('is-on', scrollY > innerHeight * 1.2);
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

})();
