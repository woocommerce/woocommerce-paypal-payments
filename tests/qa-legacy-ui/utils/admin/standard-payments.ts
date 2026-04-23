/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';
/**
 * External dependencies
 */
import { expect } from '@inpsyde/playwright-utils/build';

export class StandardPayments extends PcpSettingsPage {
	url = urls.pcp.standardPayments;

	// Locators
	subscriptionModeTextBox = () =>
		this.page.locator( '#select2-ppcp-subscriptions_mode-container' );
	vaultingCheckbox = () =>
		this.page.getByLabel( 'Vaulting', { exact: true } );

	titleInput = () => this.page.locator( '#ppcp-title' );
	descriptionInput = () => this.page.locator( '#ppcp-description' );
	brandNameInput = () => this.page.locator( '#ppcp-brand_name' );
	disableAlternativePaymentMethodsSelectBox = () =>
		this.page.getByLabel( 'Disable Alternative Payment Methods', {
			exact: true,
		} );
	customizeSmartButtonsPerLocationCheckbox = () =>
		this.page.getByLabel( 'Customize Smart Buttons per Location' );

	requireFinalConfirmationCheckbox = () =>
		this.page.getByLabel( 'Require final confirmation on checkout' );

	sendCheckoutBillingDataCombobox = () =>
		this.page
			.locator( 'tr', {
				has: this.page.getByRole( 'rowheader', {
					name: 'Send checkout billing data to card fields',
				} ),
			} )
			.locator( 'span[role="combobox"]' );

	intentCombobox = () =>
		this.page
			.locator( 'tr', {
				has: this.page.getByRole( 'rowheader', { name: 'Intent' } ),
			} )
			.locator( 'span[role="combobox"]' );

	subscriptionsModeCombobox = () =>
		this.page
			.locator( 'tr', {
				has: this.page.getByRole( 'rowheader', {
					name: 'Subscriptions Mode',
				} ),
			} )
			.locator( 'span[role="combobox"]' );

	classicCheckoutButtonLayoutCombobox = () =>
		this.page.locator( '#select2-ppcp-button_layout-container' );
	classicCheckoutButtonLabelCombobox = () =>
		this.page.locator( '#select2-ppcp-button_label-container' );
	classicCheckoutButtonColorCombobox = () =>
		this.page.locator( '#select2-ppcp-button_color-container' );
	classicCheckoutButtonShapeCombobox = () =>
		this.page.locator( '#select2-ppcp-button_shape-container' );
	classicCheckoutTaglineCheckbox = () =>
		this.page.locator( '#ppcp-button_tagline' );
	classicCheckoutButtonPreviewIframe = () =>
		this.page.frameLocator( '#ppcpCheckoutButtonPreview .component-frame' );
	classicCheckoutTaglineText = () =>
		this.classicCheckoutButtonPreviewIframe().locator(
			'.paypal-button-tagline>.paypal-button-text'
		);
	classicCheckoutGooglePayButton = () =>
		this.page.locator( '#ppcpCheckoutButtonPreviewGooglePay' );
	classicCheckoutFundingSourceButton = ( fundingSource ) =>
		this.classicCheckoutButtonPreviewIframe().locator(
			`.paypal-button[data-funding-source="${ fundingSource }"]`
		);
	classicCheckoutPayPalButtonText = () =>
		this.classicCheckoutButtonPreviewIframe().locator(
			'.paypal-button-text.true'
		);

	singleProductButtonLayoutCombobox = () =>
		this.page.locator( '#select2-ppcp-button_product_layout-container' );
	singleProductButtonLabelCombobox = () =>
		this.page.locator( '#select2-ppcp-button_product_label-container' );
	singleProductButtonColorCombobox = () =>
		this.page.locator( '#select2-ppcp-button_product_color-container' );
	singleProductButtonShapeCombobox = () =>
		this.page.locator( '#select2-ppcp-button_product_shape-container' );
	singleProductTaglineCheckbox = () =>
		this.page.locator( '#ppcp-button_product_tagline' );
	singleProductButtonPreviewIframe = () =>
		this.page.frameLocator( '#ppcpProductButtonPreview .component-frame' );
	singleProductTaglineText = () =>
		this.singleProductButtonPreviewIframe().locator(
			'.paypal-button-tagline>.paypal-button-text'
		);
	singleProductFundingSourceButton = ( fundingSource ) =>
		this.singleProductButtonPreviewIframe().locator(
			`.paypal-button[data-funding-source="${ fundingSource }"]`
		);
	singleProductPayPalButtonText = () =>
		this.singleProductButtonPreviewIframe().locator(
			'.paypal-button-text.true'
		);

