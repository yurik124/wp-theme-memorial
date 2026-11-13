/**
 * Catalog → Product pages: which products get their own page (catalog/product-pages.php).
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardBody, Flex, Notice } from '@wordpress/components';
import Hint from '../shared/hint';
import { Page } from '@wordpress/admin-ui';

const API = '/memorial-plugin/v1/product-pages';

export default function ProductPages() {
	const [ data, setData ] = useState( null );
	const [ mode, setMode ] = useState( 'auto' );
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		apiFetch( { path: API } )
			.then( ( res ) => {
				setData( res );
				setMode( res.mode );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', text: e.message } )
			);
	}, [] );
	const fields = [
		{
			id: 'mode',
			label: __( 'Own page', 'memorial-plugin' ),
			type: 'text',
			elements: [
				{
					value: 'auto',
					label: __(
						'Only for products with a description (recommended)',
						'memorial-plugin'
					),
					description: __(
						'A product without a description opens as a large photo in the catalog, and its old address leads to the catalog section. These addresses are not in the sitemap.',
						'memorial-plugin'
					),
				},
				{
					value: 'all',
					label: __( 'For every product', 'memorial-plugin' ),
					description: __(
						'Every product has its own address, even one without a description.',
						'memorial-plugin'
					),
				},
				{
					value: 'none',
					label: __( 'No page', 'memorial-plugin' ),
					description: __(
						'No product has its own page: every product opens as a large photo in the catalog, and its address leads to the catalog section.',
						'memorial-plugin'
					),
				},
			],
		},
	];
	return (
		<Page
			title={ __( 'Product pages', 'memorial-plugin' ) }
			subTitle={
				data &&
				sprintf(
					/* translators: 1: products, 2: products with their own page. */
					__(
						'Products now: %1$d, with their own page: %2$d. To give a product a page, write its description in the editor. Attachment pages always lead to the post with the image or to the file.',
						'memorial-plugin'
					),
					data.total,
					data.withPage
				)
			}
		>
			<Flex
				direction="column"
				gap={ 4 }
				className="memorial-plugin-catalog"
			>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.text }
					</Notice>
				) }
				{ data && (
					<Card>
						<CardBody>
							<fieldset className="memorial-plugin-catalog__choice">
								<legend>{ fields[ 0 ].label }</legend>
								{ fields[ 0 ].elements.map( ( el ) => (
									<div
										key={ el.value }
										className="memorial-plugin-catalog__option"
									>
										<input
											type="radio"
											id={ `pp-${ el.value }` }
											name="memorial-plugin-product-pages"
											value={ el.value }
											checked={ mode === el.value }
											onChange={ () =>
												setMode( el.value )
											}
										/>
										<label htmlFor={ `pp-${ el.value }` }>
											{ el.label }
										</label>
										<Hint text={ el.description } />
									</div>
								) ) }
							</fieldset>
							<Flex
								justify="flex-end"
								className="memorial-plugin-catalog__buttons"
							>
								<Button
									variant="primary"
									isBusy={ busy }
									disabled={ busy || mode === data.mode }
									onClick={ async () => {
										setBusy( true );
										try {
											setData(
												await apiFetch( {
													path: API,
													method: 'POST',
													data: { mode },
												} )
											);
											setNotice( {
												status: 'success',
												text: __(
													'Saved.',
													'memorial-plugin'
												),
											} );
										} catch ( e ) {
											setNotice( {
												status: 'error',
												text: e.message,
											} );
										}
										setBusy( false );
									} }
								>
									{ __( 'Save', 'memorial-plugin' ) }
								</Button>
							</Flex>
						</CardBody>
					</Card>
				) }
			</Flex>
		</Page>
	);
}
