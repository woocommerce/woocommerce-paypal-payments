/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	customers,
	shopSettings,
	storeConfigGermany,
	products,
	pcpConfigGermany,
	guests,
} from '../../resources';

test.describe( 'Pay Upon Invoice', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( {
			...storeConfigGermany,
			classicPages: true,
		} );
		await utils.configurePcp( {
			...pcpConfigGermany,
			payUponInvoice: { enableGateway: true },
		} );
	} );

	//should be updated for check of block page also
	test( 'PCP-1152 | Pay Upon Invoice - Enabling gateway', async ( {
		utils,
		classicCheckout,
		payUponInvoice,
	} ) => {
		await utils.fillVisitorsCart( [ products.simple10 ] );

		await payUponInvoice.visit();
		await payUponInvoice.enableGatewayCheckbox().uncheck();
		await payUponInvoice.saveChanges();

		await classicCheckout.visit();
		await expect(
			classicCheckout.ppui.payUponInvoiceGateway()
		).not.toBeVisible();

		await payUponInvoice.enableGatewayCheckbox().check();
		await payUponInvoice.saveChanges();

		await classicCheckout.page.reload();
		await expect(
			classicCheckout.ppui.payUponInvoiceGateway()
		).toBeVisible();
	} );

	test.describe( 'Mandatory fields', () => {
		test.beforeAll( async ( { payUponInvoice } ) => {
			await payUponInvoice.setup( { enableGateway: true } );
		} );

		test.beforeEach( async ( { payUponInvoice } ) => {
			await payUponInvoice.visit();
		} );

		test( 'PCP-1149 | Pay Upon Invoice - Brand name is mandatory field', async ( {
			payUponInvoice,
			page,
		} ) => {
			await payUponInvoice.enableGatewayCheckbox().check();
			await payUponInvoice.brandNameInput().clear();
			await payUponInvoice.saveChangesButton().click();
			await page.waitForTimeout( 1000 );
			await expect( payUponInvoice.brandNameInput() ).toHaveJSProperty(
				'validity.valid',
				false
			);
			await expect(
				payUponInvoice.savedChangesMessage()
			).not.toBeVisible();
		} );

		test( 'PCP-1150 | Pay Upon Invoice - Logo URL is mandatory field', async ( {
			payUponInvoice,
			page,
		} ) => {
			await payUponInvoice.logoUrlInput().clear();
			await payUponInvoice.saveChangesButton().click();
			await page.waitForTimeout( 1000 );
			await expect( payUponInvoice.logoUrlInput() ).toHaveJSProperty(
				'validity.valid',
				false
			);
			await expect(
				payUponInvoice.savedChangesMessage()
			).not.toBeVisible();
		} );

		test( 'PCP-1151 | Pay Upon Invoice - Customer service instructions is mandatory field', async ( {
			payUponInvoice,
			page,
		} ) => {
			await payUponInvoice.customerServiceInstructionsInput().clear();
			await payUponInvoice.saveChangesButton().click();
			await page.waitForTimeout( 1000 );
			await expect(
				payUponInvoice.customerServiceInstructionsInput()
			).toHaveJSProperty( 'validity.valid', false );
			await expect(
				payUponInvoice.savedChangesMessage()
			).not.toBeVisible();
		} );

		test( 'PCP-2904 | Pay Upon Invoice - Limit of Brand name field', async ( {
			payUponInvoice,
			page,
		} ) => {
			await payUponInvoice.brandNameInput().clear();
			await payUponInvoice
				.brandNameInput()
				.fill(
					'Save time formatting by making pageless your default for every new document. Change this any time by selecting Page setup in the'
				);

			await payUponInvoice.saveChangesButton().click();
			await page.waitForTimeout( 1000 );

			await expect( payUponInvoice.brandNameInput() ).toHaveJSProperty(
				'validity.valid',
				false
			);
			await expect(
				payUponInvoice.savedChangesMessage()
			).not.toBeVisible();

			await payUponInvoice.brandNameInput().clear();
			await payUponInvoice
				.brandNameInput()
				.fill(
					'Save time formatting by making pageless your default for every new document. Change this any time by selecting Page setup in th'
				);
			await payUponInvoice.saveChanges();
			await expect( payUponInvoice.brandNameInput() ).toHaveJSProperty(
				'validity.valid',
				true
			);
		} );
	} );

	test.describe( 'Display on frontend', () => {
		test.beforeAll( async ( { payUponInvoice } ) => {
			await payUponInvoice.setup( { enableGateway: true } );
		} );

		test.beforeEach( async ( { payUponInvoice, utils } ) => {
			await payUponInvoice.visit();
			await utils.fillVisitorsCart( [ products.simple10 ] );
		} );

		//should be updated for check of block page also
		test( 'PCP-1153 | Manipulating with Title', async ( {
			payUponInvoice,
			classicCheckout,
		} ) => {
			await payUponInvoice.titleInput().clear();
			await payUponInvoice.titleInput().fill( 'Title is edited' );
			await payUponInvoice.saveChanges();

			await classicCheckout.visit();
			await expect(
				classicCheckout.ppui.payUponInvoiceGatewayTitle()
			).toHaveText( 'Title is edited' );
		} );

		//should be updated for check of block page also
		test( 'PCP-1154 | Manipulating with Description', async ( {
			payUponInvoice,
			classicCheckout,
		} ) => {
			await payUponInvoice.titleInput().clear();
			await payUponInvoice
				.descriptionInput()
				.fill( 'New text of description' );
			await payUponInvoice.saveChanges();

			await classicCheckout.visit();
			await expect(
				classicCheckout.ppui.payUponInvoiceGatewayDescription()
			).toHaveText( 'New text of description' );
		} );

		//should be updated for check of block page also
		test( 'PCP-1218 | Pay upon Invoice - Pay upon Invoice button is not displayed for non-german billing address', async ( {
			classicCheckout,
		} ) => {
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( customers.usa );
			await expect(
				classicCheckout.ppui.payUponInvoiceGateway()
			).toBeVisible();
			await classicCheckout.fillCheckoutForm( guests.usa );
			await expect(
				classicCheckout.ppui.payUponInvoiceGateway()
			).not.toBeVisible();
		} );
	} );

	test.describe( 'Incompatible WC settings', () => {
		test.beforeEach( async ( { wooCommerceApi, utils } ) => {
			await wooCommerceApi.updateGeneralSettings(
				shopSettings.germany.general
			);
			await utils.fillVisitorsCart( [ products.simple10 ] );
		} );

		//should be updated for check of block page also
		test( 'PCP-1148 | Pay Upon Invoice - The gateway is not displayed when a country store is not in Germany', async ( {
			wooCommerceApi,
			classicCheckout,
		} ) => {
			await wooCommerceApi.updateGeneralSettings( {
				woocommerce_default_country: 'US:AK',
			} );

			await classicCheckout.visit();
			await expect(
				classicCheckout.ppui.payUponInvoiceGateway()
			).not.toBeVisible();
		} );

		//should be updated for check of block page also
		test( 'PCP-1155 | Pay Upon Invoice - The gateway is not displayed when currency is different from euro', async ( {
			wooCommerceApi,
			classicCheckout,
		} ) => {
			await wooCommerceApi.updateGeneralSettings( {
				woocommerce_currency: 'GBP',
			} );

			await classicCheckout.visit();
			await expect(
				classicCheckout.ppui.payUponInvoiceGateway()
			).not.toBeVisible();
		} );
	} );
} );
