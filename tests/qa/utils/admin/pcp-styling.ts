/**
 * Internal dependencies
 */
import { expect } from '../test';
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';
import { Pcp } from '../../resources';

/** Maps ButtonColor label to select value. */
const COLOR_LABEL_TO_VALUE: Record<
	Pcp.Admin.Styling.ButtonColor,
	string
> = {
	'Gold (recommended)': 'gold',
	'Gold (Recommended)': 'gold',
	Blue: 'blue',
	Silver: 'silver',
	Black: 'black',
	White: 'white',
};

/** Maps ButtonLabel to select value. */
const LABEL_TO_VALUE: Record<Pcp.Admin.Styling.ButtonLabel, string> = {
	PayPal: 'paypal',
	Checkout: 'checkout',
	'PayPal Buy Now': 'buynow',
	'Pay with PayPal': 'pay',
};

/** Maps ButtonShape to radio value. */
const SHAPE_TO_VALUE: Record<Pcp.Admin.Styling.ButtonShape, string> = {
	Rectangle: 'rect',
	Pill: 'pill',
};

/** Maps ButtonLayout to radio value. */
const LAYOUT_TO_VALUE: Record<Pcp.Admin.Styling.ButtonLayout, string> = {
	Vertical: 'vertical',
	Horizontal: 'horizontal',
};

export class PcpStyling extends PcpAdminPage {
	url = urls.admin.pcp.styling;

	// Locators
	configContainer = () => this.page.locator( '.ppcp-r-styling' );
	locationSelectbox = () =>
		this.page.locator( '#inspector-select-control-0' );
	enablePaymentMethodsOnLocationCheckbox = () =>
		this.page.locator( '#inspector-checkbox-control-0' );
	paymentMethodsCheckbox = ( label: Pcp.Admin.Styling.PaymentMethods ) =>
		this.page.getByLabel( label );
	buttonLayoutRadio = ( label: Pcp.Admin.Styling.ButtonLayout ) =>
		this.page.getByLabel( label );
	showTaglineBelowButtonsCheckbox = () =>
		this.page.getByLabel( 'Show tagline below buttons' );
	buttonShapeRadio = ( label: Pcp.Admin.Styling.ButtonShape ) =>
		this.page.getByLabel( label );
	buttonLabelSelectbox = () =>
		this.page.locator( '.ppcp-r-settings-block.button-label select' );
	buttonColorSelectbox = () =>
		this.page.locator( '.ppcp-r-settings-block.button-color select' );

	payPalButtonsPreviewIframe = () =>
		this.page.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' );
	payPalButtonsPreviewContainer = () =>
		this.payPalButtonsPreviewIframe().locator( '#buttons-container' );

	// Actions

	applyButtonColor = async ( color: Pcp.Admin.Styling.ButtonColor ) => {
		await this.buttonColorSelectbox().selectOption( { label: color } );
	};

	applyButtonLabel = async ( label: Pcp.Admin.Styling.ButtonLabel ) => {
		await this.buttonLabelSelectbox().selectOption( { label } );
	};

	applyButtonShape = async ( shape: Pcp.Admin.Styling.ButtonShape ) => {
		await this.buttonShapeRadio( shape ).click();
	};

	applyButtonLayout = async ( layout: Pcp.Admin.Styling.ButtonLayout ) => {
		await this.buttonLayoutRadio( layout ).click();
	};

	// Assertions

	/**
	 * Asserts the admin preview shows PayPal buttons.
	 */
	assertPreviewHasPayPalButtons = async () => {
		await expect(
			this.payPalButtonsPreviewContainer(),
			'Assert styling preview shows PayPal buttons'
		).toBeVisible();
	};

	assertButtonColor = async ( color: Pcp.Admin.Styling.ButtonColor ) => {
		const value = COLOR_LABEL_TO_VALUE[ color ];
		await expect(
			this.buttonColorSelectbox(),
			`Assert button color is ${ color }`
		).toHaveValue( value );
	};

	assertButtonLabel = async ( label: Pcp.Admin.Styling.ButtonLabel ) => {
		const value = LABEL_TO_VALUE[ label ];
		await expect(
			this.buttonLabelSelectbox(),
			`Assert button label is ${ label }`
		).toHaveValue( value );
	};

	assertButtonShape = async ( shape: Pcp.Admin.Styling.ButtonShape ) => {
		const value = SHAPE_TO_VALUE[ shape ];
		await expect(
			this.buttonShapeRadio( shape ),
			`Assert button shape is ${ shape }`
		).toBeChecked();
	};

	assertButtonLayout = async ( layout: Pcp.Admin.Styling.ButtonLayout ) => {
		await expect(
			this.buttonLayoutRadio( layout ),
			`Assert button layout is ${ layout }`
		).toBeChecked();
	};
}
