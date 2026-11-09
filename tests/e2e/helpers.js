/**
 * Общее для проверок e2e: вход в админку, сбор ошибок JS, признаки ошибок PHP на странице.
 */
const PHP_ERROR =
	/(Fatal error|Parse error|Warning|Notice|Deprecated)<\/b>:|Uncaught (Error|TypeError)/;

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.WP_USERNAME || 'admin' );
	await page.fill( '#user_pass', process.env.WP_PASSWORD || 'password' );
	await Promise.all( [
		page.waitForURL( /wp-admin/ ),
		page.click( '#wp-submit' ),
	] );
}

function collectJsErrors( page ) {
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' ) {
			errors.push( m.text() );
		}
	} );
	return errors;
}

module.exports = { PHP_ERROR, login, collectJsErrors };
