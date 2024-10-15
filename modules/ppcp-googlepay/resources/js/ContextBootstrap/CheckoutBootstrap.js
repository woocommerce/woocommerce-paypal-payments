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
	 * @type {HTMLFormElement|null}
	 */
	#checkoutForm;

	/**
	 * @param {GooglePayStorage} storage
	 */
	constructor( storage ) {
		this.#storage = storage;
		this.#checkoutForm = CheckoutBootstrap.getCheckoutForm();
	}

	/**
	 * Indicates if the current page contains a checkout form.
	 *
	 * @return {boolean} True if a checkout form is present.
	 */
	static isPageWithCheckoutForm() {
		return null !== CheckoutBootstrap.getCheckoutForm();
	}

	/**
	 * Retrieves the WooCommerce checkout form element.
	 *
	 * @return {HTMLFormElement|null} The form, or null if not a checkout page.
	 */
	static getCheckoutForm() {
		return document.querySelector( CHECKOUT_FORM_SELECTOR );
	}

	/**
	 * Returns the WooCommerce checkout form element.
	 *
	 * @return {HTMLFormElement|null} The form, or null if not a checkout page.
	 */
	get checkoutForm() {
		return this.#checkoutForm;
	}

	/**
	 * Initializes the checkout process.
	 *
	 * @throws {Error} If called on a page without a checkout form.
	 */
	init() {
		if ( ! this.#checkoutForm ) {
			throw new Error(
				'Checkout form not found. Cannot initialize CheckoutBootstrap.'
			);
		}

		this.#populateCheckoutFields();
	}

	/**
	 * Populates checkout fields with stored or customer data.
	 */
	#populateCheckoutFields() {
		const loggedInData = getWooCommerceCustomerDetails();

		if ( loggedInData ) {
			// If customer is logged in, we use the details from the customer profile.
			return;
		}

		const billingData = this.#storage.getPayer();

		if ( ! billingData ) {
			return;
		}

		setPayerData( billingData, true );
		this.checkoutForm.addEventListener(
			'submit',
			this.#onFormSubmit.bind( this )
		);
	}

	/**
	 * Clean-up when checkout form is submitted.
	 *
	 * Immediately removes the payer details from the localStorage.
	 */
	#onFormSubmit() {
		this.#storage.clearPayer();
	}
}
