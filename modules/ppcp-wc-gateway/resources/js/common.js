import DisplayManager from './common/display-manager/DisplayManager';
import moveWrappedElements from './common/wrapped-elements';

document.addEventListener( 'DOMContentLoaded', () => {
	// Wait for current execution context to end.
	setTimeout( function () {
		moveWrappedElements();
	}, 0 );

	// Initialize DisplayManager.
	const displayManager = new DisplayManager();

	jQuery( '*[data-ppcp-display]' ).each( ( index, el ) => {
		const rules = jQuery( el ).data( 'ppcpDisplay' );
		for ( const rule of rules ) {
			displayManager.addRule( rule );
		}
	} );

	displayManager.register();
} );
