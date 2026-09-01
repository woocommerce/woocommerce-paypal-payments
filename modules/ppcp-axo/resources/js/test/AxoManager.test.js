/* global describe, test, expect, afterEach, jest */
import jQuery from 'jquery';
import AxoManager from '../AxoManager';

global.$ = global.jQuery = jQuery;

/**
 * Builds an AxoManager without running its constructor's side effects; only the
 * scoped jQuery helper the field-consistency methods rely on is provided.
 *
 * @return {AxoManager} The manager instance.
 */
function buildManager() {
	const instance = Object.create( AxoManager.prototype );
	instance.$ = ( selector ) => jQuery( selector );
	return instance;
}

/**
 * Builds a stub DOM-element wrapper compatible with `DomElementCollection` entries:
 * exposes the selector plus `show`/`hide` spies, without touching the real DOM.
 *
 * @param {string} selector - CSS selector the real wrapper would expose.
 * @return {{selector: string, show: Function, hide: Function}} The stub element.
 */
function stubElement( selector ) {
	return {
		selector,
		show: jest.fn(),
		hide: jest.fn(),
	};
}

/**
 * Builds an AxoManager with everything `rerender()` touches stubbed out, driven by
 * the given `status` so the real `identifyScenario()` determines the scenario.
 *
 * @param {Object} status - Overrides merged into the manager's `status` object.
 * @return {AxoManager} The manager instance, ready for `rerender()`.
 */
function buildRerenderManager( status ) {
	const instance = buildManager();

	instance.status = {
		active: false,
		validEmail: false,
		hasProfile: false,
		hasCard: false,
		useEmailWidget: false,
		...status,
	};

	instance.el = {
		watermarkContainer: stubElement( '#ppcp-axo-watermark-container' ),
		defaultSubmitButton: stubElement( '#place_order' ),
		billingEmailSubmitButton: stubElement(
			'#ppcp-axo-billing-email-submit-button'
		),
		fieldBillingEmail: stubElement( '#billing_email_field' ),
		customerDetails: stubElement( '#ppcp-axo-customer-details' ),
		emailWidgetContainer: stubElement( '#ppcp-axo-email-widget' ),
		paymentContainer: stubElement( '#ppcp-axo-payment' ),
		gatewayDescription: stubElement( '.ppcp-axo-gateway-description' ),
		submitButtonContainer: stubElement( '#ppcp-axo-submit-button' ),
		axoCustomerDetails: stubElement( '#ppcp-axo-customer-details-wrapper' ),
		shippingAddressContainer: stubElement( '#ppcp-axo-shipping-address' ),
	};

	const view = () => ( {
		activate: jest.fn(),
		deactivate: jest.fn(),
		refresh: jest.fn(),
	} );
	instance.shippingView = view();
	instance.billingView = view();
	instance.cardView = view();

	return instance;
}

describe( 'AxoManager.rerender', () => {
	afterEach( () => {
		delete window.wc_ppcp_sdk_v6;
		document.body.innerHTML = '';
	} );

	describe( 'when AXO is inactive (the default place-order button owns the page)', () => {
		test( 'does not re-show the default place-order button when the v6 SDK manages it', () => {
			window.wc_ppcp_sdk_v6 = { fastlane: { enabled: true } };
			const manager = buildRerenderManager( { active: false } );

			manager.rerender();

			expect(
				manager.el.defaultSubmitButton.show
			).not.toHaveBeenCalled();
			expect(
				manager.el.billingEmailSubmitButton.hide
			).toHaveBeenCalled();
		} );

		test( 'shows the default place-order button on v5 pages without the v6 SDK config', () => {
			const manager = buildRerenderManager( { active: false } );

			manager.rerender();

			expect( manager.el.defaultSubmitButton.show ).toHaveBeenCalled();
			expect(
				manager.el.billingEmailSubmitButton.hide
			).toHaveBeenCalled();
		} );
	} );

	describe( 'when AXO is active with a recognized profile (AXO owns the submit button)', () => {
		test( 'hides the default place-order button even when the v6 SDK config is present', () => {
			window.wc_ppcp_sdk_v6 = { fastlane: { enabled: true } };
			document.body.innerHTML = `
				<div id="ppcp-axo-shipping-address"></div>
				<div id="ppcp-axo-watermark-container"></div>`;
			const manager = buildRerenderManager( {
				active: true,
				validEmail: true,
				hasProfile: true,
			} );

			manager.rerender();

			expect( manager.el.defaultSubmitButton.hide ).toHaveBeenCalled();
			expect(
				manager.el.billingEmailSubmitButton.show
			).toHaveBeenCalled();
		} );
	} );
} );

describe( 'AxoManager.ensureShippingFieldsConsistency', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	// The "Ship to a different address?" toggle is an <h3 id="ship-to-different-address">
	// inside .woocommerce-shipping-fields, and WooCommerce keeps the address rows in a
	// collapsed .shipping_address div until the box is checked. Hiding the toggle here
	// would leave the shopper no way to reveal the shipping fields.
	test( 'keeps the ship-to-different-address toggle visible when shipping fields are collapsed', () => {
		document.body.innerHTML = `
			<div class="woocommerce-shipping-fields">
				<h3 id="ship-to-different-address"><label>Ship to a different address?</label></h3>
				<div class="shipping_address" style="display:none">
					<div class="form-row"></div>
				</div>
			</div>`;

		buildManager().ensureShippingFieldsConsistency();

		expect(
			jQuery( '#ship-to-different-address' ).css( 'display' )
		).not.toBe( 'none' );
	} );

	test( 'hides a dangling shipping section header when no shipping fields are visible', () => {
		document.body.innerHTML = `
			<div class="woocommerce-shipping-fields">
				<h3 class="shipping-section-title">Shipping details</h3>
				<div class="shipping_address" style="display:none">
					<div class="form-row"></div>
				</div>
			</div>`;

		buildManager().ensureShippingFieldsConsistency();

		expect( jQuery( '.shipping-section-title' ).css( 'display' ) ).toBe(
			'none'
		);
	} );
} );
