/**
 * Catalog → Products → Import: a table (Excel or CSV) and photos (files or a ZIP archive).
 *
 * Steps: files → check (nothing is written, catalog/catalog-import.php) → the photos that are needed
 * are uploaded one by one → the rows are applied in portions → the result, a report and a rollback.
 */
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	DropZone,
	Flex,
	FormFileUpload,
	Modal,
	Notice,
} from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import Hint from '../shared/hint';
import { download, downloadBase64 } from '../shared/abilities';
import { unzip, sha1 } from './unzip';

const API = '/memorial-plugin/v1/catalog/import';

/**
 * Path of a file as the server compares it.
 *
 * @param {string} p Path.
 * @return {string} Lower case, forward slashes.
 */
const norm = ( p ) =>
	p
		.replace( /\\/g, '/' )
		.replace( /^\.?\//, '' )
		.toLowerCase();

const isTable = ( name ) => /\.(xlsx|csv)$/i.test( name );
const isPhoto = ( name ) => /\.(jpe?g|png|webp|gif|avif)$/i.test( name );

/**
 * Words of the result of a row.
 *
 * @return {Object} action => label.
 */
const actionLabels = () => ( {
	create: __( 'New', 'memorial-plugin' ),
	update: __( 'Update', 'memorial-plugin' ),
	same: __( 'No change', 'memorial-plugin' ),
	error: __( 'Error — skipped', 'memorial-plugin' ),
} );

// Download an example file (Excel with the guide and the types of the site, CSV, ZIP with photos).
function Examples( { onError } ) {
	const get = ( format ) =>
		apiFetch( { path: `${ API }/sample?format=${ format }` } )
			.then( ( r ) => downloadBase64( r.filename, r.file, r.mime ) )
			.catch( ( e ) => onError( e.message ) );
	return (
		<Flex gap={ 3 } justify="flex-start" wrap>
			<span>{ __( 'Example file:', 'memorial-plugin' ) }</span>
			<Button variant="link" onClick={ () => get( 'xlsx' ) }>
				Excel
			</Button>
			<Button variant="link" onClick={ () => get( 'csv' ) }>
				CSV
			</Button>
			<Button variant="link" onClick={ () => get( 'zip' ) }>
				{ __( 'Archive with photos (ZIP)', 'memorial-plugin' ) }
			</Button>
			<Hint
				text={ __(
					'The first row of the table — the headers, one product per row. The archive (ZIP): the table in the root and the folder “foto” with the photos named in the “Photos” column. Or folders named as the SKUs (foto/M-1024/1.jpg, 2.jpg) with the “Photos” cell empty. The example Excel file has the guide to every column and the types and categories of the site.',
					'memorial-plugin'
				) }
			/>
		</Flex>
	);
}

// Rows of the check: what each row of the file will do.
function PreviewTable( { rows } ) {
	const labels = actionLabels();
	const [ view, setView ] = useState( {
		type: 'table',
		fields: [ 'n', 'sku', 'action', 'changes', 'photos', 'problems' ],
		titleField: 'title',
		perPage: 20,
		page: 1,
		filters: [],
		search: '',
		sort: { field: 'n', direction: 'asc' },
	} );
	const fields = useMemo(
		() => [
			{
				id: 'n',
				label: __( 'Row', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
			},
			{
				id: 'title',
				label: __( 'Product title', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				filterBy: false,
			},
			{
				id: 'sku',
				label: __( 'SKU', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				filterBy: false,
			},
			{
				id: 'action',
				label: __( 'What happens', 'memorial-plugin' ),
				type: 'text',
				elements: Object.entries( labels ).map(
					( [ value, label ] ) => ( {
						value,
						label,
					} )
				),
				filterBy: { operators: [ 'isAny' ] },
				render: ( { item } ) => labels[ item.action ],
			},
			{
				id: 'changes',
				label: __( 'What changes', 'memorial-plugin' ),
				type: 'text',
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) => item.changes.join( ', ' ),
			},
			{
				id: 'photos',
				label: __( 'Photos', 'memorial-plugin' ),
				type: 'text',
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) =>
					item.photos
						? [
								item.photos.media &&
									sprintf(
										/* translators: %d: number of photos. */
										__(
											'in the library: %d',
											'memorial-plugin'
										),
										item.photos.media
									),
								item.photos.file &&
									sprintf(
										/* translators: %d: number of photos. */
										__(
											'to upload: %d',
											'memorial-plugin'
										),
										item.photos.file
									),
								item.photos.missing &&
									sprintf(
										/* translators: %d: number of photos. */
										__(
											'not found: %d',
											'memorial-plugin'
										),
										item.photos.missing
									),
							]
								.filter( Boolean )
								.join( ', ' )
						: '—',
			},
			{
				id: 'problems',
				label: __( 'Errors and notes', 'memorial-plugin' ),
				type: 'text',
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) => (
					<span
						className={
							item.errors.length
								? 'memorial-plugin-import__err'
								: ''
						}
					>
						{ [ ...item.errors, ...item.warnings ].join( ' ' ) }
					</span>
				),
			},
		],
		[ labels ]
	);
	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( rows, view, fields ),
		[ rows, view, fields ]
	);
	return (
		<DataViews
			data={ data }
			fields={ fields }
			view={ view }
			onChangeView={ setView }
			paginationInfo={ paginationInfo }
			getItemId={ ( item ) => String( item.n ) }
			defaultLayouts={ { table: {} } }
		/>
	);
}

