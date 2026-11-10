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

const MASK = [ '.reviews__widget', '.map', 'iframe' ];
// «липкая» шапка встаёт на место; плавающие кнопка «наверх», плашка cookie и нижняя панель телефона
// на длинном снимке встают где придётся — их убираем
const STYLE =
	'.header{position:relative !important;top:0 !important;transform:none !important}' +
	'#totop,.totop,.consent,.mbar{display:none !important}' +
	'*,*::before,*::after{transition:none !important;animation:none !important}';

for ( const { name, path } of urls ) {
	test( name, async ( { page } ) => {
		await page.goto( path, { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: STYLE } );
		// ленивые картинки: грузим все сразу, прокручиваем до конца и обратно, ждём загрузки
		await page.evaluate( async () => {
			document
				.querySelectorAll( 'img[loading="lazy"]' )
				.forEach( ( img ) => ( img.loading = 'eager' ) );
			for ( let y = 0; y < document.body.scrollHeight; y += 400 ) {
				window.scrollTo( 0, y );
				await new Promise( ( r ) => window.setTimeout( r, 100 ) );
			}
			window.scrollTo( 0, 0 );
			await Promise.all(
				[ ...document.images ]
					.filter( ( img ) => ! img.complete )
					.map(
						( img ) =>
							new Promise( ( r ) => {
								img.onload = r;
								img.onerror = r;
								window.setTimeout( r, 5000 );
							} )
					)
			);
		} );
		await page.waitForLoadState( 'networkidle' );
		await page.waitForTimeout( 300 );
		// пустая или сломанная страница — ошибка, а не «совпадает»
		await expect( page.locator( 'main#main' ) ).toBeAttached();
		const text = await page.locator( 'body' ).innerText();
		expect( text.length, 'почти нет текста' ).toBeGreaterThan( 200 );
		await expect( page ).toHaveScreenshot( name + '.png', {
			fullPage: true,
			style: STYLE,
			mask: MASK.map( ( s ) => page.locator( s ) ),
		} );
	} );
}
