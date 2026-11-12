<?php
/**
 * Карточки пакетов услуг (главная и шорткод [mem_packages] для страниц).
 * Ключевые позиции (отмечаются в настройках) — списком с лавровыми листками, весь состав с ценами — свёрнут («Весь состав и цены»).
 * Данные — «Мемориал → Цены → Пакеты похорон».
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Packages html.
 */
function memorial_plugin_packages_html() {
	$s    = memorial_plugin_prices( 'packages' );
	$pkgs = memorial_plugin_packages();
	if ( ! $pkgs ) {
		return;
	}
	$tel = memorial_plugin_tel();
	?>
	<div class="pkgs">
		<?php
		foreach ( $pkgs as $p ) :
			$feat  = ! empty( $p['featured'] );
			$total = array_sum( array_column( $p['items'], 1 ) );
			?>
		<article class="pkg<?php echo $feat ? ' pkg--feat' : ''; ?> reveal">
			<?php
			if ( $feat && ! empty( $s['badge'] ) ) :
				?>
				<span class="pkg__badge"><?php echo memorial_plugin_esc_t( $s['badge'] ); ?></span><?php endif; ?>
			<h3><?php echo esc_html( $p['name'] ); ?></h3>
			<?php
			if ( $total ) :
				?>
				<p class="pkg__total"><?php echo esc_html( memorial_plugin_rub( $total ) ); ?></p><?php endif; ?>
			<?php
			if ( $p['items'] ) :
				$keys = array_filter( $p['items'], fn( $it ) => ! empty( $it[2] ) );
				// ничего не отмечено в настройках — берём главное по названию, иначе первые четыре.
				if ( ! $keys ) {
					$keys = array_filter( $p['items'], fn( $it ) => preg_match( '/^(Гроб|Крест|Катафалк|Венок|Услуги по захоронению)/u', $it[0] ) );
				}
				if ( ! $keys ) {
					$keys = array_slice( $p['items'], 0, 4 );
				}
				$mark = memorial_plugin_laurel( 'leaf' );
				$mark = '' !== $mark ? $mark : memorial_plugin_icon( 'check' );
				?>
				<?php
				$div = memorial_plugin_laurel( 'divider' );
				if ( $div ) :
					?>
					<span class="pkg__div" aria-hidden="true"><?php echo $div; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG of the laurel. ?></span><?php endif; ?>
			<ul class="pkg__key">
				<?php
				foreach ( $keys as $it ) :
					?>
					<li><?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG mark. ?><span><?php echo esc_html( $it[0] ); ?></span></li><?php endforeach; ?>
			</ul>
			<details class="pkg__list">
			<summary><?php esc_html_e( 'The full contents and prices', 'memorial-plugin' ); ?> (<?php echo count( $p['items'] ); ?>)</summary>
			<ul>
				<?php foreach ( $p['items'] as $it ) : ?>
				<li><span><?php echo esc_html( $it[0] ); ?></span>
					<?php
					if ( $it[1] ) :
						?>
					<b><?php echo esc_html( memorial_plugin_rub( $it[1] ) ); ?></b><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
			</details>
			<?php endif; ?>
			<?php
			if ( $tel ) :
				?>
				<a class="btn <?php echo $feat ? 'btn--gold' : 'btn--line'; ?>" href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo memorial_plugin_esc_t( $s['btn'] ? $s['btn'] : __( 'Order', 'memorial-plugin' ) ); ?></a><?php endif; ?>
		</article>
		<?php endforeach; ?>
	</div>
	<?php
}

