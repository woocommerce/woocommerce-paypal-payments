/**
 * Internal dependencies
 */
import {
	test,
	expect,
	annotateVisitor,
	getTestResultsFromFile,
	saveTestResultsToFile,
} from '../../utils';
import {
	pcpPlugin,
	customers,
	products,
	cards,
	pcpConfigUsa,
	storeConfigUsa,
	acdc,
} from '../../resources';

const TEST_RESULTS_FILE = 'test-results-acdc.json';

test.describe( 'Advanced Card Processing', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigUsa );
	} );

	//to add check for block checkout when it will be implemented
	test(
		'PCP-1129 | Advanced Card Processing - Enabling gateway',
		annotateVisitor( customers.usa ),
		async ( { classicCheckout, advancedCardProcessing, utils } ) => {
			await utils.fillVisitorsCart( [ products.simple10 ] );

			await advancedCardProcessing.visit();
			await advancedCardProcessing.enableGatewayCheckbox().uncheck();
			await advancedCardProcessing.vaultingCheckbox().uncheck();
			await advancedCardProcessing.saveChanges();

			await classicCheckout.visit();
			await expect(
				classicCheckout.ppui.acdcGateway()
			).not.toBeVisible();

			await advancedCardProcessing.visit();
			await advancedCardProcessing.enableGatewayCheckbox().check();
			await advancedCardProcessing.saveChanges();

			await classicCheckout.page.reload();
			await expect( classicCheckout.ppui.acdcGateway() ).toBeVisible();
		}
	);

	test.describe( 'Enabled ACDC', () => {
		test.beforeAll( async ( { utils } ) => {
			await utils.pcpPaymentMethodIsEnabled( acdc.method );
		} );

		test( 'PCP-2121 | Advanced Card Processing - Title text by default', async ( {
			advancedCardProcessing,
		} ) => {
			await advancedCardProcessing.visit();
			await expect( advancedCardProcessing.titleInput() ).toHaveAttribute(
				'value',
				'Debit & Credit Cards'
			);
		} );

		//to add check for block checkout when it will be implemented
		test(
			'PCP-1130 | Advanced Card Processing - Title manipulation - ACDC',
			annotateVisitor( customers.usa ),
			async ( { utils, classicCheckout, advancedCardProcessing } ) => {
				const testText = 'ACDC title';
				await utils.fillVisitorsCart( [ products.simple10 ] );

				await advancedCardProcessing.visit();
				await advancedCardProcessing.titleInput().fill( testText );
				await advancedCardProcessing.saveChanges();

				await classicCheckout.visit();
				await expect(
					classicCheckout.ppui.acdcGatewayText()
				).toContainText( testText );
			}
		);

		//to add check for block checkout when it will be implemented
		test(
			'PCP-1131 | Advanced Card Processing - Disabling specific credit cards',
			annotateVisitor( customers.usa ),
			async ( { utils, classicCheckout, advancedCardProcessing } ) => {
				await utils.fillVisitorsCart( [ products.simple10 ] );

				await advancedCardProcessing.visit();
				await advancedCardProcessing.addItemsToSelectBox(
					/Disable specific credit cards/,
					'Visa'
				);
				await advancedCardProcessing.saveChanges();

				await classicCheckout.visit();
				await classicCheckout.ppui.acdcGateway().click();
				await classicCheckout.ppui
					.cardNumberInput()
					.fill( cards.visa.card_number );
				await classicCheckout.ppui.cardExpirationInput().click();
				await classicCheckout.page.keyboard.type(
					cards.visa.expiration_date
				);
				await classicCheckout.ppui
					.cardCVVInput()
					.fill( cards.visa.card_cvv );
				await classicCheckout.ppui.placeOrderButton().click();
				await expect(
					classicCheckout.page.getByText(
						'Unfortunately, we do not accept this card.'
					)
				).not.toBeVisible;
			}
		);

		//To add check for block checkout when it will be implemented
		test(
			'PCP-1132 | Advanced Card Processing - Showing logo of the following credit cards',
			annotateVisitor( customers.usa ),
			async ( { utils, classicCheckout, advancedCardProcessing } ) => {
				let icons: any = [];
				const iconsImages = [
					'visa.svg',
					'visa-dark.svg',
					'mastercard.svg',
					'mastercard-dark.svg',
					'amex.svg',
					'discover.svg',
				];

				await utils.fillVisitorsCart( [ products.simple10 ] );

				await advancedCardProcessing.visit();
				await advancedCardProcessing.removeItemsFromSelectBox(
					/Disable specific credit cards/,
					[ 'Visa', 'Mastercard', 'American Express', 'Discover' ]
				);

				// await advancedCardProcessing.visit();
				await advancedCardProcessing.addItemsToSelectBox(
					/Show logo of the following credit cards/,
					[
						'Visa (light)',
						'Visa (dark)',
						'Mastercard (light)',
						'Mastercard (dark)',
						'American Express',
						'Discover',
					]
				);
				await advancedCardProcessing.saveChanges();

				await classicCheckout.visit();
				await classicCheckout.fillCheckoutForm( customers.usa );
				icons = await classicCheckout.ppui.acdcCardsIcons().all();
				await expect( icons.length ).toEqual( iconsImages.length );

				for ( const tested of icons ) {
					let result = false;
					const imagePath = await tested.getAttribute( 'src' );
					for ( const imageName of iconsImages ) {
						if ( imagePath.includes( imageName ) ) {
							result = true;
						}
					}
					await expect( result ).toBeTruthy();
				}
			}
		);

		test( 'PCP-2289 | Advanced Card Processing - Gateway enabled - No Standard card button on styling preview in Standard Payments tab', async ( {
			standardPayments,
			advancedCardProcessing,
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

			await advancedCardProcessing.visit();
			await advancedCardProcessing.enableGatewayCheckbox().check();

			await standardPayments.visit();
			await standardPayments
				.customizeSmartButtonsPerLocationCheckbox()
				.check();
			await standardPayments.addItemsToSelectBox(
				'Smart Button Locations',
				[
					'Single Product',
					'Classic Checkout',
					'Classic Cart',
					'Mini Cart',
					'Express Checkout',
					'Cart',
				]
			);

			for ( const tested of testedFrames ) {
				if ( tested.orientationCombobox ) {
					await standardPayments[
						tested.orientationCombobox
					]().click();
					await standardPayments.dropdownOption( 'Vertical' ).click();
				}
			}
			await standardPayments.saveChanges();

			for ( const tested of testedFrames ) {
				await expect(
					standardPayments.iframePayPalSelectedButton(
						tested.buttonsIframe,
						'card'
					)
				).not.toBeVisible();
			}
		} );

		//to add check for block checkout when it will be implemented
		test(
			'PCP-2111 | Advanced Card Processing - Availability after plugin reinstallation',
			annotateVisitor( customers.usa ),
			async ( {
				utils,
				classicCheckout,
				advancedCardProcessing,
				plugins,
			} ) => {
				//Preparation: installation of old version
				await plugins.installPluginFromFile(
					pcpPlugin.oldVerionZipFilePath
				);

				await advancedCardProcessing.setup( {
					enableGateway: true,
				} );

				//Installation of actual version version
				await plugins.installPluginFromFile( pcpPlugin.zipFilePath );

				//check advanced card processing UI elements
				await utils.fillVisitorsCart( [ products.simple10 ] );
				await classicCheckout.visit();
				await expect(
					classicCheckout.ppui.acdcGateway()
				).toBeVisible();
			}
		);

		/**
		 * Subset of tests for PCP-2203
		 * Results are stored in a file and reported via 1 test after describe
		 * (search for 'PCP-2203 | Advanced Card Processing - Extend country eligibility')
		 */
		test.describe( 'PCP-2203', () => {
			test.describe( 'Subtests', () => {
				const testData = {
					'US:AZ': [ 'EUR', 'CAD', 'GBP', 'JPY', 'USD' ],
					'CA:AB': [
						'AUD',
						'BRL',
						'CAD',
						'CHF',
						'CZK',
						'DKK',
						'EUR',
						'GBP',
						'HKD',
						'HUF',
						'ILS',
						'JPY',
						'MXN',
						'NOK',
						'NZD',
						'PHP',
						'PLN',
						'SEK',
						'SGD',
						'THB',
						'TWD',
						'USD',
					],
					FR: [
						'AUD',
						'BRL',
						'CAD',
						'CHF',
						'CZK',
						'DKK',
						'EUR',
						'GBP',
						'HKD',
						'HUF',
						'ILS',
						'JPY',
						'MXN',
						'NOK',
						'NZD',
						'PHP',
						'PLN',
						'SEK',
						'SGD',
						'THB',
						'TWD',
						'USD',
					],
				};

				const countriesCurrencies = Object.entries( testData ).flatMap(
					( [ country, currencies ] ) =>
						currencies.map( ( currency ) => ( {
							country,
							currency,
						} ) )
				);

				for ( const countryCurrency of countriesCurrencies ) {
					const { country, currency } = countryCurrency;
					test( `(PCP-2203) Advanced Card Processing - Extend country eligibility - ${ country }/${ currency }`, async ( {
						advancedCardProcessing,
						wooCommerceApi,
						wooCommerceSettings,
					} ) => {
						await wooCommerceApi.updateGeneralSettings( {
							woocommerce_default_country: country,
							woocommerce_currency: currency,
						} );

						await wooCommerceSettings.visit( 'payments' );
						await expect
							.soft(
								wooCommerceSettings.gatewayRow(
									/Advanced Card Processing/
								),
								`Not visible for ${ country } + ${ currency }`
							)
							.toBeVisible();
						await advancedCardProcessing.visit();
						await expect
							.soft( advancedCardProcessing.tabName() )
							.toBeVisible();
						await expect
							.soft(
								advancedCardProcessing.enableGatewayCheckbox()
							)
							.toBeVisible();
					} );
				}
			} );

			test.afterEach( async ( {}, testInfo ) => {
				saveTestResultsToFile(
					testInfo.title,
					testInfo.status,
					TEST_RESULTS_FILE
				);
			} );
		} );

		/**
		 * Test PCP-2203 to report results of subtests from describe above (stored in a file)
		 * (search for '(PCP-2203)'
		 */
		test( 'PCP-2203 | Advanced Card Processing - Extend country eligibility', async ( {} ) => {
			getTestResultsFromFile( 'PCP-2203', TEST_RESULTS_FILE );
		} );
	} );
} );