	standardCardButtonCheckbox = () =>
		this.page.getByLabel( 'Create gateway for Standard Card Button' );
	singleProductGooglePayButton = () =>
		this.page.locator( '#ppcpProductButtonPreviewGooglePay' );

	classicCartButtonLayoutCombobox = () =>
		this.page.locator( '#select2-ppcp-button_cart_layout-container' );
	classicCartButtonLabelCombobox = () =>
		this.page.locator( '#select2-ppcp-button_cart_label-container' );
	classicCartButtonButtonColorCombobox = () =>
		this.page.locator( '#select2-ppcp-button_cart_color-container' );
	classicCartButtonShapeCombobox = () =>
		this.page.locator( '#select2-ppcp-button_cart_shape-container' );
	classicCartButtonPreviewIframe = () =>
		this.page.frameLocator( '#ppcpCartButtonPreview .component-frame' );
	classicCartTaglineCheckbox = () =>
		this.page.locator( '#ppcp-button_cart_tagline' );
	classicCartTaglineText = () =>
		this.classicCartButtonPreviewIframe().locator(
			'.paypal-button-tagline>.paypal-button-text'
		);
	classicCartGooglePayButton = () =>
		this.page.locator( '#ppcpCartButtonPreviewGooglePay' );
	classicCartFundingSourceButton = ( fundingSource ) =>
		this.classicCartButtonLayoutCombobox().locator(
			`.paypal-button[data-funding-source="${ fundingSource }"]`
		);
	classicCartPayPalButtonText = () =>
		this.classicCartButtonLayoutCombobox().locator(
			'.paypal-button-text.true'
		);

	miniCartButtonPreviewIframe = () =>
		this.page.frameLocator( '#ppcpMiniCartButtonPreview .component-frame' );
	miniCartButtonShapeCombobox = () =>
		this.page.locator( '#select2-ppcp-button_mini-cart_shape-container' );
	miniCartButtonLabelCombobox = () =>
		this.page.locator( '#select2-ppcp-button_mini-cart_label-container' );
	miniCartButtonLayoutCombobox = () =>
		this.page.locator( '#select2-ppcp-button_mini-cart_layout-container' );
	miniCartButtonColorCombobox = () =>
		this.page.locator( '#select2-ppcp-button_mini-cart_color-container' );
	miniCartButtonHeightCombobox = () =>
		this.page.locator( '#ppcp-button_mini-cart_height' );
	miniCartGooglePayButton = () =>
		this.page.locator( '#ppcpMiniCartButtonPreviewGooglePay' );
	miniCartFundingSourceButton = ( fundingSource ) =>
		this.miniCartButtonPreviewIframe().locator(
			`.paypal-button[data-funding-source="${ fundingSource }"]`
		);
	miniCartPayPalButtonText = () =>
		this.miniCartButtonPreviewIframe().locator(
			'.paypal-button-text.true'
		);

	expressCheckoutButtonPreviewIframe = () =>
		this.page.frameLocator(
			'#ppcpCheckoutBlockExpressButtonPreview .component-frame'
		);
	expressCheckoutGooglePayButton = () =>
		this.page.locator( '#ppcpCheckoutBlockExpressButtonPreviewGooglePay' );
	expressCheckoutButtonLabelCombobox = () =>
		this.page.locator(
			'#select2-ppcp-button_checkout-block-express_label-container'
		);
	expressCheckoutColorCombobox = () =>
		this.page.locator(
			'#select2-ppcp-button_checkout-block-express_color-container'
		);
	expressCheckoutButtonShapeCombobox = () =>
		this.page.locator(
			'#select2-ppcp-button_checkout-block-express_shape-container'
		);
	expressCheckoutButtonHeightCombobox = () =>
		this.page.locator( '#ppcp-button_checkout-block-express_height' );
	expressCheckoutFundingSourceButton = ( fundingSource ) =>
		this.expressCheckoutButtonPreviewIframe().locator(
			`.paypal-button[data-funding-source="${ fundingSource }"]`
		);
	expressCheckoutPayPalButtonText = () =>
		this.expressCheckoutButtonPreviewIframe().locator(
			'.paypal-button-text.true'
		);

