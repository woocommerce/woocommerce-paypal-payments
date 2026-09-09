/**
 * Internal dependencies
 */
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';

export class PcpPaymentMethods extends PcpAdminPage {
	url = urls.admin.pcp.paymentMethods;

	// Locators
	contentContainer = () => this.page.locator( '.ppcp-r-payment-methods' );
	paymentMethodTitle = ( title: string ) =>
		this.page.getByText( title, { exact: true } );
	paymentMethodTitleContainer = ( title: string ) =>
		this.page.locator( '.ppcp--method-title' ).filter( {
			has: this.paymentMethodTitle( title ),
		} );
	paymentMethodContainers = () => this.page.locator( '.ppcp--method-item' );

	// Group containers — IDs come from TabPaymentMethods.js PaymentMethodCard `id` props
	payPalCheckoutContainer = () =>
		this.page.locator( '#ppcp-paypal-checkout-card' );
	onlineCardPaymentsContainer = () =>
		this.page.locator( '#ppcp-card-payments-card' );
	alternativePaymentMethodsContainer = () =>
		this.page.locator( '#ppcp-alternative-payments-card' );

	// Per-group item counts (scoped to each section)
	payPalCheckoutMethodItems = () =>
		this.payPalCheckoutContainer().locator( '.ppcp--method-item' );
	onlineCardPaymentMethodItems = () =>
		this.onlineCardPaymentsContainer().locator( '.ppcp--method-item' );
	alternativePaymentMethodItems = () =>
		this.alternativePaymentMethodsContainer().locator(
			'.ppcp--method-item'
		);
	paymentMethodContainer = ( title: string ) =>
		this.paymentMethodContainers().filter( {
			has: this.paymentMethodTitleContainer( title ),
		} );
	paymentMethodToggle = ( title: string ) =>
		this.paymentMethodContainer( title ).locator(
			'input.components-form-toggle__input'
		);
	paymentMethodSettingsButton = ( title: string ) =>
		this.paymentMethodContainer( title ).locator(
			'button.ppcp--method-settings'
		);
	modalWindow = () => this.page.locator( '.ppcp-r-modal' );
	modalCloseButton = () =>
		this.modalWindow().locator( 'button[aria-label="Close"]' );
	modalTitle = () => this.modalWindow().locator( '.ppcp-r-modal__title' );

	// Actions

	// Assertions

	/**
	 * Compares actual modal window screenshot to expected.
	 *
	 * @param snapshotName
	 */
	snapshotModalWindow = async ( snapshotName: string ) =>
		this.snapshotLocator( this.modalWindow(), snapshotName, {
			threshold: 0.9,
		} );
}
