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
	const {
		testKey,
		testLabel,
		country,
		testGateways,
		expectedGatewayCount,
		expectedGroupCounts,
	} = testData;

	test( `${ testKey } | Settings - US - Payment Methods - Default UI — groups, toggles, modals, live PayPal buttons${
		testLabel ?? ''
	}`, async ( {
		utils,
		pcpPaymentMethods,
		payPalUiClassic,
		product,
		cart,
		classicCart,
		checkout,
		classicCheckout,
	} ) => {
		const simpleProduct = products.simple100;

		await test.step( 'Verify payment method groups and counts in settings', async () => {
			await pcpPaymentMethods.visit();
			await expect(
				pcpPaymentMethods.onlineCardPaymentsContainer(),
				`Assert online card payments container is visible for ${ country }`
			).toBeVisible();

			// Total item count (soft — continues on mismatch)
			await expect
				.soft(
					pcpPaymentMethods.paymentMethodContainers(),
					`Assert total payment methods count is ${ expectedGatewayCount } for ${ country }`
				)
				.toHaveCount( expectedGatewayCount );

			// Per-group item counts (soft — targeted diagnostics on count drift)
			await expect
				.soft(
					pcpPaymentMethods.payPalCheckoutMethodItems(),
					`Assert PayPal Checkout group has ${ expectedGroupCounts.paypalCheckout } items`
				)
				.toHaveCount( expectedGroupCounts.paypalCheckout );
			await expect
				.soft(
					pcpPaymentMethods.onlineCardPaymentMethodItems(),
					`Assert Online Card Payments group has ${ expectedGroupCounts.onlineCardPayments } items`
				)
				.toHaveCount( expectedGroupCounts.onlineCardPayments );
			await expect
				.soft(
					pcpPaymentMethods.alternativePaymentMethodItems(),
					`Assert Alternative Payment Methods group has ${ expectedGroupCounts.alternativePaymentMethods } items`
				)
				.toHaveCount( expectedGroupCounts.alternativePaymentMethods );
		} );

		await test.step( 'Verify each gateway toggle and settings modal behavior', async () => {
			for ( const testGateway of testGateways ) {
				const gateway = gateways[ testGateway ];
				const {
					title,
					titleInPcpSettings,
					titleInModal,
					hasSettingsButton,
					enabled: isGatewayEnabled,
					dependsOn,
				} = gateway;

				const expectedModalTitle = titleInModal ?? title;

				const gatewayToggle =
					pcpPaymentMethods.paymentMethodToggle( titleInPcpSettings );
				const gatewaySettingsButton =
					pcpPaymentMethods.paymentMethodSettingsButton(
						titleInPcpSettings
					);

				// Assert gateway title is displayed correctly
				const gatewayContainer =
					pcpPaymentMethods.paymentMethodContainer(
						titleInPcpSettings
					);
				await expect(
					gatewayContainer,
					`Assert gateway container for ${ titleInPcpSettings } is visible`
				).toBeVisible();
				const gatewayTitle = await gatewayContainer.textContent();
				expect(
					gatewayTitle,
					`Assert gateway ${ titleInPcpSettings } title is displayed`
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
					// Track which toggles are being enabled
					const dependencyTitle = dependsOn
						? gateways[ dependsOn ].titleInPcpSettings
						: null;
					let enabledDependency = false;
					let enabledGateway = false;

					if ( ! isGatewayEnabled ) {
						// Enable parent dependency if it is not yet on.
						if ( dependencyTitle ) {
							const depToggle =
								pcpPaymentMethods.paymentMethodToggle(
									dependencyTitle
								);
							if ( ! ( await depToggle.isChecked() ) ) {
								await depToggle.check();
								enabledDependency = true;
							}
						}
						await gatewayToggle.check();
						enabledGateway = true;
					}

					await gatewaySettingsButton.click();
					await expect(
						pcpPaymentMethods.modalWindow(),
						`Assert modal window is visible for ${ titleInPcpSettings }`
					).toBeVisible();
					await expect(
						pcpPaymentMethods.modalTitle(),
						`Assert modal title contains "${ expectedModalTitle }"`
					).toContainText( expectedModalTitle );
					await pcpPaymentMethods.modalCloseButton().click();

					// Restore toggle state (gateway first, then dependency).
					if ( enabledGateway ) {
						await gatewayToggle.uncheck();
					}
					if ( enabledDependency && dependencyTitle ) {
						await pcpPaymentMethods
							.paymentMethodToggle( dependencyTitle )
							.uncheck();
					}
				}
			}
		} );

		await test.step( 'Verify live PayPal button visibility on frontend', async () => {
			await utils.fillVisitorsCart( [ simpleProduct ] );

			await product.visit( simpleProduct.slug );
			await product.payPalUi.assertPayPalButtonsGatewayVisibleWithContent();

			await product.minicartContainer().hover();
			await expect
				.soft(
					payPalUiClassic.miniCartButtonContainer(),
					'Assert mini cart PayPal button is not visible in default settings'
				)
				.not.toBeVisible();

			await cart.visit();
			await cart.payPalUi.assertPayPalButtonsBlockVisibleWithContent();

			await classicCart.visit();
			await classicCart.payPalUi.assertPayPalButtonsGatewayVisibleWithContent();

			await checkout.visit();
			await checkout.payPalUi.assertPayPalButtonsBlockVisibleWithContent();

			await classicCheckout.visit();
			await classicCheckout.paymentOption( 'PayPal' ).click();
			await classicCheckout.payPalUi.assertPayPalButtonsGatewayVisibleWithContent();
		} );
	} );
}
