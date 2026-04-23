/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	products,
	pcpConfigDefault,
	storeConfigClassic,
	guests,
	debitOrCreditCard,
	acdc,
} from '../../resources';

const guest = guests.usa;

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( storeConfigClassic );
} );

test.describe( 'Standard Payments', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configurePcp( pcpConfigDefault );
	} );

	test( 'PCP-1037 | Standard Payments - Enable PayPal Gateway @Critical', async ( {
		utils,
		standardPayments,
		product,
		classicCart,
		classicCheckout,
	} ) => {
		const testProduct = products.simple10;
		const stepsData = [
			{
				name: 'disabled gateway',
				checkboxAction: 'uncheck',
				enabledState: false,
			},
			{
				name: 'enabled gateway',
				checkboxAction: 'check',
				enabledState: true,
			},
		];

		await utils.fillVisitorsCart( [ testProduct ] );

		for ( const step of stepsData ) {
			await test.step( step.name, async () => {
				await standardPayments.visit();
				await standardPayments
					.enableGatewayCheckbox()
					[ step.checkboxAction ]();
				await standardPayments.saveChanges();
				await expect(
					standardPayments.enableGatewayCheckbox()
				).toBeChecked( { checked: step.enabledState } );

				await product.visit( testProduct.slug );
				await product.ppui.assertPayPalButtonVisibility(
					step.enabledState
				);

				await classicCart.visit();
				await classicCart.ppui.assertPayPalButtonVisibility(
					step.enabledState
				);

				await classicCheckout.visit();
				await classicCheckout.ppui.assertPayPalButtonVisibility(
					step.enabledState
				);
			} );
		}
	} );

	test( 'PCP-1038 | Standard Payments - Adding/changing Title', async ( {
		utils,
		standardPayments,
		classicCheckout,
	} ) => {
		const testText = 'Test PayPal Title';
		await utils.fillVisitorsCart( [ products.simple10 ] );

		await standardPayments.visit();
		await standardPayments.titleInput().fill( testText );
		await standardPayments.saveChanges();

		await classicCheckout.visit();
		await expect( classicCheckout.ppui.payPalGatewayText() ).toContainText(
			testText
		);
	} );

	test( 'PCP-1039 | Standard payment - Adding/changing Description', async ( {
		utils,
		standardPayments,
		classicCheckout,
	} ) => {
		const testText = 'Test PayPal Description';
		await utils.fillVisitorsCart( [ products.simple10 ] );

		await standardPayments.visit();
		await standardPayments.descriptionInput().fill( testText );
		await standardPayments.saveChanges();

		await classicCheckout.visit();
		await expect(
			classicCheckout.ppui.payPalGatewayDescription()
		).toContainText( testText );
	} );

	test( 'PCP-1042 | Standard Payments - Adding/changing Brand name', async ( {
		utils,
		standardPayments,
		classicCheckout,
	} ) => {
		const testText = 'Test Brand Name';
		await utils.fillVisitorsCart( [ products.simple10 ] );

		await standardPayments.visit();
		await standardPayments.brandNameInput().fill( testText );
		await standardPayments.saveChanges();

		await classicCheckout.visit();
		await classicCheckout.fillCheckoutForm( guest );
		const payPal = await classicCheckout.ppui.openPayPalPupup();
		await expect( payPal.cancelLink() ).toContainText( testText );
	} );

	test.describe( 'Debit or Credit Card', () => {
		test.beforeAll(
			async ( { utils } ) =>
				await utils.pcpPaymentMethodIsEnabled(
					debitOrCreditCard.method
				)
		);

		test( 'PCP-1046 | Standard payment - Send checkout billing data to card fields - Use WC checkout form data @Critical', async ( {
			standardPayments,
			classicCheckout,
			utils,
		} ) => {
			await standardPayments.setup( {
				classicCheckoutButtonLayout: 'Vertical',
				sendCheckoutBillingData:
					'Use WC checkout form data (do not show any address fields)',
			} );

			await utils.fillVisitorsCart( [ products.simple10 ] );

			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guest );
			await classicCheckout.ppui.payPalGateway().click();
			await classicCheckout.ppui.debitOrCreditCardButton().click();

			await expect(
				classicCheckout.ppui.debitOrCreditCardNumberInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardExpirationInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardCSCInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPoweredByText()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPoweredByLogo()
			).toBeVisible();
		} );

		test( 'PCP-1047 | Standard Payments - Send checkout billing data to card fields = Name and postal code', async ( {
			utils,
			standardPayments,
			classicCheckout,
		} ) => {
			await standardPayments.setup( {
				classicCheckoutButtonLayout: 'Vertical',
				sendCheckoutBillingData: 'Request only name and postal code',
			} );

			await utils.fillVisitorsCart( [ products.simple10 ] );

			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guest );
			await classicCheckout.ppui.payPalGateway().click();
			await classicCheckout.ppui.debitOrCreditCardButton().click();

			await expect(
				classicCheckout.ppui.debitOrCreditCardNumberInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardExpirationInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardCSCInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardFirstNameInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardLastNameInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardStreetInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardApartmentInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardCityInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardStateInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardZipCodeInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPoweredByText()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPoweredByLogo()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPhoneInput()
			).not.toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardEmailInput()
			).not.toBeVisible();
		} );

		test( 'PCP-1048 | Standard Payments - Send checkout billing data to card fields = All address fields', async ( {
			utils,
			standardPayments,
			classicCheckout,
		} ) => {
			await standardPayments.setup( {
				classicCheckoutButtonLayout: 'Vertical',
				sendCheckoutBillingData:
					'Do not use WC checkout form data (request all address fields)',
			} );

			await utils.fillVisitorsCart( [ products.simple10 ] );

			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guest );
			await classicCheckout.ppui.payPalGateway().click();
			await classicCheckout.ppui.debitOrCreditCardButton().click();

			await expect(
				classicCheckout.ppui.debitOrCreditCardNumberInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardExpirationInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardCSCInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardFirstNameInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardLastNameInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardStreetInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardApartmentInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardCityInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardStateInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardZipCodeInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPhoneInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardEmailInput()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPoweredByText()
			).toBeVisible();
			await expect(
				classicCheckout.ppui.debitOrCreditCardPoweredByLogo()
			).toBeVisible();
		} );

		test.afterAll(
			async ( { utils } ) =>
				await utils.pcpPaymentMethodIsEnabled( acdc.method )
		);
	} );

	test( 'PCP-1472 | Standard Payments - Disabling smart button locations', async ( {
		standardPayments,
		classicCheckout,
		classicCart,
		product,
		visitorWooCommerceApi,
		cart,
		checkout,
	} ) => {
		const testLocations = [
			{
				locations: [],
				product: false,
				classicCheckout: false,
				classicCart: false,
				miniCart: false,
				expressCart: false,
				expressCheckout: false,
			},
			{
				locations: [ 'Single Product' ],
				product: true,
				classicCheckout: false,
				classicCart: false,
				miniCart: false,
				expressCart: false,
				expressCheckout: false,
			},
			{
				locations: [ 'Single Product', 'Classic Checkout' ],
				product: true,
				classicCheckout: true,
				classicCart: false,
				miniCart: false,
				expressCart: false,
				expressCheckout: false,
			},
			{
				locations: [
					'Single Product',
					'Classic Checkout',
					'Classic Cart',
				],
				product: true,
				classicCheckout: true,
				classicCart: true,
				miniCart: false,
				expressCart: false,
				expressCheckout: false,
			},
			{
				locations: [
					'Single Product',
					'Classic Checkout',
					'Classic Cart',
					'Mini Cart',
				],
				product: true,
				classicCheckout: true,
				classicCart: true,
				miniCart: true,
				expressCart: false,
				expressCheckout: false,
			},
			{
				locations: [
					'Single Product',
					'Classic Checkout',
					'Classic Cart',
					'Mini Cart',
					'Cart',
				],
				product: true,
				classicCheckout: true,
				classicCart: true,
				miniCart: true,
				expressCart: true,
				expressCheckout: false,
			},
			{
				locations: [
					'Single Product',
					'Classic Checkout',
					'Classic Cart',
					'Mini Cart',
					'Cart',
					'Express Checkout',
				],
				product: true,
				classicCheckout: true,
				classicCart: true,
				miniCart: true,
				expressCart: true,
				expressCheckout: true,
			},
		];
		test.setTimeout( 5 * 60 * 1000 );
		await standardPayments.visit();
		await standardPayments.removeItemsFromSelectBox(
			'Smart Button Locations',
			[
				'Single Product',
				'Classic Checkout',
				'Classic Cart',
				'Mini Cart',
				'Cart',
				'Express Checkout',
			]
		);
		await standardPayments.saveChanges();

		for ( const tested of testLocations ) {
			await standardPayments.addItemsToSelectBox(
				'Smart Button Locations',
				tested.locations
			);
			await standardPayments.saveChanges();
			await expect(
				standardPayments.wcErrorsContainer()
			).not.toBeVisible();

			await visitorWooCommerceApi.clearCart();

			await product.visit( products.simple10.slug );
			await product.addToCartButton().click();
			await product.ppui.assertPayPalButtonVisibility( tested.product );
			await product.ppui.assertMiniCartPayPalButtonVisibility(
				tested.miniCart
			);

			await classicCheckout.visit();
			await classicCheckout.ppui.assertPayPalButtonVisibility(
				tested.classicCheckout
			);

			await classicCart.visit();
			await classicCart.ppui.assertPayPalButtonVisibility(
				tested.classicCart
			);

			await cart.visit();
			const payPalButtonsExpressCart =
				await cart.ppui.collectBlockSmartButtons();
			if ( tested.expressCart ) {
				await expect( payPalButtonsExpressCart.length ).toBeGreaterThan(
					0
				);
			} else {
				await expect( payPalButtonsExpressCart.length ).toEqual( 0 );
			}

			await checkout.visit();
			const payPalButtonsExpressCheckout =
				await checkout.ppui.collectBlockSmartButtons();
			if ( tested.expressCheckout ) {
				await expect(
					payPalButtonsExpressCheckout.length
				).toBeGreaterThan( 0 );
			} else {
				await expect( payPalButtonsExpressCheckout.length ).toEqual(
					0
				);
			}
		}
	} );
} );