/** Карточка «Кремация» в том же виде, что пакеты похорон (данные — «Мемориал → Цены → Пакет кремации») */
function memorial_plugin_cremation_card_html() {
	$c    = memorial_plugin_prices( 'cremation' );
	$rows = array_values( array_filter( (array) ( $c['rows'] ?? array() ), fn( $r ) => trim( $r['name'] ?? '' ) !== '' ) );
	if ( ! $rows ) {
		return '';
	}
	$total = memorial_plugin_cremation_total();
	$keys  = array_filter( $rows, fn( $r ) => ! empty( $r['key'] ) );
	if ( ! $keys ) {
		$keys = count( $rows ) <= 5 ? $rows : array_slice( $rows, 0, 4 );
	}
	$mark = memorial_plugin_laurel( 'leaf' );
	$mark = '' !== $mark ? $mark : memorial_plugin_icon( 'check' );
	$tel  = memorial_plugin_tel();
	$page = function_exists( 'memorial_plugin_guess_page' ) ? (int) memorial_plugin_guess_page( array( 'ritualnye-uslugi/kremacziya', 'kremacziya', 'kremaciya', 'kremaciya-v-belgorode' ) ) : 0;
	$page = $page && 'publish' === get_post_status( $page ) && get_the_ID() !== $page ? get_permalink( $page ) : '';
	$btn  = memorial_plugin_prices( 'packages' )['btn'] ?? '';
	$h    = '<article class="pkg pkg--crem reveal"><h3>' . esc_html( '' !== trim( $c['card_title'] ?? '' ) ? trim( $c['card_title'] ) : __( 'Cremation', 'memorial-plugin' ) ) . '</h3>';
	if ( $total ) {
		$h .= '<p class="pkg__total">' . esc_html( memorial_plugin_rub( $total ) ) . '</p>';
	}
	$div = memorial_plugin_laurel( 'divider' );
	if ( $div ) {
		$h .= '<span class="pkg__div" aria-hidden="true">' . $div . '</span>';
	}
	$h .= '<ul class="pkg__key">';
	foreach ( $keys as $r ) {
		$h .= '<li>' . $mark . '<span>' . esc_html( $r['name'] ) . '</span></li>';
	}
	$h .= '</ul><details class="pkg__list"><summary>' . __( 'The full contents and prices', 'memorial-plugin' ) . ' (' . count( $rows ) . ')</summary><ul>';
	foreach ( $rows as $r ) {
		$h .= '<li><span>' . esc_html( $r['name'] ) . '</span>' . ( ! empty( $r['price'] ) ? '<b>' . esc_html( memorial_plugin_rub( (int) $r['price'] ) ) . '</b>' : '' ) . '</li>';
	}
	$h .= '</ul></details>';
	if ( ! empty( $c['total'] ) ) {
		$h .= '<p class="pkg__note">' . esc_html( $c['total'] ) . '</p>';
	}
	if ( $page ) {
		$h .= '<a class="link-more pkg__more" href="' . esc_url( $page ) . '">' . esc_html__( 'More about cremation', 'memorial-plugin' ) . ' <span aria-hidden="true">→</span></a>';
	}
	if ( $tel ) {
		$h .= '<a class="btn btn--line" href="tel:' . esc_attr( $tel ) . '">' . esc_html( memorial_plugin_t( $btn ? $btn : __( 'Order', 'memorial-plugin' ) ) ) . '</a>';
	}
	return $h . '</article>';
}

add_shortcode(
	'mem_packages',
	function ( $atts = array() ) {
		$a = shortcode_atts( array( 'cremation' => '' ), $atts, 'mem_packages' );
		ob_start();
		memorial_plugin_packages_html();
		$html = ob_get_clean();
		// карточка кремации: на странице «Цены» (если включено в настройках) или по cremation="1"; cremation="0" — никогда.
		$slug = (string) get_post_field( 'post_name', get_the_ID() );
		$crem = '1' === $a['cremation'] || ( '' === $a['cremation'] && ! empty( memorial_plugin_prices( 'cremation' )['card'] ) && in_array( $slug, array( 'ceny', 'tseny', 'prices' ), true ) );
		$card = $crem && $html ? memorial_plugin_cremation_card_html() : '';
		if ( $card ) {
			$html = preg_replace( '~</div>\s*$~', $card . '</div>', $html, 1 );
		}
		return '<div class="mem-pkgs">' . $html . '</div>';
	}
);
