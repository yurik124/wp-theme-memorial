/**
 * Импорт товаров: ZIP с таблицей и фото через окно «Импорт», проверка, запись, откат; выгрузка в Excel.
 */
const { test, expect } = require( '@playwright/test' );
const { login, collectJsErrors } = require( './helpers' );

const api = ( page, options ) =>
	page.evaluate(
		( o ) =>
			window.wp.apiFetch( o ).catch( ( e ) => {
				throw new Error( JSON.stringify( e ) );
			} ),
		options
	);

// Два разных PNG 1×1.
const PNG = [
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==',
].map( ( b ) => Buffer.from( b, 'base64' ) );

function crc32( buf ) {
	let c = -1;
	for ( const b of buf ) {
		c ^= b;
		for ( let k = 0; k < 8; k++ ) {
			c = ( c >>> 1 ) ^ ( 0xedb88320 & -( c & 1 ) );
		}
	}
	return ( c ^ -1 ) >>> 0;
}

// ZIP без сжатия (метод 0), имена в UTF-8.
function zip( files ) {
	const local = [];
	const central = [];
	let offset = 0;
	for ( const [ name, data ] of Object.entries( files ) ) {
		const n = Buffer.from( name, 'utf8' );
		const crc = crc32( data );
		const h = Buffer.alloc( 30 );
		h.writeUInt32LE( 0x04034b50, 0 );
		h.writeUInt16LE( 20, 4 );
		h.writeUInt16LE( 0x800, 6 );
		h.writeUInt32LE( crc, 14 );
		h.writeUInt32LE( data.length, 18 );
		h.writeUInt32LE( data.length, 22 );
		h.writeUInt16LE( n.length, 26 );
		local.push( h, n, data );
		const c = Buffer.alloc( 46 );
		c.writeUInt32LE( 0x02014b50, 0 );
		c.writeUInt16LE( 20, 4 );
		c.writeUInt16LE( 20, 6 );
		c.writeUInt16LE( 0x800, 8 );
		c.writeUInt32LE( crc, 16 );
		c.writeUInt32LE( data.length, 20 );
		c.writeUInt32LE( data.length, 24 );
		c.writeUInt16LE( n.length, 28 );
		c.writeUInt32LE( offset, 42 );
		central.push( c, n );
		offset += 30 + n.length + data.length;
	}
	const cd = Buffer.concat( central );
	const end = Buffer.alloc( 22 );
	end.writeUInt32LE( 0x06054b50, 0 );
	end.writeUInt16LE( Object.keys( files ).length, 8 );
	end.writeUInt16LE( Object.keys( files ).length, 10 );
	end.writeUInt32LE( cd.length, 12 );
	end.writeUInt32LE( offset, 16 );
	return Buffer.concat( [ ...local, cd, end ] );
}

test( 'импорт: ZIP с таблицей и фото, проверка, запись, откат', async ( {
	page,
} ) => {
	test.setTimeout( 180_000 );
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto(
		'/wp-admin/edit.php?post_type=mem_product&page=mem-products'
	);
	await page.waitForFunction( () => window.wp && window.wp.apiFetch );
	const ts = Date.now();
	const typeName = 'Импорт e2e ' + ts;
	await api( page, {
		path: '/wp/v2/mem_cat',
		method: 'POST',
		data: { name: typeName },
	} );
	const sku1 = 'E2E-' + ts;
	const sku2 = 'E2F-' + ts;
	const csv =
		'﻿Артикул;Тип;Название;Цена;Фото;Alt\r\n' +
		`${ sku1 };${ typeName };Венок e2e;4 500;e2e-${ ts }.png;Венок спереди\r\n` +
		`${ sku2 };${ typeName };Второй e2e;;;\r\n`;
	const archive = zip( {
		'catalog.csv': Buffer.from( csv, 'utf8' ),
		[ `foto/e2e-${ ts }.png` ]: PNG[ 0 ],
		[ `foto/${ sku2 }/1.png` ]: PNG[ 1 ],
	} );

	await page.reload();
	await page.getByRole( 'button', { name: 'Import', exact: true } ).click();
	const modal = page.locator( '.memorial-plugin-import' );
	await expect( modal ).toBeVisible();
	await modal
		.locator( 'input[type=file]' )
		.first()
		.setInputFiles( {
			name: 'import.zip',
			mimeType: 'application/zip',
			buffer: archive,
		} );
	await expect( modal ).toContainText( 'catalog.csv' );
	await expect( modal ).toContainText( '2 photos' );
	await modal.getByRole( 'button', { name: 'Check the file' } ).click();
	await expect( modal ).toContainText( 'New: 2' );
	await modal.getByRole( 'button', { name: 'Import 2 products' } ).click();
	await expect( modal ).toContainText( 'New: 2', { timeout: 90_000 } );
	await expect(
		modal.getByRole( 'button', { name: 'Roll back this import' } )
	).toBeVisible();

	const data = await api( page, { path: '/memorial-plugin/v1/catalog/products' } );
	const p1 = data.items.find( ( i ) => i.sku === sku1 );
	const p2 = data.items.find( ( i ) => i.sku === sku2 );
	expect( p1 ).toBeTruthy();
	expect( p1.status ).toBe( 'draft' );
	expect( p1.price ).toBe( 4500 );
	expect( p1.photos ).toHaveLength( 1 );
	expect( p2.photos ).toHaveLength( 1 );
	const media = await api( page, {
		path: '/wp/v2/media/' + p1.photos[ 0 ].id + '?context=edit',
	} );
	expect( media.alt_text ).toBe( 'Венок спереди' );
	expect( media.title.raw ).toBe( 'Венок e2e' );

	// Тот же файл ещё раз: фото не загружаются заново, товары без изменений.
	const again = await page.evaluate(
		async ( [ text ] ) => {
			const body = new window.FormData();
			body.append(
				'table',
				new window.Blob( [ text ], { type: 'text/csv' } ),
				'catalog.csv'
			);
			body.append( 'files', '[]' );
			return window.wp.apiFetch( {
				path: '/memorial-plugin/v1/catalog/import/preview',
				method: 'POST',
				body,
			} );
		},
		[ csv.replace( `${ sku2 };${ typeName };Второй e2e;;;\r\n`, '' ) ]
	);
	expect( again.count.same ).toBe( 1 );

	await modal
		.getByRole( 'button', { name: 'Roll back this import' } )
		.click();
	await expect( modal ).toContainText( 'Rolled back' );
	const after = await api( page, {
		path: '/memorial-plugin/v1/catalog/products',
	} );
	expect( after.items.find( ( i ) => i.sku === sku1 ) ).toBeFalsy();
	expect( errors ).toEqual( [] );
} );

test( 'выгрузка и пример: Excel-файлы', async ( { page } ) => {
	await login( page );
	await page.goto(
		'/wp-admin/edit.php?post_type=mem_product&page=mem-products'
	);
	await page.waitForFunction( () => window.wp && window.wp.apiFetch );
	const sample = await api( page, {
		path: '/memorial-plugin/v1/catalog/import/sample?format=xlsx',
	} );
	expect( sample.filename ).toBe( 'catalog-import-example.xlsx' );
	expect( sample.file.startsWith( 'UEsD' ) ).toBe( true ); // «PK» — ZIP
	const exp = await api( page, {
		path: '/wp-abilities/v1/abilities/memorial-plugin/export-catalog/run?input[format]=xlsx',
	} );
	expect( exp.filename ).toMatch( /\.xlsx$/ );
	expect( exp.file.startsWith( 'UEsD' ) ).toBe( true );
} );
