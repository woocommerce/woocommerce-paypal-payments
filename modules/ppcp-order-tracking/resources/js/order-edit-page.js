document.addEventListener( 'DOMContentLoaded', () => {
	const config = PayPalCommerceGatewayOrderTrackingInfo;
	if ( ! typeof PayPalCommerceGatewayOrderTrackingInfo ) {
		console.error( 'tracking cannot be set.' );
		return;
	}

	const includeAllItemsCheckbox =
		document.getElementById( 'include-all-items' );
	const shipmentsWrapper =
		'#ppcp_order-tracking .ppcp-tracking-column.shipments';
	const captureId = document.querySelector( '.ppcp-tracking-capture_id' );
	const orderId = document.querySelector( '.ppcp-tracking-order_id' );
	const carrier = document.querySelector( '.ppcp-tracking-carrier' );
	const carrierNameOther = document.querySelector(
		'.ppcp-tracking-carrier_name_other'
	);

	function toggleLineItemsSelectbox() {
		const selectContainer = document.getElementById(
			'items-select-container'
		);
		includeAllItemsCheckbox?.addEventListener( 'change', function () {
			selectContainer.style.display = includeAllItemsCheckbox.checked
				? 'none'
				: 'block';
		} );
	}

	function toggleShipment() {
		jQuery( document ).on(
			'click',
			'.ppcp-shipment-header',
			function ( event ) {
				const shipmentContainer =
					event.target.closest( '.ppcp-shipment' );
				const shipmentInfo = shipmentContainer.querySelector(
					'.ppcp-shipment-info'
				);

				shipmentContainer.classList.toggle( 'active' );
				shipmentContainer.classList.toggle( 'closed' );
				shipmentInfo.classList.toggle( 'hidden' );
			}
		);
	}

	function toggleShipmentUpdateButtonDisabled() {
		jQuery( document ).on(
			'change',
			'.ppcp-shipment-status',
			function ( event ) {
				const shipmentSelectbox = event.target;
				const shipment = shipmentSelectbox.closest( '.ppcp-shipment' );
				const updateShipmentButton =
					shipment.querySelector( '.update_shipment' );
				const selectedValue = shipmentSelectbox.value;

				updateShipmentButton.classList.remove( 'button-disabled' );
			}
		);
	}

	function toggleLoaderVisibility() {
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
	}

	function toggleOtherCarrierName() {
		jQuery( carrier ).on( 'change', function () {
			const hiddenHtml = carrierNameOther.parentNode;
			if ( carrier.value === 'OTHER' ) {
				hiddenHtml.classList.remove( 'hidden' );
			} else if ( ! hiddenHtml.classList.contains( 'hidden' ) ) {
				hiddenHtml.classList.add( 'hidden' );
			}
		} );
	}

	function handleAddShipment() {
		jQuery( document ).on( 'click', '.submit_tracking_info', function () {
			const trackingNumber = document.querySelector(
				'.ppcp-tracking-tracking_number'
			);
			const status = document.querySelector( '.ppcp-tracking-status' );
			const submitButton = document.querySelector(
				'.submit_tracking_info'
			);
			const items = document.querySelector( '.ppcp-tracking-items' );
			const noShipemntsContainer = document.querySelector(
				'.ppcp-tracking-no-shipments'
			);

			const checkedItems =
				includeAllItemsCheckbox?.checked || ! items
					? 0
					: Array.from( items.selectedOptions ).map(
							( option ) => option.value
					  );

			toggleLoaderVisibility();
			fetch( config.ajax.tracking_info.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: config.ajax.tracking_info.nonce,
					capture_id: captureId ? captureId.value : null,
					tracking_number: trackingNumber
						? trackingNumber.value
						: null,
					status: status ? status.value : null,
					carrier: carrier ? carrier.value : null,
					carrier_name_other: carrierNameOther
						? carrierNameOther.value
						: null,
					order_id: orderId ? orderId.value : null,
					items: checkedItems,
				} ),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					toggleLoaderVisibility();

					if ( ! data.success || ! data.data.shipment ) {
						jQuery(
							"<p class='error tracking-info-message'>" +
								data.data.message +
								'</p>'
						).insertAfter( submitButton );
						setTimeout(
							() => jQuery( '.tracking-info-message' ).remove(),
							3000
						);
						submitButton.removeAttribute( 'disabled' );
						console.error( data );
						throw Error( data.data.message );
					}

					jQuery(
						"<p class='success tracking-info-message'>" +
							data.data.message +
							'</p>'
					).insertAfter( submitButton );
					setTimeout(
						() => jQuery( '.tracking-info-message' ).remove(),
						3000
					);
					jQuery( data.data.shipment ).appendTo( shipmentsWrapper );
					if ( noShipemntsContainer ) {
						noShipemntsContainer.parentNode.removeChild(
							noShipemntsContainer
						);
					}
					trackingNumber.value = '';
				} );
		} );
	}

	function handleUpdateShipment() {
		jQuery( document ).on( 'click', '.update_shipment', function ( event ) {
			const updateShipment = event.target;
			const parentElement = updateShipment.parentNode.parentNode;
			const shipmentStatus = parentElement.querySelector(
				'.ppcp-shipment-status'
			);
			const shipmentTrackingNumber = parentElement.querySelector(
				'.ppcp-shipment-tacking_number'
			);
			const shipmentCarrier = parentElement.querySelector(
				'.ppcp-shipment-carrier'
			);
			const shipmentCarrierNameOther = parentElement.querySelector(
				'.ppcp-shipment-carrier-other'
			);

			toggleLoaderVisibility();
			fetch( config.ajax.tracking_info.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: config.ajax.tracking_info.nonce,
					capture_id: captureId ? captureId.value : null,
					tracking_number: shipmentTrackingNumber
						? shipmentTrackingNumber.value
						: null,
					status: shipmentStatus ? shipmentStatus.value : null,
					carrier: shipmentCarrier ? shipmentCarrier.value : null,
					carrier_name_other: shipmentCarrierNameOther
						? shipmentCarrierNameOther.value
						: null,
					order_id: orderId ? orderId.value : null,
					action: 'update',
				} ),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					toggleLoaderVisibility();

					if ( ! data.success ) {
						jQuery(
							"<p class='error tracking-info-message'>" +
								data.data.message +
								'</p>'
						).insertAfter( updateShipment );
						setTimeout(
							() => jQuery( '.tracking-info-message' ).remove(),
							3000
						);
						console.error( data );
						throw Error( data.data.message );
					}

					jQuery(
						"<p class='success tracking-info-message'>" +
							data.data.message +
							'</p>'
					).insertAfter( updateShipment );
					setTimeout(
						() => jQuery( '.tracking-info-message' ).remove(),
						3000
					);
				} );
		} );
	}

	handleAddShipment();
	handleUpdateShipment();
	toggleLineItemsSelectbox();
	toggleShipment();
	toggleShipmentUpdateButtonDisabled();
	toggleOtherCarrierName();
} );
