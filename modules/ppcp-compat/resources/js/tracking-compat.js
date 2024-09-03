document.addEventListener( 'DOMContentLoaded', () => {
	const config = PayPalCommerceGatewayOrderTrackingCompat;

	const orderTrackingContainerId = 'ppcp_order-tracking';
	const orderTrackingContainerSelector =
		'#ppcp_order-tracking .ppcp-tracking-column.shipments';
	const gzdSaveButton = document.getElementById( 'order-shipments-save' );
	const loadLocation =
		location.href + ' ' + orderTrackingContainerSelector + '>*';
	const gzdSyncEnabled = config.gzd_sync_enabled;
	const wcShipmentSyncEnabled = config.wc_shipment_sync_enabled;
	const wcShippingTaxSyncEnabled = config.wc_shipping_tax_sync_enabled;
	const wcShipmentSaveButton = document.querySelector(
		'#woocommerce-shipment-tracking .button-save-form'
	);
	const wcShipmentTaxBuyLabelButtonSelector =
		'.components-modal__screen-overlay .label-purchase-modal__sidebar .purchase-section button.components-button';
	const dhlGenerateLabelButton =
		document.getElementById( 'dhl-label-button' );

	const toggleLoaderVisibility = function () {
		const loader = document.querySelector( '.ppcp-tracking-loader' );
		if ( loader ) {
			if (
				loader.style.display === 'none' ||
				loader.style.display === ''
			) {
				loader.style.display = 'block';
			} else {
				loader.style.display = 'none';
			}
		}
	};

	const waitForTrackingUpdate = function ( elementToCheck ) {
		if ( elementToCheck.css( 'display' ) !== 'none' ) {
			setTimeout( () => waitForTrackingUpdate( elementToCheck ), 100 );
		} else {
			jQuery( orderTrackingContainerSelector ).load(
				loadLocation,
				'',
				function () {
					toggleLoaderVisibility();
				}
			);
		}
	};

	const waitForButtonRemoval = function ( button ) {
		if ( document.body.contains( button ) ) {
			setTimeout( () => waitForButtonRemoval( button ), 100 );
		} else {
			jQuery( orderTrackingContainerSelector ).load(
				loadLocation,
				'',
				function () {
					toggleLoaderVisibility();
				}
			);
		}
	};

	if (
		gzdSyncEnabled &&
		typeof gzdSaveButton !== 'undefined' &&
		gzdSaveButton != null
	) {
		gzdSaveButton.addEventListener( 'click', function ( event ) {
			toggleLoaderVisibility();
			waitForTrackingUpdate( jQuery( '#order-shipments-save' ) );
		} );
	}

	if (
		wcShipmentSyncEnabled &&
		typeof wcShipmentSaveButton !== 'undefined' &&
		wcShipmentSaveButton != null
	) {
		wcShipmentSaveButton.addEventListener( 'click', function ( event ) {
			toggleLoaderVisibility();
			waitForTrackingUpdate( jQuery( '#shipment-tracking-form' ) );
		} );
	}

	if (
		typeof dhlGenerateLabelButton !== 'undefined' &&
		dhlGenerateLabelButton != null
	) {
		dhlGenerateLabelButton.addEventListener( 'click', function ( event ) {
			toggleLoaderVisibility();
			waitForButtonRemoval( dhlGenerateLabelButton );
		} );
	}

	jQuery( document ).on(
		'mouseover mouseout',
		'#dhl_delete_label',
		function ( event ) {
			jQuery( '#ppcp-shipment-status' )
				.val( 'CANCELLED' )
				.trigger( 'change' );
			document.querySelector( '.update_shipment' ).click();
		}
	);

	if (
		wcShippingTaxSyncEnabled &&
		typeof wcShippingTaxSyncEnabled !== 'undefined'
	) {
		document.addEventListener( 'click', function ( event ) {
			const wcShipmentTaxBuyLabelButton = event.target.closest(
				wcShipmentTaxBuyLabelButtonSelector
			);

			if ( wcShipmentTaxBuyLabelButton ) {
				toggleLoaderVisibility();
				setTimeout( function () {
					jQuery( orderTrackingContainerSelector ).load(
						loadLocation,
						'',
						function () {
							toggleLoaderVisibility();
						}
					);
				}, 10000 );
			}
		} );
	}
} );
