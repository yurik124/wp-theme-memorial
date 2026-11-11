/**
 * Catalog lists on DataViews: Products, Types and categories, Our works, Kinds of works
 * (catalog/catalog-manager.php, catalog/catalog-types-admin.php,
 * works/works-manager.php).
 */
import { createRoot } from '@wordpress/element';
import Products from './products';
import Works from './works';
import WorkTypes from './work-types';
import Types from './types';
import './style.scss';

const SCREENS = {
	products: Products,
	types: Types,
	works: Works,
	'work-types': WorkTypes,
};

const root = document.getElementById( 'memorial-plugin-catalog' );
if ( root ) {
	const Screen = SCREENS[ root.dataset.screen ] || Products;
	createRoot( root ).render( <Screen /> );
}
