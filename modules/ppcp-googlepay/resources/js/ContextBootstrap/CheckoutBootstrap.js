import { GooglePayStorage } from '../Helper/GooglePayStorage';
import {
	getWooCommerceCustomerDetails,
	setPayerData,
} from '../../../../ppcp-button/resources/js/modules/Helper/PayerData';

const CHECKOUT_FORM_SELECTOR = 'form.woocommerce-checkout';

export class CheckoutBootstrap {
	/**
	 * @type {GooglePayStorage}
	 */
	#storage;

	/**
	 * @type {null|HTMLFormElement}
	 */
	#checkoutForm = null;

	constructor( storage ) {
		this.#storage = storage;
	}

	/**
	 * Returns the WooCommerce checkout form element.
	 *
	 * @return {HTMLFormElement|null} The form, or null if not a checkout page.
	 */
	get checkoutForm() {
		if ( null === this.#checkoutForm ) {
			this.#checkoutForm = document.querySelector(
				CHECKOUT_FORM_SELECTOR
			);
		}

		return this.#checkoutForm;
	}

	/**
	 * Indicates, if the current page contains a checkout form.
	 *
	 * @return {boolean} True, if a checkout form is present.
	 */
	get isPageWithCheckoutForm() {
		return null !== this.checkoutForm;
	}

	init() {
		if ( ! this.isPageWithCheckoutForm ) {
			return;
		}

		this.#populateCheckoutFields();
	}

	#populateCheckoutFields() {
		const loggedInData = getWooCommerceCustomerDetails();

		// If customer is logged in, we use the details from the customer profile.
		if ( loggedInData ) {
			return;
		}

		const billingData = this.#storage.getPayer();

		if ( ! billingData ) {
			return;
		}

		setPayerData( billingData, true );
		this.checkoutForm.addEventListener( 'submit', () =>
			this.#onFormSubmit()
		);
	}

	#onFormSubmit() {
		this.#storage.clearPayer();
	}
}
