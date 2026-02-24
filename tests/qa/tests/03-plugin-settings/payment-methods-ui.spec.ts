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

	test.fixme(
		`${ testKey } | Settings - ${ country } - Payment Methods - Default UI`,
		async (
			{
				utils,
				pcpPaymentMethods,
				payPalUiClassic,
				product,
				cart,
				classicCart,
				checkout,
				classicCheckout,
			},
			testInfo
		) => {
			const simpleProduct = products.simple100;

			await pcpPaymentMethods.visit();
			await expect(
				pcpPaymentMethods.onlineCardPaymentsContainer(),
				`Assert online card payments container is visible for ${ country }`
			).toBeVisible();
			await expect
				.soft(
					pcpPaymentMethods.paymentMethodContainers(),
					`Assert payment methods count is ${ testGateways.length } for ${ country }`
				)
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

				const gatewayToggle =
					pcpPaymentMethods.paymentMethodToggle( titleInPcpSettings );
				const gatewaySettingsButton =
					pcpPaymentMethods.paymentMethodSettingsButton(
						titleInPcpSettings
					);

				// Assert gateway title is displayed correctly
				const gatewayContainer = pcpPaymentMethods.paymentMethodContainer(
					titleInPcpSettings
				);
				await expect(
					gatewayContainer,
					`Assert gateway container for ${ titleInPcpSettings } is visible for ${ country }`
				).toBeVisible();
				const gatewayTitle = await gatewayContainer.textContent();
				expect(
					gatewayTitle,
					`Assert gateway ${ titleInPcpSettings } title is displayed for ${ country }`
				).toContain( titleInPcpSettings );

				await expect(
					gatewaySettingsButton,
					`Assert settings button visibility is ${ hasSettingsButton } for ${ titleInPcpSettings }`
				).toBeVisible( { visible: hasSettingsButton } );
				await expect(
					gatewayToggle,
					`Assert gateway ${ titleInPcpSettings } checked state is ${ isGatewayEnabled }`
				).toBeChecked( { checked: isGatewayEnabled } );

				if ( hasSettingsButton ) {
					if ( ! isGatewayEnabled ) {
						if ( dependsOn ) {
							await pcpPaymentMethods
								.paymentMethodToggle(
									gateways[ dependsOn ].titleInPcpSettings
								)
								.check();
						}
						await gatewayToggle.check();
					}

					await gatewaySettingsButton.click();
					await expect(
						pcpPaymentMethods.modalWindow(),
						`Assert modal window is visible for ${ titleInPcpSettings }`
					).toBeVisible();
					await pcpPaymentMethods.modalCloseButton().click();
				}
			}

			await utils.fillVisitorsCart( [ simpleProduct ] );

			await product.visit( simpleProduct.slug );
			await expect(
				product.payPalUi.payPalButtonsBlockContainer(),
				'Assert PayPal button container is visible on product page'
			).toBeVisible();

			await product.minicartContainer().hover();
			await expect
				.soft(
					payPalUiClassic.miniCartButtonContainer(),
					'Assert mini cart PayPal button is not visible when not expected'
				)
				.not.toBeVisible();

			await cart.visit();
			await expect(
				cart.payPalUi.payPalButtonsBlockContainer(),
				'Assert PayPal button container is visible on cart'
			).toBeVisible();

			await classicCart.visit();
			await expect(
				classicCart.payPalUi.payPalButtonsBlockContainer(),
				'Assert PayPal button container is visible on classic cart'
			).toBeVisible();

			await checkout.visit();
			await expect(
				checkout.payPalUi.payPalButtonsBlockContainer(),
				'Assert PayPal button container is visible on checkout'
			).toBeVisible();

			await classicCheckout.visit();
			await classicCheckout.paymentOption( 'PayPal' ).click();
			await expect(
				classicCheckout.payPalUi.payPalButtonsBlockContainer(),
				'Assert PayPal button container is visible on classic checkout'
			).toBeVisible();
		}
	);
}
