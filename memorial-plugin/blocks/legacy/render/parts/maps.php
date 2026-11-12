<?php
/**
 * Maps.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
/* Карты: вкладка на каждый адрес, карта грузится по кнопке (не тормозит страницу) */
$places = memorial_plugin_addrs();
if ( ! $places ) {
	return;
}
$one = count( $places ) === 1;
?>
<div class="maps">
	<?php if ( ! $one ) : ?>
	<div class="maps__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Addresses', 'memorial-plugin' ); ?>">
		<?php
		foreach ( $places as $n => $p ) :
			$on = 0 === $n;
			?>
		<button class="maps__tab" type="button" role="tab" id="mt-<?php echo (int) $n; ?>"
				aria-controls="mp-<?php echo (int) $n; ?>" aria-selected="<?php echo $on ? 'true' : 'false'; ?>"
				tabindex="<?php echo $on ? '0' : '-1'; ?>">
			<?php echo esc_html( ! empty( $p['title'] ) ? $p['title'] : memorial_plugin_short_addr( $p['addr'] ) ); ?>
		</button>
	<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<?php foreach ( $places as $n => $p ) : ?>
	<div class="map" id="mp-<?php echo (int) $n; ?>"
		<?php
		if ( ! $one ) {
			echo ' role="tabpanel" aria-labelledby="mt-' . (int) $n . '"';}
		?>
		data-src="<?php echo esc_url( memorial_plugin_map_src( $p['map'] ?? '', $p['addr'] ) ); ?>"<?php echo 0 === $n ? '' : ' hidden'; ?>>
		<p class="map__addr"><?php echo esc_html( memorial_plugin_addr( $p['addr'] ) ); ?></p>
		<?php
		if ( ! empty( $p['hours'] ) ) :
			?>
			<p class="map__hours"><?php echo esc_html( $p['hours'] ); ?></p><?php endif; ?>
		<button class="btn btn--gold map__load" type="button"><?php esc_html_e( 'Show the map', 'memorial-plugin' ); ?></button>
	</div>
	<?php endforeach; ?>
</div>
