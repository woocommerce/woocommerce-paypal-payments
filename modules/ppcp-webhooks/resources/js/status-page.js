import { setVisibleByClass } from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

document.addEventListener( 'DOMContentLoaded', () => {
	const resubscribeBtn = jQuery(
		PayPalCommerceGatewayWebhooksStatus.resubscribe.button
	);

	resubscribeBtn.click( async () => {
		resubscribeBtn.prop( 'disabled', true );

		const response = await fetch(
			PayPalCommerceGatewayWebhooksStatus.resubscribe.endpoint,
			{
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'content-type': 'application/json',
				},
				body: JSON.stringify( {
					nonce: PayPalCommerceGatewayWebhooksStatus.resubscribe
						.nonce,
				} ),
			}
		);

		const reportError = ( error ) => {
			const msg =
				PayPalCommerceGatewayWebhooksStatus.resubscribe.failureMessage +
				' ' +
				error;
			alert( msg );
		};

		if ( ! response.ok ) {
			try {
				const result = await response.json();
				reportError( result.data );
			} catch ( exc ) {
				console.error( exc );
				reportError( response.status );
			}
		}

		window.location.reload();
	} );

	function sleep( ms ) {
		return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	}

	const simulateBtn = jQuery(
		PayPalCommerceGatewayWebhooksStatus.simulation.start.button
	);
	simulateBtn.click( async () => {
		simulateBtn.prop( 'disabled', true );

		try {
			const response = await fetch(
				PayPalCommerceGatewayWebhooksStatus.simulation.start.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'content-type': 'application/json',
					},
					body: JSON.stringify( {
						nonce: PayPalCommerceGatewayWebhooksStatus.simulation
							.start.nonce,
					} ),
				}
			);

			const reportError = ( error ) => {
				const msg =
					PayPalCommerceGatewayWebhooksStatus.simulation.start
						.failureMessage +
					' ' +
					error;
				alert( msg );
			};

			if ( ! response.ok ) {
				try {
					const result = await response.json();
					reportError( result.data );
				} catch ( exc ) {
					console.error( exc );
					reportError( response.status );
				}

				return;
			}

			const showStatus = ( html ) => {
				let statusBlock = simulateBtn.siblings(
					'.ppcp-webhooks-status-text'
				);
				if ( ! statusBlock.length ) {
					statusBlock = jQuery(
						'<div class="ppcp-webhooks-status-text"></div>'
					).insertAfter( simulateBtn );
				}
				statusBlock.html( html );
			};

			simulateBtn.siblings( '.description' ).hide();

			showStatus(
				PayPalCommerceGatewayWebhooksStatus.simulation.state
					.waitingMessage +
					'<span class="spinner is-active" style="float: none;"></span>'
			);

			const delay = 2000;
			const retriesBeforeErrorMessage = 15;
			const maxRetries = 30;

			for ( let i = 0; i < maxRetries; i++ ) {
				await sleep( delay );

				const stateResponse = await fetch(
					PayPalCommerceGatewayWebhooksStatus.simulation.state
						.endpoint,
					{
						method: 'GET',
						credentials: 'same-origin',
					}
				);

				try {
					const result = await stateResponse.json();

					if ( ! stateResponse.ok || ! result.success ) {
						console.error(
							'Simulation state query failed: ' + result.data
						);
						continue;
					}

					const state = result.data.state;
					if (
						state ===
						PayPalCommerceGatewayWebhooksStatus.simulation.state
							.successState
					) {
						showStatus(
							'<span class="success">' +
								'✔️ ' +
								PayPalCommerceGatewayWebhooksStatus.simulation
									.state.successMessage +
								'</span>'
						);
						return;
					}
				} catch ( exc ) {
					console.error( exc );
				}

				if ( i === retriesBeforeErrorMessage ) {
					showStatus(
						'<span class="error">' +
							PayPalCommerceGatewayWebhooksStatus.simulation.state
								.tooLongDelayMessage +
							'</span>'
					);
				}
			}
		} finally {
			simulateBtn.prop( 'disabled', false );
		}
	} );

	const sandboxCheckbox = document.querySelector( '#ppcp-sandbox_on' );
	if ( sandboxCheckbox ) {
		const setWebhooksVisibility = ( show ) => {
			[
				'#field-webhook_status_heading',
				'#field-webhooks_list',
				'#field-webhooks_resubscribe',
				'#field-webhooks_simulate',
			].forEach( ( selector ) => {
				setVisibleByClass( selector, show, 'hide' );
			} );
		};

		const serverSandboxState =
			PayPalCommerceGatewayWebhooksStatus.environment === 'sandbox';
		setWebhooksVisibility( serverSandboxState === sandboxCheckbox.checked );
		sandboxCheckbox.addEventListener( 'click', () => {
			setWebhooksVisibility(
				serverSandboxState === sandboxCheckbox.checked
			);
		} );
	}
} );
