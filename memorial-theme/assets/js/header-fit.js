/**
 * Header of the site (patterns/header.php): the menu does not fit in a row — switch the header to the burger.
 * Printed right after the header (functions.php, render_block of the Header template part), so it runs
 * before painting, without flicker.
 */
(function () {
	var h = document.getElementById('header'), nav = h && h.querySelector('.wp-block-navigation');
	if (!h || !nav) return;
	nav.id = nav.id || 'nav';
	function items() { return nav.querySelectorAll('.wp-block-navigation__container > li'); }
	function over() { // the last item went over the edge of the menu or the items wrap to a new line
	var li = items();
	if (!li.length) return false;
	var a = li[0].getBoundingClientRect(), b = li[li.length - 1].getBoundingClientRect();
	return b.right > nav.getBoundingClientRect().right + 1 || b.top > a.top + 4;
	}
	function fit() {
	var was = h.classList.contains('is-compact');
	h.classList.add('is-measuring');
	h.classList.remove('is-compact', 'is-tworow');
	var compact = innerWidth <= 900, two = false;
	if (!compact && over()) {
		h.classList.add('is-tworow');
		two = !over();
		if (!two) { h.classList.remove('is-tworow'); compact = true; }
	}
	h.classList.toggle('is-compact', compact);
	document.documentElement.classList.toggle('hdr-two', two);
	if (two) document.documentElement.style.setProperty('--hh2', h.offsetHeight + 'px');
	void nav.offsetWidth;
	h.classList.remove('is-measuring');
	if (was && !compact) {
		nav.classList.remove('is-open');
		document.body.classList.remove('menu-open');
		var b = document.getElementById('burger');
		if (b) b.setAttribute('aria-expanded', 'false');
	}
	}
	fit();
	if (window.ResizeObserver) new ResizeObserver(fit).observe(h.querySelector('.header__in'));
	else addEventListener('resize', fit);
	if (document.fonts) document.fonts.ready.then(fit);
	addEventListener('load', fit);
})();
