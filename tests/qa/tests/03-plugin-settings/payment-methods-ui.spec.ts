/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigUsa, gateways, products } from '../../resources';
import { paymentMethodsData } from './_test-data';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigUsa );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
} );

for ( const testData of paymentMethodsData.defaultUi ) {
	const { testKey, country, testGateways } = testData;

	test( `${ testKey } | Settings - ${ country } - Payment Methods - Default UI`, async ( {
		utils,
		pcpPaymentMethods,
		payPalUiClassic,
		product,
		cart,
		classicCart,
		checkout,
		classicCheckout,
	}, testInfo ) => {
		const snapshotName = testInfo.title;

		await pcpPaymentMethods.visit();
		// Snapshot the full screen
		await pcpPaymentMethods.snapshotContent(
			`${ snapshotName } - Content`
		);
		// Assert number of gateways
		await expect
			.soft( pcpPaymentMethods.paymentMethodContainers() )
			.toHaveCount( testGateways.length );

		// Assert expected gateways one by one
		for ( const testGateway of testGateways ) {
			const gateway = gateways[ testGateway ];
			const {
				titleInPcpSettings,
				hasSettingsButton,
				enabled: isGatewayEnabled,
				dependsOn,
			} = gateway;

			// current gateway toggle and settings button
			const gatewayToggle =
				pcpPaymentMethods.paymentMethodToggle( titleInPcpSettings );
			const gatewaySettingsButton =
				pcpPaymentMethods.paymentMethodSettingsButton(
					titleInPcpSettings
				);

			// Assert current gateway enabled state and settings button
			await expect( gatewaySettingsButton ).toBeVisible( {
				visible: hasSettingsButton,
			} );
			await expect( gatewayToggle ).toBeChecked( {
				checked: isGatewayEnabled,
			} );

			// Snapshot gateway settings modal window if it exists
			if ( hasSettingsButton ) {
				// Enable gateway if not by default
				if ( ! isGatewayEnabled ) {
					if ( dependsOn ) {
						// Ensure the leading gateway is enabled
						await pcpPaymentMethods
							.paymentMethodToggle(
								gateways[ dependsOn ].titleInPcpSettings
							)
							.check();
					}
					await gatewayToggle.check();
				}

				// Open gateway settings modal window
				await gatewaySettingsButton.click();
				// Snapshot gateway settings modal window
				await pcpPaymentMethods.snapshotModalWindow(
					`${ snapshotName } - Modal - ${ titleInPcpSettings }`
				);
				await pcpPaymentMethods.modalCloseButton().click();
			}
		}

		await utils.fillVisitorsCart( [ products.simple10 ] );

		await product.visit( products.simple10.slug );
		await product.payPalUi.snapshotClassicPayPalButtons(
			`${ snapshotName } - Frontend - Product`
		);

		await product.minicartContainer().hover();
		await expect
			.soft( payPalUiClassic.miniCartButtonContainer() )
			.not.toBeVisible();

		await cart.visit();
		await cart.payPalUi.snapshotBlockPayPalButtons(
			`${ snapshotName } - Frontend - Cart`
		);

		await classicCart.visit();
		await classicCart.payPalUi.snapshotClassicPayPalButtons(
			`${ snapshotName } - Frontend - Classic Cart`
		);

		await checkout.visit();
		await checkout.payPalUi.snapshotBlockPayPalButtons(
			`${ snapshotName } - Frontend - Checkout`
		);

		await classicCheckout.visit();
		await classicCheckout.paymentOption( 'PayPal' ).click();
		await classicCheckout.payPalUi.snapshotClassicPayPalButtons(
			`${ snapshotName } - Frontend - Classic checkout`
		);
	} );
}
