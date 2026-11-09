/**
 * Проверка сборки: DataViews (пакет dataviews, вход /wp) и Page из пакета admin-ui
 * собираются внутрь плагина и работают на WordPress 7.1.x. Удаляется на этапе 4.
 */
import { createRoot, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataViews } from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';

const DATA = [
	{ id: 1, title: 'Wreath', price: 3500 },
	{ id: 2, title: 'Basket', price: 4200 },
	{ id: 3, title: 'Coffin', price: 18900 },
];

const FIELDS = [
	{
		id: 'title',
		label: __( 'Title', 'memorial-plugin' ),
		enableHiding: false,
		enableGlobalSearch: true,
	},
	{
		id: 'price',
		label: __( 'Price', 'memorial-plugin' ),
		type: 'integer',
	},
];

function App() {
	const [ view, setView ] = useState( {
		type: 'table',
		fields: [ 'price' ],
		titleField: 'title',
		perPage: 10,
		page: 1,
		sort: { field: 'title', direction: 'asc' },
	} );
	// Сортировка и страницы — вручную: проверяем только сам компонент DataViews.
	const { data, paginationInfo } = useMemo( () => {
		const dir = view.sort?.direction === 'desc' ? -1 : 1;
		const key = view.sort?.field || 'title';
		const sorted = [ ...DATA ].sort( ( a, b ) =>
			a[ key ] > b[ key ] ? dir : -dir
		);
		const start = ( view.page - 1 ) * view.perPage;
		return {
			data: sorted.slice( start, start + view.perPage ),
			paginationInfo: {
				totalItems: DATA.length,
				totalPages: Math.ceil( DATA.length / view.perPage ),
			},
		};
	}, [ view ] );
	return (
		<Page title={ __( 'DataViews check', 'memorial-plugin' ) }>
			<DataViews
				data={ data }
				fields={ FIELDS }
				view={ view }
				onChangeView={ setView }
				paginationInfo={ paginationInfo }
				defaultLayouts={ { table: {}, grid: {}, list: {} } }
				getItemId={ ( item ) => String( item.id ) }
			/>
		</Page>
	);
}

const root = document.getElementById( 'memorial-plugin-dataviews-check' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
