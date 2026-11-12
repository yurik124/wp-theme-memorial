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
