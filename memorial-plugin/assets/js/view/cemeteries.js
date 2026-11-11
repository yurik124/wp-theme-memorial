/**
 * Cemeteries block: filter by burial status and the map loaded when “More and map” is opened.
 * Interactivity API (script module); the markup and the directives are in places/places.php.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'memorial-plugin/cemeteries', {
	state: {
		// The chip of the chosen filter is pressed.
		get isSelected() {
			const context = getContext();
			return context.filter === context.value;
		},
		// A cemetery with another status is hidden while a filter is chosen.
		get isHidden() {
			const context = getContext();
			return !! context.filter && context.filter !== context.status;
		},
	},
	actions: {
		select() {
			const context = getContext();
			context.filter = context.value;
		},
		// The map frame is added only when the details are opened (no third-party requests before).
		loadMap() {
			const { ref } = getElement();
			const box = ref.querySelector( '[data-map-src]' );
			if ( ! ref.open || ! box || box.firstChild ) {
				return;
			}
			const frame = document.createElement( 'iframe' );
			frame.src = box.dataset.mapSrc;
			frame.title = box.dataset.mapTitle || '';
			frame.loading = 'lazy';
			frame.allowFullscreen = true;
			box.append( frame );
		},
	},
} );
