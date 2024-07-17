export const apmButtonsInit = ( config, selector = '.ppcp-button-apm' ) => {
	let selectorInContainer = selector;

	if ( window.ppcpApmButtons ) {
		return;
	}

	if ( config && config.button ) {
		// If it's separate gateways, modify wrapper to account for the individual buttons as individual APMs.
		const wrapper = config.button.wrapper;
		const isSeparateGateways =
			jQuery( wrapper ).children( 'div[class^="item-"]' ).length > 0;

		if ( isSeparateGateways ) {
			selector += `, ${ wrapper } div[class^="item-"]`;
			selectorInContainer += `, div[class^="item-"]`;
		}
	}

	window.ppcpApmButtons = new ApmButtons( selector, selectorInContainer );
};

export class ApmButtons {
	constructor( selector, selectorInContainer ) {
		this.selector = selector;
		this.selectorInContainer = selectorInContainer;
		this.containers = [];

		// Reloads button containers.
		this.reloadContainers();

		// Refresh button layout.
		jQuery( window )
			.resize( () => {
				this.refresh();
			} )
			.resize();

		jQuery( document ).on( 'ppcp-smart-buttons-init', () => {
			this.refresh();
		} );

		jQuery( document ).on(
			'ppcp-shown ppcp-hidden ppcp-enabled ppcp-disabled',
			( ev, data ) => {
				this.refresh();
				setTimeout( this.refresh.bind( this ), 200 );
			}
		);

		// Observes for new buttons.
		new MutationObserver(
			this.observeElementsCallback.bind( this )
		).observe( document.body, { childList: true, subtree: true } );
	}

	observeElementsCallback( mutationsList, observer ) {
		const observeSelector =
			this.selector +
			', .widget_shopping_cart, .widget_shopping_cart_content';

		let shouldReload = false;
		for ( const mutation of mutationsList ) {
			if ( mutation.type === 'childList' ) {
				mutation.addedNodes.forEach( ( node ) => {
					if ( node.matches && node.matches( observeSelector ) ) {
						shouldReload = true;
					}
				} );
			}
		}

		if ( shouldReload ) {
			this.reloadContainers();
			this.refresh();
		}
	}

	reloadContainers() {
		jQuery( this.selector ).each( ( index, el ) => {
			const parent = jQuery( el ).parent();
			if ( ! this.containers.some( ( $el ) => $el.is( parent ) ) ) {
				this.containers.push( parent );
			}
		} );
	}

	refresh() {
		for ( const container of this.containers ) {
			const $container = jQuery( container );

			// Check width and add classes
			const width = $container.width();

			$container.removeClass(
				'ppcp-width-500 ppcp-width-300 ppcp-width-min'
			);

			if ( width >= 500 ) {
				$container.addClass( 'ppcp-width-500' );
			} else if ( width >= 300 ) {
				$container.addClass( 'ppcp-width-300' );
			} else {
				$container.addClass( 'ppcp-width-min' );
			}

			// Check first apm button
			const $firstElement = $container.children( ':visible' ).first();

			// Assign margins to buttons
			$container.find( this.selectorInContainer ).each( ( index, el ) => {
				const $el = jQuery( el );

				if ( $el.is( $firstElement ) ) {
					$el.css( 'margin-top', `0px` );
					return true;
				}

				const minMargin = 11; // Minimum margin.
				const height = $el.height();
				const margin = Math.max(
					minMargin,
					Math.round( height * 0.3 )
				);
				$el.css( 'margin-top', `${ margin }px` );
			} );
		}
	}
}
