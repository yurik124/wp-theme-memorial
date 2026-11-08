/* Ряды вкладок и кнопок в админке каталога и работ: в одну строку, листаются влево-вправо
   стрелками, колёсиком/тачпадом, пальцем и мышью (потянуть). Как разделы на сайте. */
(function () {
  const SEL = '.mcm__tabs, .mcm__filters, .mcm__phs';
  const memory = {};                       // где был ряд до перерисовки — чтобы не прыгал в начало
  const key = el => el.classList.contains('mcm__tabs') ? 'tabs' : el.classList.contains('mcm__phs') ? 'phs' : 'filters';

  function init(el) {
    if (el._mhs) return;
    el._mhs = true;
    const box = document.createElement('div');
    box.className = 'mhs mhs--' + key(el);
    el.parentNode.insertBefore(box, el);
    box.appendChild(el);
    el.classList.add('mhs__in');
    ['l', 'r'].forEach(s => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'mhs__arr mhs__arr--' + s; b.tabIndex = -1;
      b.setAttribute('aria-label', s === 'l' ? 'Прокрутить влево' : 'Прокрутить вправо');
      b.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="' + (s === 'l' ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7') + '" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';   // ровно по центру
      b.addEventListener('click', () => el.scrollBy({ left: (s === 'l' ? -1 : 1) * el.clientWidth * .7, behavior: 'smooth' }));
      box.appendChild(b);
    });
    const edges = () => {
      box.classList.toggle('has-l', el.scrollLeft > 4);
      box.classList.toggle('has-r', el.scrollLeft + el.clientWidth < el.scrollWidth - 4);
      if (key(el) !== 'phs') memory[key(el)] = el.scrollLeft;   // у рядов фото — своё место у каждого товара
    };
    el.addEventListener('scroll', edges, { passive: true });
    addEventListener('resize', edges);

    // колёсико мыши (вертикальное) листает ряд вбок, если ряд длиннее экрана
    el.addEventListener('wheel', e => {
      if (el.scrollWidth <= el.clientWidth || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
      e.preventDefault(); el.scrollLeft += e.deltaY;
    }, { passive: false });

    // потянуть мышью (кроме вкладок типов на «Типы и категории» — их там перетаскивают для порядка)
    if (!el.classList.contains('mct__tabs') && !el.classList.contains('mcm__phs')) {   // в ряду фото мышью меняют порядок фото
      let x0 = 0, s0 = 0, down = false, moved = false;
      el.addEventListener('dragstart', e => e.preventDefault());
      el.addEventListener('pointerdown', e => {
        if (e.pointerType !== 'mouse' || e.button !== 0 || el.scrollWidth <= el.clientWidth) return;
        down = true; moved = false; x0 = e.clientX; s0 = el.scrollLeft;
      });
      addEventListener('pointermove', e => {
        if (!down) return;
        const dx = e.clientX - x0;
        if (!moved && Math.abs(dx) > 5) { moved = true; el.classList.add('is-drag'); }
        if (moved) el.scrollLeft = s0 - dx;
      });
      addEventListener('pointerup', () => { if (!down) return; down = false; if (moved) setTimeout(() => el.classList.remove('is-drag'), 0); });
      el.addEventListener('click', e => { if (el.classList.contains('is-drag')) { e.preventDefault(); e.stopPropagation(); } }, true);
    }

    // после перерисовки — на прежнее место; выбранная кнопка должна быть видна
    if (key(el) !== 'phs' && memory[key(el)]) el.scrollLeft = memory[key(el)];
    const on = el.querySelector('.is-on');
    if (on) {
      const l = on.offsetLeft - el.offsetLeft, r = l + on.offsetWidth;
      if (l < el.scrollLeft || r > el.scrollLeft + el.clientWidth) el.scrollLeft = Math.max(0, l - 40);
    }
    edges();
  }

  const scan = () => document.querySelectorAll(SEL).forEach(init);
  document.addEventListener('DOMContentLoaded', scan);
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
  scan();
})();