	expressCartButtonPreviewIframe = () =>
		this.page.frameLocator(
			'#ppcpCartBlockButtonPreview .component-frame'
		);
	expressCartButtonLabelCombobox = () =>
		this.page.locator( '#select2-ppcp-button_cart-block_label-container' );
	expressCartGooglePayButton = () =>
		this.page.locator( '#ppcpCartBlockButtonPreviewGooglePay' );
	expressCartColorCombobox = () =>
		this.page.locator( '#select2-ppcp-button_cart-block_color-container' );
	expressCartButtonShapeCombobox = () =>
		this.page.locator( '#select2-ppcp-button_cart-block_shape-container' );
	expressCartButtonHeightCombobox = () =>
		this.page.locator( '#ppcp-button_cart-block_height' );
	expressCartFundingSourceButton = ( fundingSource ) =>
		this.expressCartButtonPreviewIframe().locator(
			`.paypal-button[data-funding-source="${ fundingSource }"]`
		);
	expressCartPayPalButtonText = () =>
		this.expressCartButtonPreviewIframe().locator(
			'.paypal-button-text.true'
		);

	googlePayButtonCheckbox = () =>
		this.page.locator( '#ppcp-googlepay_button_enabled' );
	googlePayButtonLabelCombobox = () =>
		this.page.locator( '#select2-ppcp-googlepay_button_type-container' );
	googlePayButtonColorCombobox = () =>
		this.page.locator( '#select2-ppcp-googlepay_button_color-container' );
	googlePayButtonLanguageCombobox = () =>
		this.page.locator(
			'#select2-ppcp-googlepay_button_language-container'
		);
	googlePayShippingCallbackCheckbox = () =>
		this.page.locator( '#ppcp-googlepay_button_shipping_enabled' );

	iframePayPalButton = ( sectionCamelCaseName ) =>
		this[ `${ sectionCamelCaseName }ButtonPreviewIframe` ]().locator(
			'.paypal-button'
		);
	iframePayPalSelectedButton = ( sectionCamelCaseName, buttonName ) =>
		this[ `${ sectionCamelCaseName }ButtonPreviewIframe` ]().locator(
			`.paypal-button[data-funding-source="${ buttonName }"]`
		);
	iframePayPalButtonText = ( sectionCamelCaseName ) =>
		this[ `${ sectionCamelCaseName }ButtonPreviewIframe` ]().locator(
			'.paypal-button-text.true'
		);

	taglineCheckbox = ( sectionCamelCaseName ) =>
		this[ `${ sectionCamelCaseName }TaglineCheckbox` ]();
	taglineText = ( sectionCamelCaseName ) =>
		this[ `${ sectionCamelCaseName }TaglineText` ]();

	smartButtonLanguageCombobox = () =>
		this.page.locator( '#select2-ppcp-smart_button_language-container' );
	smartButtonLanguageClearSelectionButton = () =>
		this.smartButtonLanguageCombobox().locator(
			'.select2-selection__clear'
		);
	smartButtonLanguageInput = () =>
		this.page.locator(
			'input[aria-owns="select2-ppcp-smart_button_language-results"]'
		);
	smartButtonLanguageSearchResults = () =>
		this.page.locator(
			'li[id^="select2-ppcp-smart_button_language-result"]'
		);

	// Actions

