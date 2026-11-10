/**
 * Страницы для сравнения — из файла URLS_FILE (его собирает workflow через wp-cli):
 * все опубликованные страницы, каталог, два раздела, два товара, 404.
 * Изменчивое на странице (карты, виджет отзывов Яндекса, кнопка «наверх») закрывается маской.
 */
const fs = require( 'fs' );
const { test, expect } = require( '@playwright/test' );

const urls = JSON.parse(
	fs.readFileSync( process.env.URLS_FILE || 'urls.json', 'utf8' )
);

const MASK = [ '.reviews__widget', '.map', 'iframe', '#totop', '.consent' ];

for ( const { name, path } of urls ) {
	test( name, async ( { page } ) => {
		await page.goto( path, { waitUntil: 'networkidle' } );
		// ленивые картинки: прокрутка до конца и обратно
		await page.evaluate( async () => {
			for ( let y = 0; y < document.body.scrollHeight; y += 600 ) {
				window.scrollTo( 0, y );
				await new Promise( ( r ) => window.setTimeout( r, 60 ) );
			}
			window.scrollTo( 0, 0 );
		} );
		await page.waitForLoadState( 'networkidle' );
		// пустая или сломанная страница — ошибка, а не «совпадает»
		await expect( page.locator( 'main#main' ) ).toBeAttached();
		const text = await page.locator( 'body' ).innerText();
		expect( text.length, 'на странице почти нет текста' ).toBeGreaterThan( 200 );
		await expect( page ).toHaveScreenshot( name + '.png', {
			fullPage: true,
			mask: MASK.map( ( s ) => page.locator( s ) ),
		} );
	} );
}
