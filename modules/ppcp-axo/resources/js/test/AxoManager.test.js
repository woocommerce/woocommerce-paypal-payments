/* global describe, test, expect, afterEach */
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