	/**
	 * Bulk update of Standard Payments tab settings
	 *
	 * @param data
	 */
	setup = async ( data? ) => {
		if ( ! data ) {
			return;
		}

		await this.visit();

		// restore checkbox to enable Save Changes button
		const currentValue = await this.enableGatewayCheckbox().isChecked();
		await this.enableGatewayCheckbox().setChecked( ! currentValue );
		await this.enableGatewayCheckbox().setChecked( data.enableGateway ?? currentValue );

		if ( data.requireFinalConfirmation !== undefined ) {
			await this.requireFinalConfirmationCheckbox().setChecked(
				data.requireFinalConfirmation
			);
		}

		if ( data.standardCardButton !== undefined ) {
			await this.standardCardButtonCheckbox().setChecked(
				data.standardCardButton
			);
		}

		if ( data.intent ) {
			await this.intentCombobox().click();
			await this.dropdownOption( data.intent ).click();
		}

		if ( data.smartButtonLocations?.length ) {
			await this.addItemsToSelectBox(
				'Smart Button Locations',
				data.smartButtonLocations
			);
		}

		if ( data.customizeSmartButtonsPerLocation !== undefined ) {
			await this.customizeSmartButtonsPerLocationCheckbox().setChecked(
				data.customizeSmartButtonsPerLocation
			);
		}

		if ( data.classicCheckoutButtonLayout ) {
			await this.customizeSmartButtonsPerLocationCheckbox().check();
			await this.classicCheckoutButtonLayoutCombobox().click();
			await this.dropdownOption(
				data.classicCheckoutButtonLayout
			).click();
		}

		if ( data.singleProductButtonLayout ) {
			await this.customizeSmartButtonsPerLocationCheckbox().check();
			await this.singleProductButtonLayoutCombobox().click();
			await this.dropdownOption( data.singleProductButtonLayout ).click();
		}

		if ( data.classicCartButtonLayout ) {
			await this.customizeSmartButtonsPerLocationCheckbox().check();
			await this.classicCartButtonLayoutCombobox().click();
			await this.dropdownOption( data.classicCartButtonLayout ).click();
		}

		if ( data.miniCartButtonLayout ) {
			await this.customizeSmartButtonsPerLocationCheckbox().check();
			await this.miniCartButtonLayoutCombobox().click();
			await this.dropdownOption( data.miniCartButtonLayout ).click();
		}

		if ( data.vaulting !== undefined ) {
			await this.vaultingCheckbox().setChecked( data.vaulting );
		}

		if ( data.subscriptionsMode ) {
			await this.subscriptionsModeCombobox().click();
			await this.dropdownOption( data.subscriptionsMode ).click();
		}

		if ( data.sendCheckoutBillingData ) {
			await this.sendCheckoutBillingDataCombobox().click();
			await this.dropdownOption( data.sendCheckoutBillingData ).click();
		}

		if ( data.disableAlternativePaymentMethods?.length ) {
			await this.disableAlternativePaymentMethods(
				data.disableAlternativePaymentMethods
			);
		}

		if ( data.enableAlternativePaymentMethods?.length ) {
			await this.enableAlternativePaymentMethods(
				data.enableAlternativePaymentMethods
			);
		}
		// Add other settings here

		await this.saveChanges();
	};

	enableAlternativePaymentMethods = async ( methodNames: string[] ) => {
		await this.removeItemsFromSelectBox(
			'Disable Alternative Payment Methods',
			methodNames
		);
	};

	disableAlternativePaymentMethods = async ( methodNames: string[] ) => {
		await this.addItemsToSelectBox(
			'Disable Alternative Payment Methods',
			methodNames
		);
	};

	// Assertions

	assertPayPalButtonsHaveClass = async ( section, regex ) => {
		const payPalButtons = await this.iframePayPalButton( section ).all();
		for ( const payPalButton of payPalButtons ) {
			await expect( payPalButton ).toHaveClass( regex );
		}
	};

	assertPayPalButtonsVisibility = async ( sectionName, isVisible ) => {
		const payPalButtons = await this.iframePayPalButton(
			sectionName
		).all();
		for ( const payPalButton of payPalButtons ) {
			await expect( payPalButton ).toBeVisible( { visible: isVisible } );
		}
	};
}
