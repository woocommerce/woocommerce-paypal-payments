/**
 * Internal dependencies
 */
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';
import { Pcp } from '../../resources';

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
	onlineCardPaymentsContainer = () =>
		this.page.locator( '#ppcp-card-payments-card' );
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
	modalCheckoutPageTitleInput = () =>
		this.modalWindow().getByRole( 'textbox', {
			name: 'Checkout page title',
		} );
	modalCheckoutPageDescriptionInput = () =>
		this.modalWindow().getByRole( 'textbox', {
			name: 'Checkout page description',
		} );
	modalPayPalShowLogoToggle = () =>
		this.modalWindow().getByLabel( 'Show logo' );
	modalAcdc3dSecureRadio = ( label: Pcp.Admin.PaymentMethods.ThreeDSecure ) =>
		this.modalWindow().getByLabel( label );
	modalFastlaneDisplayCardholderNameToggle = () =>
		this.modalWindow().getByLabel( 'Display cardholder name' );
	modalFastlaneDisplayFastlaneWatermarkToggle = () =>
		this.modalWindow().getByLabel( 'Display Fastlane Watermark' );
	modalSaveChangesButton = () =>
		this.modalWindow().getByRole( 'button', { name: 'Save changes' } );

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
