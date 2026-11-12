<?php
/**
 * Title: Header
 * Slug: memorial-theme/header
 * Inserter: no
 * Description: The header of the site: logo, menu, contacts and the burger.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:memorial-plugin/topbar /-->

<!-- wp:group {"tagName":"header","anchor":"header","className":"header","layout":{"type":"default"},"style":{"spacing":{"blockGap":"0"}}} -->
<header id="header" class="wp-block-group header"><!-- wp:group {"className":"wrap header__in","layout":{"type":"default"},"style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-group wrap header__in"><!-- wp:group {"className":"logo","layout":{"type":"default"},"style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-group logo"><!-- wp:site-logo {"width":44,"className":"logo__mark-wrap"} /-->

<!-- wp:group {"className":"logo__text","layout":{"type":"default"},"style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-group logo__text"><!-- wp:site-title {"level":0} /-->

<!-- wp:paragraph {"className":"logo__sub"} -->
<p class="logo__sub"><?php esc_html_e( 'funeral company', 'memorial-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:navigation {"overlayMenu":"never","showSubmenuIcon":false,"className":"nav"} /-->

<!-- wp:memorial-plugin/header-contacts /-->

<!-- wp:html -->
<button class="burger" id="burger" type="button" aria-label="<?php esc_attr_e( 'Menu', 'memorial-theme' ); ?>" aria-controls="nav" aria-expanded="false"><span></span></button>
<!-- /wp:html --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:html -->
<script>
/* The menu does not fit in a row — switch the header to the burger. Checked before painting, without flicker. */
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
	var compact = !(innerWidth > 900), two = false; // without the “less than” sign: WordPress processes the template HTML (wptexturize) and breaks the script after it
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
</script>
<!-- /wp:html -->
