/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
/**
 * External dependencies
 */
import { wooCommerceUrls } from '@inpsyde/playwright-utils/build';
import buttonColorClasses from '../../resources/paypal-button-colors.json';
import {
	storeConfigClassic,
	products,
	pcpConfigDefault,
} from '../../resources';

test.describe( 'Standard Payments tab', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( storeConfigClassic );
		await utils.configurePcp( {
			...pcpConfigDefault,
			standardPayments: {
				...pcpConfigDefault.standardPayments,
				smartButtonLocations: [
					'Single Product',
					'Classic Checkout',
					'Classic Cart',
					'Mini Cart',
					'Express Checkout',
					'Cart',
				],
			},
		} );
	} );

	const verticalLayoutTests = [
		{
			title: 'PCP-1068 | Standard Payments - Product - Vertical button layout',
			buttonOrientation: {
				singleProductButtonLayout: 'Vertical',
			},
			section: 'singleProduct',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-1053 | Standard Payments - Classic Checkout - Vertical button layout',
			buttonOrientation: {
				classicCheckoutButtonLayout: 'Vertical',
			},
			section: 'classicCheckout',
			frontPageUrl: wooCommerceUrls.frontend.classicCheckout,
		},
		{
			title: 'PCP-1075 | Standard Payments - Classic Cart - Vertical button layout',
			buttonOrientation: {
				classicCartButtonLayout: 'Vertical',
			},
			section: 'classicCart',
			frontPageUrl: wooCommerceUrls.frontend.classicCart,
		},
		{
			title: 'PCP-1082 | Standard Payments - Mini Cart - Vertical button layout',
			buttonOrientation: {
				classicCartButtonLayout: 'Vertical',
			},
			section: 'miniCart',
			frontPageUrl: products.simple10.slug,
		},
	];

	for ( const tested of verticalLayoutTests ) {
		test(
			tested.title,
			async ( {
				standardPayments,
				ppui,
				visitorPage,
				utils,
				product,
			} ) => {
				let payPalButtonsFront: any = [];

				await utils.fillVisitorsCart( [ products.simple10 ] );

				await standardPayments.visit();
				await standardPayments.setup( tested.buttonOrientation );

				await standardPayments.assertPayPalButtonsHaveClass(
					tested.section,
					/paypal-button-layout-vertical/
				);

				await visitorPage.goto( tested.frontPageUrl );
				if ( tested.section === 'miniCart' ) {
					await product.cartMenu().hover();
					payPalButtonsFront = await ppui
						.miniCartIframePayPalButton()
						.all();
				} else {
					payPalButtonsFront = await ppui.iframePayPalButton().all();
				}
				await ppui.assertPayPalButtonsHaveClass(
					payPalButtonsFront,
					/paypal-button-layout-vertical/
				);
			}
		);
	}

	const horizontalLayoutTests = [
		{
			title: 'PCP-1054 | Standard Payments - Classic Checkout - Horizontal button layout',
			buttonOrientation: {
				classicCheckoutButtonLayout: 'Horizontal',
			},
			section: 'classicCheckout',
			frontPageUrl: wooCommerceUrls.frontend.classicCheckout,
		},
		{
			title: 'PCP-1069 | Standard Payments - Product - Horizontal button layout',
			buttonOrientation: {
				singleProductButtonLayout: 'Horizontal',
			},
			section: 'singleProduct',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-1076 | Standard Payments - Classic Cart - Horizontal button layout',
			buttonOrientation: {
				classicCartButtonLayout: 'Horizontal',
			},
			section: 'classicCart',
			frontPageUrl: wooCommerceUrls.frontend.classicCart,
		},
		{
			title: 'PCP-2818 | Standard Payments - Mini cart - Horizontal button layout',
			buttonOrientation: {
				miniCartButtonLayout: 'Horizontal',
			},
			section: 'miniCart',
			frontPageUrl: products.simple10.slug,
		},
	];

	for ( const tested of horizontalLayoutTests ) {
		test(
			tested.title,
			async ( {
				standardPayments,
				ppui,
				visitorPage,
				utils,
				product,
			} ) => {
				let payPalButtonsFront: any = [];

				await utils.fillVisitorsCart( [ products.simple10 ] );

				await standardPayments.visit();
				await standardPayments.setup( tested.buttonOrientation );

				await standardPayments.assertPayPalButtonsHaveClass(
					tested.section,
					/paypal-button-layout-horizontal/
				);

				await visitorPage.goto( tested.frontPageUrl );
				if ( tested.section === 'miniCart' ) {
					await product.cartMenu().hover();
					payPalButtonsFront = await ppui
						.miniCartIframePayPalButton()
						.all();
				} else {
					payPalButtonsFront = await ppui.iframePayPalButton().all();
				}
				await ppui.assertPayPalButtonsHaveClass(
					payPalButtonsFront,
					/paypal-button-layout-horizontal/
				);
			}
		);
	}

	const taglineTests = [
		{
			title: 'PCP-1055 | Standard Payments - Classic Checkout - Tagline text',
			buttonOrientation: {
				classicCheckoutButtonLayout: 'Horizontal',
			},
			section: 'classicCheckout',
			frontPageUrl: wooCommerceUrls.frontend.classicCheckout,
		},
		{
			title: 'PCP-1070 | Standard Payments - Product - Tagline text',
			buttonOrientation: {
				singleProductButtonLayout: 'Horizontal',
			},
			section: 'singleProduct',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-1077 | Standard Payments - Classic Cart - Tagline text',
			buttonOrientation: {
				classicCartButtonLayout: 'Horizontal',
			},
			section: 'classicCart',
			frontPageUrl: products.simple10.slug,
		},
	];

	for ( const tested of taglineTests ) {
		test(
			tested.title,
			async ( { standardPayments, ppui, visitorPage, utils } ) => {
				await standardPayments.visit();
				await standardPayments.setup( tested.buttonOrientation );

				await standardPayments
					.taglineCheckbox( tested.section )
					.check();
				await standardPayments.saveChanges();

				await expect(
					standardPayments.taglineText( tested.section )
				).toBeVisible();
				await expect(
					standardPayments.wcErrorsContainer()
				).not.toBeVisible();

				await utils.fillVisitorsCart( [ products.simple10 ] );

				await visitorPage.goto( tested.frontPageUrl );
				await expect( ppui.taglineText() ).toBeVisible();
			}
		);
	}

	const buttonColorsTests = [
		{
			title: 'PCP-1057 | Standard payments - Classic Checkout - Buttons Color',
			buttonOrientation: {
				classicCheckoutButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'classicCheckoutButtonColorCombobox',
			section: 'classicCheckout',
			frontPageUrl: wooCommerceUrls.frontend.classicCheckout,
		},
		{
			title: 'PCP-1079 | Standard payments - Classic Cart - Buttons Color',
			buttonOrientation: {
				classicCartButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'classicCartButtonButtonColorCombobox',
			section: 'classicCart',
			frontPageUrl: wooCommerceUrls.frontend.classicCart,
		},
		{
			title: 'PCP-1072 | Standard payments - Product - Buttons Color',
			buttonOrientation: {
				singleProductButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'singleProductButtonColorCombobox',
			section: 'singleProduct',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-1085 | Standard payments - Mini Cart - Buttons Color',
			buttonOrientation: {
				miniCartButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'miniCartButtonColorCombobox',
			section: 'miniCart',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-2812 | Standard payments - Express Cart - Buttons Color',
			buttonOrientation: false,
			comboboxLocatorName: 'expressCartColorCombobox',
			section: 'expressCart',
			frontPageUrl: wooCommerceUrls.frontend.cart,
		},
		{
			title: 'PCP-2813 | Standard payments - Express Checkout - Buttons Color',
			buttonOrientation: false,
			comboboxLocatorName: 'expressCheckoutColorCombobox',
			section: 'expressCheckout',
			frontPageUrl: wooCommerceUrls.frontend.checkout,
		},
	];

	for ( const tested of buttonColorsTests ) {
		test(
			tested.title,
			async ( {
				utils,
				standardPayments,
				ppui,
				visitorPage,
				product,
			} ) => {
				const buttonsColorsDropdown = {
					'Gold (Recommended)': 'gold',
					Blue: 'blue',
					Silver: 'silver',
					Black: 'black',
					White: 'white',
				};

				let payPalButtonsFront: any = [];

				await utils.fillVisitorsCart( [ products.simple10 ] );

				await standardPayments.visit();
				if ( tested.buttonOrientation ) {
					await standardPayments.setup( tested.buttonOrientation );
				}

				const assertPayPalButtonColorClasses = async (
					currentColor,
					payPalButtons
				) => {
					const buttonClasses = buttonColorClasses[ currentColor ];

					for ( const button of payPalButtons ) {
						const dataFundingSource = await button.getAttribute(
							'data-funding-source'
						);

						for ( const fundingSource in buttonClasses ) {
							const classRegex = await new RegExp(
								buttonClasses[ fundingSource ]
							);

							if ( fundingSource === dataFundingSource ) {
								await expect( button ).toHaveClass(
									classRegex
								);
							}
						}
					}
				};

				//iteration of Button color dropdown
				for ( const comboboxColorOption in buttonsColorsDropdown ) {
					const currentColor =
						buttonsColorsDropdown[ comboboxColorOption ];

					await standardPayments.visit();
					await standardPayments[
						tested.comboboxLocatorName
					]().click();
					await standardPayments
						.dropdownOption( comboboxColorOption )
						.click();
					await standardPayments.saveChanges();

					// collect and iterate texts from all Checkout buttons preview on Standard Payments tab
					const payPalButtons = await standardPayments
						.iframePayPalButton( tested.section )
						.all();
					await assertPayPalButtonColorClasses(
						currentColor,
						payPalButtons
					);

					// collect and iterate texts from paypal buttons on front end
					await visitorPage.goto( tested.frontPageUrl );

					if ( tested.section === 'miniCart' ) {
						await product.cartMenu().hover();
						payPalButtonsFront = await ppui
							.miniCartIframePayPalButton()
							.all();
					} else if (
						tested.section === 'expressCart' ||
						tested.section === 'expressCheckout'
					) {
						payPalButtonsFront =
							await ppui.collectBlockSmartButtons();
					} else {
						payPalButtonsFront = await ppui
							.iframePayPalButton()
							.all();
					}

					await assertPayPalButtonColorClasses(
						currentColor,
						payPalButtonsFront
					);
				}
			}
		);
	}

	//===============================================================================================================

	const buttonShapeTests = [
		{
			title: 'PCP-1058 | Standard payments - Classic Checkout - Buttons shape',
			buttonOrientation: {
				classicCheckoutButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'classicCheckoutButtonShapeCombobox',
			section: 'classicCheckout',
			frontPageUrl: wooCommerceUrls.frontend.classicCheckout,
		},
		{
			title: 'PCP-1080 | Standard payments - Classic Cart - Buttons Shape',
			buttonOrientation: {
				classicCartButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'classicCartButtonShapeCombobox',
			section: 'classicCart',
			frontPageUrl: wooCommerceUrls.frontend.classicCart,
		},
		{
			title: 'PCP-1073 | Standard payments - Product - Buttons Shape',
			buttonOrientation: {
				singleProductButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'singleProductButtonShapeCombobox',
			section: 'singleProduct',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-2814 | Standard payments - Express Cart - Buttons Shape',
			buttonOrientation: false,
			comboboxLocatorName: 'expressCartButtonShapeCombobox',
			section: 'expressCart',
			frontPageUrl: wooCommerceUrls.frontend.cart,
		},
		{
			title: 'PCP-2816 | Standard payments - Express Checkout - Buttons Shape',
			buttonOrientation: false,
			comboboxLocatorName: 'expressCheckoutButtonShapeCombobox',
			section: 'expressCheckout',
			frontPageUrl: wooCommerceUrls.frontend.checkout,
		},
		{
			title: 'PCP-1086 | Standard payments - Mini Cart - Buttons Color',
			buttonOrientation: {
				miniCartButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'miniCartButtonShapeCombobox',
			section: 'miniCart',
			frontPageUrl: products.simple10.slug,
		},
	];

	for ( const tested of buttonShapeTests ) {
		test(
			tested.title,
			async ( {
				standardPayments,
				ppui,
				visitorPage,
				utils,
				product,
			} ) => {
				await utils.fillVisitorsCart( [ products.simple10 ] );
				await standardPayments.setup( tested.buttonOrientation );

				const shapes = [
					{
						option: 'Pill',
						class: /paypal-button-shape-pill/,
					},
					{
						option: 'Rectangle',
						class: /paypal-button-shape-rect/,
					},
				];

				let payPalButtonsFront: any = [];

				for ( const shape of shapes ) {
					await standardPayments.visit();
					await standardPayments[
						tested.comboboxLocatorName
					]().click();
					await standardPayments
						.dropdownOption( shape.option )
						.click();
					await standardPayments.saveChanges();

					await standardPayments.assertPayPalButtonsHaveClass(
						tested.section,
						shape.class
					);

					//check frontend
					await visitorPage.goto( tested.frontPageUrl );

					if ( tested.section === 'miniCart' ) {
						await product.cartMenu().hover();
						payPalButtonsFront = await ppui
							.miniCartIframePayPalButton()
							.all();
					} else if (
						tested.section === 'expressCart' ||
						tested.section === 'expressCheckout'
					) {
						payPalButtonsFront =
							await ppui.collectBlockSmartButtons();
					} else {
						payPalButtonsFront = await ppui
							.iframePayPalButton()
							.all();
					}

					await ppui.assertPayPalButtonsHaveClass(
						payPalButtonsFront,
						shape.class
					);
				}
			}
		);
	}

	//================================================================================================================

	const buttonTextTests = [
		{
			title: 'PCP-1084 | Standard Payments - Mini Cart - Button Label',
			buttonOrientation: {
				miniCartButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'miniCartButtonLabelCombobox',
			buttonsIframe: 'miniCart',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-1056 | Standard payments - Classic checkout - Button label',
			buttonOrientation: {
				classicCheckoutButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'classicCheckoutButtonLabelCombobox',
			buttonsIframe: 'classicCheckout',
			frontPageUrl: wooCommerceUrls.frontend.classicCheckout,
		},
		{
			title: 'PCP-1071 | Standard payments - Product - Button label',
			buttonOrientation: {
				singleProductButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'singleProductButtonLabelCombobox',
			buttonsIframe: 'singleProduct',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-1078 | Standard payments - Classic Cart - Button label',
			buttonOrientation: {
				classicCartButtonLayout: 'Vertical',
			},
			comboboxLocatorName: 'classicCartButtonLabelCombobox',
			buttonsIframe: 'classicCart',
			frontPageUrl: wooCommerceUrls.frontend.classicCart,
		},
		{
			title: 'PCP-2820 | Standard payments - Express Cart - Buttons Label',
			comboboxLocatorName: 'expressCartButtonLabelCombobox',
			buttonsIframe: 'expressCart',
			frontPageUrl: wooCommerceUrls.frontend.cart,
		},
		{
			title: 'PCP-2821 | Standard payments - Express Checkout - Buttons Label',
			comboboxLocatorName: 'expressCheckoutButtonLabelCombobox',
			buttonsIframe: 'expressCheckout',
			frontPageUrl: wooCommerceUrls.frontend.checkout,
		},
	];

	for ( const tested of buttonTextTests ) {
		test(
			tested.title,
			async ( {
				utils,
				standardPayments,
				ppui,
				visitorPage,
				product,
			} ) => {
				await standardPayments.setup( tested.buttonOrientation );
				await utils.fillVisitorsCart( [ products.simple10 ] );

				const buttonLabels = {
					payPal: {
						inCombobox: 'PayPal',
						onButton: null,
					},
					classicCheckout: {
						inCombobox: 'Checkout',
						onButton: 'Checkout',
					},
					buyNow: {
						inCombobox: 'PayPal Buy Now',
						onButton: 'Buy Now',
					},
					payWith: {
						inCombobox: 'Pay with PayPal',
						onButton: 'Pay with',
					},
				};

				const assertPayPalButtonsText = async (
					text,
					payPalButtons
				) => {
					const exclusionFundingSources = [ 'card', 'paylater' ]; // text is not added to these buttons

					for ( const button of payPalButtons ) {
						const dataFundingSource = await button.getAttribute(
							'data-funding-source'
						);

						// if option PayPal is selected - nothing should be added
						if ( text === null ) {
							await expect( button ).not.toContainText(
								buttonLabels.classicCheckout.onButton
							);
							await expect( button ).not.toContainText(
								buttonLabels.buyNow.onButton
							);
							await expect( button ).not.toContainText(
								buttonLabels.payWith.onButton
							);
							continue;
						}

						// other combobox options
						if (
							exclusionFundingSources.includes(
								dataFundingSource
							)
						) {
							await expect( button ).not.toContainText( text );
						} else {
							await expect( button ).toContainText( text );
						}
					}
				};

				//iteration of Button Label dropdown
				for ( const i in buttonLabels ) {
					const label = buttonLabels[ i ];

					await test.step(
						`Button Label: ${ label.onButton }`,
						async () => {
							await standardPayments.visit();
							await standardPayments[
								tested.comboboxLocatorName
							]().click();
							await standardPayments
								.dropdownOption( label.inCombobox )
								.click();
							await standardPayments.saveChanges();

							// collect and iterate texts from all buttons on Standard Payments tab
							let payPalButtons = await standardPayments
								.iframePayPalButton( tested.buttonsIframe )
								.all();
							await assertPayPalButtonsText(
								label.onButton,
								payPalButtons
							);

							//check frontend>checkout visitorPage
							await visitorPage.goto( tested.frontPageUrl );

							if ( tested.buttonsIframe === 'miniCart' ) {
								await product.cartMenu().hover();
								payPalButtons = await ppui
									.miniCartIframePayPalButton()
									.all();
							} else if (
								tested.buttonsIframe === 'expressCart' ||
								tested.buttonsIframe === 'expressCheckout'
							) {
								payPalButtons =
									await ppui.collectBlockSmartButtons();
							} else {
								payPalButtons = await ppui
									.iframePayPalButton()
									.all();
							}

							await assertPayPalButtonsText(
								label.onButton,
								payPalButtons
							);
						}
					);
				}
			}
		);
	}

	//=========================================================================================
	const buttonSizeTests = [
		{
			title: 'PCP-1087 | Standard Payments - Mini Cart - Button Height inside the allowed range',
			comboboxLocatorName: 'miniCartButtonHeightCombobox',
			buttonsIframe: 'miniCart',
			frontPageUrl: products.simple10.slug,
		},
		{
			title: 'PCP-2822 | Standard payments - Express Cart - Button Height inside the allowed range',
			comboboxLocatorName: 'expressCartButtonHeightCombobox',
			buttonsIframe: 'expressCart',
			frontPageUrl: wooCommerceUrls.frontend.cart,
		},
		{
			title: 'PCP-2823 | Standard payments - Express Checkout - Button Height inside the allowed range',
			comboboxLocatorName: 'expressCheckoutButtonHeightCombobox',
			buttonsIframe: 'expressCheckout',
			frontPageUrl: wooCommerceUrls.frontend.checkout,
		},
	];

	for ( const tested of buttonSizeTests ) {
		test(
			tested.title,
			async ( {
				utils,
				standardPayments,
				visitorPage,
				ppui,
				product,
			} ) => {
				await utils.fillVisitorsCart( [ products.simple10 ] );
				const buttonHeight = '40';
				let payPalButtonsFront: any = [];

				await standardPayments.visit();
				await standardPayments[
					`${ tested.comboboxLocatorName }`
				]().clear();
				await standardPayments[
					`${ tested.comboboxLocatorName }`
				]().fill( buttonHeight );
				await standardPayments.saveChanges();

				//check height of buttons on backend
				const payPalButtons = await standardPayments
					.iframePayPalButton( tested.buttonsIframe )
					.all();
				for ( const payPalButton of payPalButtons ) {
					await expect( payPalButton ).toHaveCSS(
						'height',
						buttonHeight + 'px'
					);
				}

				//check height of buttons on frontend
				await visitorPage.goto( tested.frontPageUrl );

				if ( tested.buttonsIframe === 'miniCart' ) {
					await product.cartMenu().hover();
					payPalButtonsFront = await ppui
						.miniCartButtonIframe()
						.locator( 'div[class^="paypal-button-number-"]' )
						.all();
				} else {
					payPalButtonsFront = await ppui.collectBlockSmartButtons();
				}

				for ( const payPalButton of payPalButtonsFront ) {
					await expect( payPalButton ).toHaveCSS(
						'height',
						buttonHeight + 'px'
					);
				}
			}
		);
	}
	//=============================================================================================

	const buttonIncorrectSizeTests = [
		{
			title: 'PCP-1089 | Standard Payments - Mini Cart - Button Height outside the allowed range',
			comboboxLocatorName: 'miniCartButtonHeightCombobox',
			buttonsIframe: 'miniCart',
		},
		{
			title: 'PCP-2825 | Standard payments - Express Cart - Button Height outside the allowed range',
			comboboxLocatorName: 'expressCartButtonHeightCombobox',
			buttonsIframe: 'expressCart',
		},
		{
			title: 'PCP-2827 | Standard payments - Express Checkout - Button Height outside the allowed range',
			comboboxLocatorName: 'expressCheckoutButtonHeightCombobox',
			buttonsIframe: 'expressCheckout',
		},
	];

	for ( const tested of buttonIncorrectSizeTests ) {
		test( tested.title, async ( { standardPayments } ) => {
			const inputValues = [
				{
					incorrect: '56',
					correct: '55',
					alertMessage: 'Value must be less than or equal to 55',
				},
				{
					incorrect: '24',
					correct: '25',
					alertMessage: 'Value must be greater than or equal to 40',
				},
			];

			await standardPayments.visit();

			for ( const testedValue of inputValues ) {
				//check invalid value
				await standardPayments[
					`${ tested.comboboxLocatorName }`
				]().fill( testedValue.incorrect );
				standardPayments.page.on( 'dialog', async ( dialog ) => {
					expect( dialog.message() ).toContain(
						testedValue.alertMessage
					);
				} );
				await standardPayments.saveChangesButton().click();
				await standardPayments.page.waitForTimeout( 2000 );
				await expect(
					standardPayments
						.iframePayPalButton( [ `${ tested.buttonsIframe }` ] )
						.first()
				).not.toBeVisible();

				//check valid boundary value
				await standardPayments[
					`${ tested.comboboxLocatorName }`
				]().fill( testedValue.correct );
				standardPayments.page.on( 'dialog', async ( dialog ) => {
					expect( dialog.message() ).toBeUndefined();
				} );
				await standardPayments.saveChangesButton().click();
				await standardPayments.page.waitForTimeout( 2000 );
				await standardPayments.assertPayPalButtonsVisibility(
					`${ tested.buttonsIframe }`,
					true
				);
			}
		} );
	}

	//tested for Germany language and "PayPal Buy Now" label
	test( 'PCP-2221 | Standard Payments - Smart button language - Buttons preview', async ( {
		standardPayments,
	} ) => {
		const testDataTranslation = [
			{
				translated: {
					text: 'Jetzt kaufen',
					cardButtonText: 'Debit- oder Kreditkarte',
				},
			},
			{
				default: {
					text: 'Buy Now',
					cardButtonText: 'Debit or Credit Card',
				},
			},
		];

		const testedFrames = [
			{
				orientationCombobox: 'miniCartButtonLayoutCombobox',
				labelCombobox: 'miniCartButtonLabelCombobox',
				buttonsIframe: 'miniCart',
			},
			{
				orientationCombobox: 'classicCheckoutButtonLayoutCombobox',
				labelCombobox: 'classicCheckoutButtonLabelCombobox',
				buttonsIframe: 'classicCheckout',
			},
			{
				orientationCombobox: 'singleProductButtonLayoutCombobox',
				labelCombobox: 'singleProductButtonLabelCombobox',
				buttonsIframe: 'singleProduct',
			},
			{
				orientationCombobox: 'classicCartButtonLayoutCombobox',
				labelCombobox: 'classicCartButtonLabelCombobox',
				buttonsIframe: 'classicCart',
			},
			{
				labelCombobox: 'expressCheckoutButtonLabelCombobox',
				buttonsIframe: 'expressCheckout',
			},
			{
				labelCombobox: 'expressCartButtonLabelCombobox',
				buttonsIframe: 'expressCart',
			},
		];

		const checkButtonsTextInPreviewIframes = async (
			buttonsTranslatedText,
			cardsButtonText
		) => {
			for ( const tested of testedFrames ) {
				// collect and iterate texts from all buttons from current tested section
				const payPalButtons = await standardPayments
					.iframePayPalButton( tested.buttonsIframe )
					.all();

				for ( const button of payPalButtons ) {
					const exclusionFundingSources = [ 'card', 'paylater' ]; // text is not added to these buttons

					const dataFundingSource = await button.getAttribute(
						'data-funding-source'
					);

					// check text on buttons
					if (
						exclusionFundingSources.includes( dataFundingSource )
					) {
						await expect( button ).not.toContainText(
							buttonsTranslatedText
						);
					} else {
						await expect( button ).toContainText(
							buttonsTranslatedText
						);
					}

					if ( dataFundingSource === 'card' ) {
						await expect( button ).toContainText( cardsButtonText );
					}
				}
			}
		};

		await standardPayments.visit();
		for ( const tested of testedFrames ) {
			if ( tested.orientationCombobox ) {
				await standardPayments[ tested.orientationCombobox ]().click();
				await standardPayments.dropdownOption( 'Vertical' ).click();
			}
			await standardPayments[ tested.labelCombobox ]().click();
			await standardPayments.dropdownOption( 'PayPal Buy Now' ).click();
		}

		//testing of custom language
		await standardPayments.smartButtonLanguageCombobox().click();
		await standardPayments.dropdownOption( 'German (Germany)' ).click();
		await standardPayments.saveChanges();

		await checkButtonsTextInPreviewIframes(
			testDataTranslation[ 0 ].translated?.text,
			testDataTranslation[ 0 ].translated?.cardButtonText
		);

		//testing of browser(default = English) language
		await standardPayments
			.smartButtonLanguageClearSelectionButton()
			.click();
		await standardPayments.classicCheckoutButtonLayoutCombobox().click();
		await standardPayments.saveChanges();

		await checkButtonsTextInPreviewIframes(
			testDataTranslation[ 1 ].default?.text,
			testDataTranslation[ 1 ].default?.cardButtonText
		);
	} );

	test( 'PCP-2237 | Standard Payments - Smart button language - Select language with filter', async ( {
		standardPayments,
	} ) => {
		await standardPayments.visit();
		await standardPayments.smartButtonLanguageCombobox().click();
		await standardPayments.smartButtonLanguageInput().fill( 'Polish' );

		await expect(
			standardPayments.smartButtonLanguageSearchResults()
		).toContainText( 'Polish' );

		await standardPayments.smartButtonLanguageSearchResults().click();
		await expect(
			standardPayments.smartButtonLanguageCombobox()
		).toContainText( 'Polish' );
	} );
} );
