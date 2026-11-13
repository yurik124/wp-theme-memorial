/**
 * Round “?” button: an explanation that opens on hover, focus or click and takes no room in the form.
 */
import { useState } from '@wordpress/element';
import { Popover } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Hint( { text, label } ) {
	const [ open, setOpen ] = useState( false );
	const [ pinned, setPinned ] = useState( false );
	const [ anchor, setAnchor ] = useState( null );
	if ( ! text ) {
		return null;
	}
	const isOpen = open || pinned;
	return (
		<span
			className="memorial-plugin-hint"
			onMouseEnter={ () => setOpen( true ) }
			onMouseLeave={ () => setOpen( false ) }
		>
			<button
				type="button"
				ref={ setAnchor }
				className="memorial-plugin-hint__btn"
				aria-label={ __( 'Explanation', 'memorial-plugin' ) }
				aria-expanded={ isOpen }
				onFocus={ () => setOpen( true ) }
				onBlur={ () => {
					setOpen( false );
					setPinned( false );
				} }
				onClick={ ( e ) => {
					// Inside a <label>: the click must not toggle the checkbox or move the focus to the field.
					e.preventDefault();
					e.stopPropagation();
					setPinned( ! pinned );
				} }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Escape' ) {
						setOpen( false );
						setPinned( false );
					}
				} }
			>
				?
			</button>
			{ label && (
				<span className="memorial-plugin-hint__label">{ label }</span>
			) }
			{ isOpen && anchor && (
				<Popover
					anchor={ anchor }
					placement="top-start"
					focusOnMount={ false }
					offset={ 8 }
					className="memorial-plugin-hint__pop"
					onClose={ () => {
						setOpen( false );
						setPinned( false );
					} }
				>
					<div role="tooltip">{ text }</div>
				</Popover>
			) }
		</span>
	);
}