test.describe( 'Standard Payments > Clear DB', () => {
	test.beforeEach( async ( { pcpApi, utils } ) => {
		await pcpApi.clearPcpDb();
		await pcpApi.connectMerchant( pcpConfigDefault.merchant );
		await utils.configurePcp( {
			standardPayments: {
				enableGateway: true,
				customizeSmartButtonsPerLocation: true,
			},
		} );
	} );

	test( 'PCP-1044 | Standard Payments - Disable Alternative payment methods = Credit or debit cards @Critical', async ( {
		utils,
		standardPayments,
		classicCheckout,
	} ) => {
		await standardPayments.visit();
		await standardPayments.addItemsToSelectBox(
			'Disable Alternative Payment Methods',
			'Credit or debit cards'
		);
		await standardPayments.saveChanges();

		await utils.fillVisitorsCart( [ products.simple10 ] );

		await classicCheckout.visit();
		await classicCheckout.ppui.assertDebitOrCreditCardButtonVisibility(
			false
		);
	} );

	test( 'PCP-1062 | Standard payments - Separate payment gateway is enabled only then checkout buttons have a vertical layout', async ( {
		standardPayments,
	} ) => {
		await standardPayments.visit();
		await standardPayments.classicCheckoutButtonLayoutCombobox().click();
		await standardPayments.dropdownOption( 'Horizontal' ).click();
		await standardPayments.standardCardButtonCheckbox().check();
		await expect(
			standardPayments.classicCheckoutButtonLayoutCombobox()
		).not.toBeVisible();
		await standardPayments.assertPayPalButtonsHaveClass(
			'classicCheckout',
			/paypal-button-layout-vertical/
		);
	} );

	// Known bug: https://inpsyde.atlassian.net/browse/PCP-3676
	test( 'PCP-1139 | Standard Payments - Standard card button gateway checkbox', async ( {
		standardPayments,
		standardCardButton,
	} ) => {
		await standardPayments.visit();
		await expect( standardCardButton.tabName() ).not.toBeVisible();
		await expect(
			standardPayments.standardCardButtonCheckbox()
		).not.toBeChecked();
		await standardPayments.standardCardButtonCheckbox().check();
		await standardPayments.saveChanges();
		await expect( standardCardButton.tabName() ).toBeVisible();
	} );

	test( 'PCP-2288 | Standard Payments - Standard Payments - No Standard card button on styling preview when Standard card button gateway enabled and activated', async ( {
		standardPayments,
	} ) => {
		const testedFrames = [
			{
				orientationCombobox: 'miniCartButtonLayoutCombobox',
				buttonsIframe: 'miniCart',
			},
			{
				orientationCombobox: 'classicCheckoutButtonLayoutCombobox',
				buttonsIframe: 'classicCheckout',
			},
			{
				orientationCombobox: 'singleProductButtonLayoutCombobox',
				buttonsIframe: 'singleProduct',
			},
			{
				orientationCombobox: 'classicCartButtonLayoutCombobox',
				buttonsIframe: 'classicCart',
			},
			{
				buttonsIframe: 'expressCheckout',
			},
			{
				buttonsIframe: 'expressCart',
			},
		];

		await standardPayments.visit();
		await standardPayments.addItemsToSelectBox( 'Smart Button Locations', [
			'Single Product',
			'Classic Checkout',
			'Classic Cart',
			'Mini Cart',
			'Express Checkout',
			'Cart',
		] );

		for ( const tested of testedFrames ) {
			if ( tested.orientationCombobox ) {
				await standardPayments.page.waitForTimeout( 2000 );
				await standardPayments[ tested.orientationCombobox ]().click();
				await standardPayments.dropdownOption( 'Vertical' ).click();
			}
		}
		await standardPayments.saveChanges();

		for ( const tested of testedFrames ) {
			const cardButton =
				await standardPayments.iframePayPalSelectedButton(
					tested.buttonsIframe,
					'card'
				);
			await expect( cardButton ).toBeVisible();
		}

		await standardPayments.standardCardButtonCheckbox().check();
		await standardPayments.saveChanges();

		for ( const tested of testedFrames ) {
			const cardButton =
				await standardPayments.iframePayPalSelectedButton(
					tested.buttonsIframe,
					'card'
				);
			await expect( cardButton ).not.toBeVisible();
		}
	} );

	test( 'PCP-2540 | Standard Payments - Smart Button Locations', async ( {
		standardPayments,
	} ) => {
		const smartButtonsLocations = [
			'Single Product',
			'Classic Checkout',
			'Express Checkout',
			'Classic Cart',
			'Cart',
		];

		await standardPayments.visit();

		for ( const button of smartButtonsLocations ) {
			await expect(
				standardPayments.selectBoxChosenItem(
					'Smart Button Locations',
					button
				)
			).toBeVisible();
		}
	} );

	test( 'PCP-1091 | Standard payments - When Vaulting enabled PayLater is disabled', async ( {
		payLater,
		standardPayments,
		page,
	} ) => {
		const payLaterAlert =
			"You have PayPal vaulting enabled, that's why Pay Later options are unavailable now. You cannot use both features at the same time.";

		//check initial state
		await payLater.visit();
		await expect( payLater.enableGatewayCheckbox() ).toBeEnabled();
		await expect( await page.getByText( payLaterAlert ) ).not.toBeVisible();

		await standardPayments.visit();
		await expect( standardPayments.vaultingCheckbox() ).not.toBeChecked();

		//verify changes works
		await standardPayments.vaultingCheckbox().check();
		await standardPayments.saveChanges();

		await payLater.visit();
		await expect( payLater.enableGatewayCheckbox() ).toBeDisabled();
		await expect(
			(
				await page.getByText( payLaterAlert ).all()
			 ).length
		).toEqual( 2 );
	} );
} );