/**
 * Report of the import as CSV.
 *
 * @param {Object[]} rows Results.
 * @return {string} CSV.
 */
function report( rows ) {
	const labels = actionLabels();
	const cell = ( v ) => '"' + String( v ?? '' ).replace( /"/g, '""' ) + '"';
	const head = [
		__( 'Row', 'memorial-plugin' ),
		__( 'SKU', 'memorial-plugin' ),
		__( 'Title', 'memorial-plugin' ),
		__( 'What happened', 'memorial-plugin' ),
		__( 'Errors and notes', 'memorial-plugin' ),
	];
	return (
		'﻿' +
		[
			head,
			...rows.map( ( r ) => [
				r.n,
				r.sku,
				r.title,
				labels[ r.action ] || r.action,
				( r.errors || [] ).join( ' ' ),
			] ),
		]
			.map( ( r ) => r.map( cell ).join( ';' ) )
			.join( '\r\n' )
	);
}

export default function ImportModal( { onClose, onDone, last } ) {
	const [ step, setStep ] = useState( 'files' );
	const [ table, setTable ] = useState( null );
	const [ photos, setPhotos ] = useState( [] );
	const [ publish, setPublish ] = useState( false );
	const [ preview, setPreview ] = useState( null );
	const [ progress, setProgress ] = useState( null );
	const [ results, setResults ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ undo, setUndo ] = useState( null );
	const [ lastImport, setLastImport ] = useState( last );

	// Files chosen or dropped: a table, photos, ZIP archives (opened here, in the browser).
	const addFiles = async ( list ) => {
		setError( '' );
		setBusy( true );
		let tbl = null;
		const add = [];
		try {
			for ( const f of Array.from( list ) ) {
				if ( /\.zip$/i.test( f.name ) ) {
					const entries = await unzip( f );
					// The table closest to the root of the archive.
					const tables = entries
						.filter( ( e ) => isTable( e.path ) )
						.sort(
							( a, b ) =>
								a.path.split( '/' ).length -
								b.path.split( '/' ).length
						);
					if ( tables.length ) {
						tbl = await tables[ 0 ].file();
					}
					entries
						.filter( ( e ) => isPhoto( e.path ) )
						.forEach( ( e ) =>
							add.push( {
								path: e.path,
								size: e.size,
								file: e.file,
							} )
						);
				} else if ( isTable( f.name ) ) {
					tbl = f;
				} else if ( isPhoto( f.name ) ) {
					add.push( {
						path: f.webkitRelativePath || f.name,
						size: f.size,
						file: async () => f,
					} );
				}
			}
		} catch ( e ) {
			setError(
				e.message === 'zip64'
					? __(
							'The archive is too large (more than 4 GB). Split the photos into several archives.',
							'memorial-plugin'
						)
					: __(
							'The archive could not be opened. Make it again as a ZIP (not RAR or 7z).',
							'memorial-plugin'
						)
			);
		}
		if ( tbl ) {
			setTable( tbl );
		}
		if ( add.length ) {
			setPhotos( ( prev ) => {
				const map = new Map(
					prev.map( ( p ) => [ norm( p.path ), p ] )
				);
				add.forEach( ( p ) => map.set( norm( p.path ), p ) );
				return [ ...map.values() ];
			} );
		}
		setBusy( false );
	};

	const check = async () => {
		setError( '' );
		setBusy( true );
		try {
			const list = [];
			for ( let i = 0; i < photos.length; i++ ) {
				setProgress( {
					done: i,
					total: photos.length,
					label: __( 'Reading the photos…', 'memorial-plugin' ),
				} );
				const p = photos[ i ];
				list.push( {
					path: p.path,
					size: p.size,
					hash: p.hash ?? ( p.hash = await sha1( await p.file() ) ),
				} );
			}
			setProgress( null );
			const body = new window.FormData();
			body.append( 'table', table, table.name );
			body.append( 'files', JSON.stringify( list ) );
			body.append( 'publish', publish ? '1' : '0' );
			const res = await apiFetch( {
				path: `${ API }/preview`,
				method: 'POST',
				body,
			} );
			setPreview( res );
			setStep( 'preview' );
		} catch ( e ) {
			setError( e.message );
		} finally {
			setProgress( null );
			setBusy( false );
		}
	};

	const run = async () => {
		setError( '' );
		setStep( 'run' );
		const out = [];
		const byPath = new Map( photos.map( ( p ) => [ norm( p.path ), p ] ) );
		try {
			// Photos one per request: the limits of the hosting on the size of a request do not matter.
			for ( let i = 0; i < preview.need.length; i++ ) {
				setProgress( {
					done: i,
					total: preview.need.length,
					label: __( 'Uploading the photos…', 'memorial-plugin' ),
				} );
				const p = byPath.get( preview.need[ i ] );
				const body = new window.FormData();
				body.append( 'token', preview.token );
				body.append( 'path', preview.need[ i ] );
				body.append( 'file', await p.file() );
				try {
					await apiFetch( {
						path: `${ API }/media`,
						method: 'POST',
						body,
					} );
				} catch ( e ) {
					out.push( {
						n: '',
						sku: '',
						title: preview.need[ i ],
						action: 'error',
						errors: [ e.message ],
					} );
				}
			}
			let offset = 0;
			let done = false;
			while ( ! done ) {
				setProgress( {
					done: offset,
					total: preview.rows.length,
					label: __( 'Saving the products…', 'memorial-plugin' ),
				} );
				const res = await apiFetch( {
					path: `${ API }/run`,
					method: 'POST',
					data: { token: preview.token, offset },
				} );
				out.push( ...res.rows );
				offset = res.next;
				done = res.done;
			}
			setResults( out );
			setStep( 'done' );
			onDone();
		} catch ( e ) {
			// What was saved stays; the log of the import has it, so it can be rolled back.
			setResults( out );
			setError( e.message );
			setStep( 'done' );
			onDone();
		} finally {
			setProgress( null );
		}
	};

	const rollback = async () => {
		setBusy( true );
		setError( '' );
		try {
			const res = await apiFetch( {
				path: `${ API }/rollback`,
				method: 'POST',
			} );
			setUndo(
				sprintf(
					/* translators: 1: number of products moved to the trash, 2: number of restored products. */
					__(
						'Rolled back: %1$d new products moved to the trash, %2$d products returned as they were.',
						'memorial-plugin'
					),
					res.trashed,
					res.restored
				)
			);
			setLastImport( null );
			onDone();
		} catch ( e ) {
			setError( e.message );
		} finally {
			setBusy( false );
		}
	};

	const count = ( list, action ) =>
		list.filter( ( r ) => r.action === action ).length;
	const todo = preview ? preview.count.create + preview.count.update : 0;

	return (
		<Modal
			title={ __( 'Import products', 'memorial-plugin' ) }
			onRequestClose={ step === 'run' ? () => {} : onClose }
			isDismissible={ step !== 'run' }
			size="large"
			className="memorial-plugin-import"
		>
			<Flex direction="column" gap={ 4 } justify="flex-start">
				{ error && (
					<Notice status="error" onRemove={ () => setError( '' ) }>
						{ error }
					</Notice>
				) }
				{ undo && (
					<Notice status="success" isDismissible={ false }>
						{ undo }
					</Notice>
				) }

				{ step === 'files' && (
					<>
						{ lastImport?.canUndo && ! undo && (
							<Notice status="info" isDismissible={ false }>
								<Flex gap={ 3 } wrap justify="flex-start">
									<span>
										{ sprintf(
											/* translators: 1: date and time, 2: number of new products, 3: number of updated products. */
											__(
												'Last import %1$s: new — %2$d, updated — %3$d.',
												'memorial-plugin'
											),
											new Date(
												lastImport.time * 1000
											).toLocaleString( 'ru-RU' ),
											lastImport.created,
											lastImport.updated
										) }
									</span>
									<Button
										variant="secondary"
										isDestructive
										onClick={ rollback }
										isBusy={ busy }
										disabled={ busy }
									>
										{ __(
											'Roll back the last import',
											'memorial-plugin'
										) }
									</Button>
								</Flex>
							</Notice>
						) }
						<Examples onError={ setError } />
						<div className="memorial-plugin-import__drop">
							<DropZone
								label={ __(
									'Drop the table, photos or a ZIP archive here',
									'memorial-plugin'
								) }
								onFilesDrop={ addFiles }
							/>
							<p>
								{ __(
									'Drag the table (Excel or CSV) and the photos or a ZIP archive here, or choose them:',
									'memorial-plugin'
								) }
							</p>
							<Flex gap={ 3 } justify="flex-start" wrap>
								<FormFileUpload
									accept=".xlsx,.csv,.zip"
									onChange={ ( e ) =>
										addFiles( e.target.files )
									}
									render={ ( { openFileDialog } ) => (
										<Button
											variant="secondary"
											onClick={ openFileDialog }
											disabled={ busy }
										>
											{ __(
												'Choose the table or an archive',
												'memorial-plugin'
											) }
										</Button>
									) }
								/>
								<FormFileUpload
									accept="image/*,.zip"
									multiple
									onChange={ ( e ) =>
										addFiles( e.target.files )
									}
									render={ ( { openFileDialog } ) => (
										<Button
											variant="secondary"
											onClick={ openFileDialog }
											disabled={ busy }
										>
											{ __(
												'Choose photos',
												'memorial-plugin'
											) }
										</Button>
									) }
								/>
							</Flex>
							<ul className="memorial-plugin-import__files">
								<li>
									{ __( 'Table:', 'memorial-plugin' ) }{ ' ' }
									<strong>
										{ table
											? table.name
											: __(
													'not chosen',
													'memorial-plugin'
												) }
									</strong>
								</li>
								<li>
									{ sprintf(
										/* translators: %d: number of photos. */
										_n(
											'%d photo',
											'%d photos',
											photos.length,
											'memorial-plugin'
										),
										photos.length
									) }
									{ photos.length > 0 && (
										<>
											{ ' ' }
											<Button
												variant="link"
												onClick={ () =>
													setPhotos( [] )
												}
											>
												{ __(
													'Clear',
													'memorial-plugin'
												) }
											</Button>
										</>
									) }
								</li>
							</ul>
						</div>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __( 'Publish at once', 'memorial-plugin' ) }
							help={ __(
								'New products are shown on the site at once. Otherwise they are hidden until you check them.',
								'memorial-plugin'
							) }
							checked={ publish }
							onChange={ setPublish }
						/>
						{ progress && <Progress { ...progress } /> }
						<Flex justify="flex-end">
							<Button variant="tertiary" onClick={ onClose }>
								{ __( 'Cancel', 'memorial-plugin' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ check }
								disabled={ ! table || busy }
								isBusy={ busy }
							>
								{ __( 'Check the file', 'memorial-plugin' ) }
							</Button>
						</Flex>
					</>
				) }

				{ step === 'preview' && preview && (
					<>
						<Notice
							status={ preview.count.error ? 'warning' : 'info' }
							isDismissible={ false }
						>
							{ sprintf(
								/* translators: 1: new, 2: to update, 3: without changes, 4: with errors. */
								__(
									'Nothing is saved yet. New: %1$d, to update: %2$d, without changes: %3$d, with errors (will be skipped): %4$d.',
									'memorial-plugin'
								),
								preview.count.create,
								preview.count.update,
								preview.count.same,
								preview.count.error
							) }
							{ preview.need.length > 0 &&
								' ' +
									sprintf(
										/* translators: %d: number of photos. */
										__(
											'Photos to upload: %d.',
											'memorial-plugin'
										),
										preview.need.length
									) }
						</Notice>
						{ preview.unknown.length > 0 && (
							<Notice status="warning" isDismissible={ false }>
								{ sprintf(
									/* translators: %s: list of columns. */
									__(
										'These columns are not known and are skipped: %s.',
										'memorial-plugin'
									),
									preview.unknown.join( ', ' )
								) }
							</Notice>
						) }
						<PreviewTable rows={ preview.rows } />
						<Flex justify="flex-end">
							<Button
								variant="tertiary"
								onClick={ () => setStep( 'files' ) }
							>
								{ __( 'Back', 'memorial-plugin' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ run }
								disabled={ ! todo }
							>
								{ sprintf(
									/* translators: %d: number of products. */
									_n(
										'Import %d product',
										'Import %d products',
										todo,
										'memorial-plugin'
									),
									todo
								) }
							</Button>
						</Flex>
					</>
				) }

				{ step === 'run' && progress && <Progress { ...progress } /> }

				{ step === 'done' && (
					<>
						<Notice
							status={
								count( results, 'error' )
									? 'warning'
									: 'success'
							}
							isDismissible={ false }
						>
							{ sprintf(
								/* translators: 1: new, 2: updated, 3: without changes, 4: skipped with errors. */
								__(
									'Done. New products: %1$d, updated: %2$d, without changes: %3$d, skipped with errors: %4$d.',
									'memorial-plugin'
								),
								count( results, 'create' ),
								count( results, 'update' ),
								count( results, 'same' ),
								count( results, 'error' )
							) }
						</Notice>
						<ul className="memorial-plugin-import__errors">
							{ results
								.filter( ( r ) => r.errors?.length )
								.slice( 0, 50 )
								.map( ( r, i ) => (
									<li key={ i }>
										{ r.n
											? sprintf(
													/* translators: %d: row number. */
													__(
														'Row %d:',
														'memorial-plugin'
													),
													r.n
												) + ' '
											: '' }
										{ r.title } — { r.errors.join( ' ' ) }
									</li>
								) ) }
						</ul>
						<Flex justify="flex-end" wrap>
							<Button
								variant="tertiary"
								onClick={ () =>
									download(
										'import-report.csv',
										report( results )
									)
								}
							>
								{ __(
									'Download the report',
									'memorial-plugin'
								) }
							</Button>
							{ ! undo && (
								<Button
									variant="secondary"
									isDestructive
									onClick={ rollback }
									isBusy={ busy }
									disabled={ busy }
								>
									{ __(
										'Roll back this import',
										'memorial-plugin'
									) }
								</Button>
							) }
							<Button variant="primary" onClick={ onClose }>
								{ __( 'Close', 'memorial-plugin' ) }
							</Button>
						</Flex>
					</>
				) }
			</Flex>
		</Modal>
	);
}

// Progress of a long step: a bar and “12 of 40”.
function Progress( { done, total, label } ) {
	return (
		<div className="memorial-plugin-import__progress" role="status">
			<span>
				{ label }{ ' ' }
				{ sprintf(
					/* translators: 1: done, 2: total. */
					__( '%1$d of %2$d', 'memorial-plugin' ),
					done,
					total
				) }
			</span>
			<progress max={ total || 1 } value={ done } />
		</div>
	);
}
