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
 * Wraps a manager's `this.$` so the country/state select query used by
 * `refreshEnhancedSelects()` is driven directly instead of through real layout.
 *
 * jsdom never computes layout - `offsetWidth`, `offsetHeight` and
 * `getClientRects()` are always 0/empty - so jQuery's `:visible` can never
 * match a real `<select>` here. Every other selector `rerender()` touches
 * still goes through the real jQuery the manager already has, so DOM effects
 * elsewhere remain observable.
 *
 * @param {AxoManager}      manager - Manager whose `$` gets wrapped.
 * @param {Array<string[]>} selects - One entry per visible country/state
 *                                    select, each its list of classes.
 * @return {Function} The spy standing in for `.trigger()` on `document.body`.
 */
function stubEnhancedSelectQuery( manager, selects ) {
	const realDollar = manager.$;
	const trigger = jest.fn();

	manager.$ = ( selector ) => {
		if (
			selector ===
			'select.country_select:visible, select.state_select:visible'
		) {
			return {
				// The production code excludes by class selector, while the
				// fixtures list bare class names.
				not: ( classSelector ) => ( {
					length: selects.filter(
						( classes ) =>
							! classes.includes(
								classSelector.replace( /^\./, '' )
							)
					).length,
				} ),
			};
		}
		if ( selector === document.body ) {
			return { trigger };
		}
		return realDollar( selector );
	};

	return trigger;
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

describe( 'AxoManager.rerender > refreshing WooCommerce enhanced selects', () => {
	// WooCommerce only upgrades select.country_select to select2 on page load while
	// it is visible. AXO hides the WooCommerce form before that runs, so revealing it
	// again must ask WooCommerce to redo the upgrade, or the country/state fields stay
	// plain <select> elements instead of the select2 combobox.
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	test.each( [
		[
			'the Gary flow reveals the default WooCommerce form (active, valid email, no profile)',
			{ active: true, validEmail: true, hasProfile: false },
		],
		[
			'AXO is inactive and the default WooCommerce form takes over',
			{ active: false },
		],
		[
			'the Ryan flow reveals the form for a recognized profile (active, valid email, has profile)',
			{ active: true, validEmail: true, hasProfile: true },
		],
	] )(
		'triggers country_to_state_changed when %s and a plain country select is still present',
		( _label, status ) => {
			document.body.innerHTML = `
				<div id="ppcp-axo-shipping-address"></div>
				<div id="ppcp-axo-watermark-container"></div>
				<div id="billing_email_field"><div class="woocommerce-input-wrapper"></div></div>`;
			const manager = buildRerenderManager( status );
			const trigger = stubEnhancedSelectQuery( manager, [
				[ 'country_select' ],
			] );

			manager.rerender();

			expect( trigger ).toHaveBeenCalledWith(
				'country_to_state_changed'
			);
		}
	);

	test( 'does not trigger country_to_state_changed while the form stays hidden waiting for a valid email', () => {
		document.body.innerHTML = `
			<div id="ppcp-axo-watermark-container"></div>
			<div id="billing_email_field"><div class="woocommerce-input-wrapper"></div></div>`;
		const manager = buildRerenderManager( {
			active: true,
			validEmail: false,
		} );
		const trigger = stubEnhancedSelectQuery( manager, [
			[ 'country_select' ],
		] );

		manager.rerender();

		expect( trigger ).not.toHaveBeenCalled();
	} );

	test( 'does not trigger country_to_state_changed when every visible select is already select2-enhanced', () => {
		document.body.innerHTML = `
			<div id="ppcp-axo-shipping-address"></div>
			<div id="ppcp-axo-watermark-container"></div>
			<div id="billing_email_field"><div class="woocommerce-input-wrapper"></div></div>`;
		const manager = buildRerenderManager( { active: false } );
		const trigger = stubEnhancedSelectQuery( manager, [
			[ 'country_select', 'select2-hidden-accessible' ],
		] );

		manager.rerender();

		expect( trigger ).not.toHaveBeenCalled();
	} );
} );

describe( 'AxoManager.refreshEnhancedSelects', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	test( 'triggers country_to_state_changed when a visible select.country_select lacks select2-hidden-accessible', () => {
		const manager = buildManager();
		const trigger = stubEnhancedSelectQuery( manager, [
			[ 'country_select' ],
		] );

		manager.refreshEnhancedSelects();

		expect( trigger ).toHaveBeenCalledWith( 'country_to_state_changed' );
	} );

	test( 'triggers country_to_state_changed when a visible select.state_select lacks select2-hidden-accessible', () => {
		const manager = buildManager();
		const trigger = stubEnhancedSelectQuery( manager, [
			[ 'state_select' ],
		] );

		manager.refreshEnhancedSelects();

		expect( trigger ).toHaveBeenCalledWith( 'country_to_state_changed' );
	} );

	test( 'does not trigger country_to_state_changed when every visible select is already select2-enhanced (churn guard)', () => {
		const manager = buildManager();
		const trigger = stubEnhancedSelectQuery( manager, [
			[ 'country_select', 'select2-hidden-accessible' ],
			[ 'state_select', 'select2-hidden-accessible' ],
		] );

		manager.refreshEnhancedSelects();

		expect( trigger ).not.toHaveBeenCalled();
	} );

	test( 'does not trigger country_to_state_changed when there are no country or state selects', () => {
		const manager = buildManager();
		const trigger = stubEnhancedSelectQuery( manager, [] );

		manager.refreshEnhancedSelects();

		expect( trigger ).not.toHaveBeenCalled();
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
