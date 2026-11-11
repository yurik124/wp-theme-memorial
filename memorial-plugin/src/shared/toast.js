/**
 * Short message at the bottom of the screen after a command (any admin page, not only the editor),
 * also read out by screen readers.
 */
import { speak } from '@wordpress/a11y';

/**
 * @param {string} text   Message.
 * @param {string} status success | warning | error.
 */
export default function toast( text, status = 'success' ) {
	speak( text, status === 'error' ? 'assertive' : 'polite' );
	const el = document.createElement( 'div' );
	el.className = 'memorial-plugin-toast is-' + status;
	el.setAttribute( 'aria-hidden', 'true' ); // already announced by speak()
	el.textContent = text;
	document.body.append( el );
	window.setTimeout( () => el.classList.add( 'is-gone' ), 4500 );
	window.setTimeout( () => el.remove(), 5000 );
}
