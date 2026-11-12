<?php
/**
 * Настройки категории каталога: пропорции фото, колонки, порядок разделов, якорь.
 * Список товаров — admin/catalog-views.php.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Настройки категории ---------- */
/**
 * Cat fields.
 *
 * @param WP_Term $term Term.
 */
function memorial_plugin_cat_fields( $term = null ) {
	$v     = fn( $k ) => $term ? get_term_meta( $term->term_id, $k, true ) : '';
	$ratio = $v( 'mem_ratio' );
	$rows  = array(
		array(
			'mem_ratio',
			__( 'Photo proportions', 'memorial-plugin' ),
			( '<select name="mem_ratio" id="mem_ratio"><option value="">' . esc_html__( 'As in the parent category', 'memorial-plugin' ) . '</option>' )
								. implode( '', array_map( fn( $k, $l ) => '<option value="' . esc_attr( $k ) . '"' . selected( $ratio, $k, false ) . '>' . esc_html( $l ) . '</option>', array_keys( memorial_plugin_ratios() ), memorial_plugin_ratios() ) )
								. '</select>',
			__( 'How to crop the photos in the cards. “Whole” — the photo is not cropped, with white margins at the edges.', 'memorial-plugin' ),
		),
		array( 'mem_cols', __( 'Columns on a computer', 'memorial-plugin' ), '<input type="number" min="2" max="6" name="mem_cols" id="mem_cols" value="' . esc_attr( $v( 'mem_cols' ) ) . '" placeholder="4" style="width:6em">', __( 'Always 2 on a phone.', 'memorial-plugin' ) ),
		array( 'mem_order', __( 'Section order', 'memorial-plugin' ), '<input type="number" name="mem_order" id="mem_order" value="' . esc_attr( $v( 'mem_order' ) ) . '" placeholder="0" style="width:6em">', __( 'Sections on the page go in ascending order of this number.', 'memorial-plugin' ) ),
		array( 'mem_anchor', __( 'Section anchor', 'memorial-plugin' ), '<input type="text" name="mem_anchor" id="mem_anchor" value="' . esc_attr( $v( 'mem_anchor' ) ) . ( '" placeholder="' . esc_attr__( 'for example: korziny', 'memorial-plugin' ) . '">' ), __( 'For links like #korziny. Can be left empty.', 'memorial-plugin' ) ),
	);
	foreach ( $rows as [$k, $label, $input, $help] ) {
		if ( $term ) {
			echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>' . $input . '<p class="description">' . esc_html( $help ) . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $input is the form control built above with escaped values.
		} else {
			echo '<div class="form-field"><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label>' . $input . '<p>' . esc_html( $help ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $input is the form control built above with escaped values.
		}
	}
	wp_nonce_field( 'mem_cat', 'mem_cat_nonce' );
}
add_action( 'mem_cat_add_form_fields', fn() => memorial_plugin_cat_fields() );
add_action( 'mem_cat_edit_form_fields', fn( $t ) => memorial_plugin_cat_fields( $t ) );

/**
 * Cat save.
 *
 * @param int $id ID.
 */
function memorial_plugin_cat_save( $id ) {
	if ( ! isset( $_POST['mem_cat_nonce'] ) || ! wp_verify_nonce( $_POST['mem_cat_nonce'], 'mem_cat' ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$vals = array(
		'mem_ratio'  => array_key_exists( $_POST['mem_ratio'] ?? '', memorial_plugin_ratios() ) ? $_POST['mem_ratio'] : '',
		'mem_cols'   => absint( $_POST['mem_cols'] ?? 0 ) ? min( 6, max( 2, absint( $_POST['mem_cols'] ) ) ) : '',
		'mem_order'  => ( $_POST['mem_order'] ?? '' ) === '' ? '' : (int) $_POST['mem_order'],
		'mem_anchor' => sanitize_title( $_POST['mem_anchor'] ?? '' ),
	);
	foreach ( $vals as $k => $v ) {
		'' === $v ? delete_term_meta( $id, $k ) : update_term_meta( $id, $k, $v );
	}
}
add_action( 'created_mem_cat', 'memorial_plugin_cat_save' );
add_action( 'edited_mem_cat', 'memorial_plugin_cat_save' );

/* Список товаров — DataViews (admin/catalog-views.php): цена, характеристики, категории, фото и порядок правятся там */
