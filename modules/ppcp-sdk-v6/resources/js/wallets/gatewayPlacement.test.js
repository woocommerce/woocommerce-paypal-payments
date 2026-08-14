import jQuery from 'jquery';

const mockGetCurrentPaymentMethod = jest.fn();
jest.mock( '@ppcp-button/Helper/CheckoutMethodState', () => ( {
	getCurrentPaymentMethod: () => mockGetCurrentPaymentMethod(),
	ORDER_BUTTON_SELECTOR: '#place_order',
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

// The module keeps a `synced` Set at module scope to guard against stacking
// listeners across re-renders, so each test needs a fresh module instance.
let revealGateway;
let syncGatewayVisibility;

beforeEach( () => {
	jest.resetModules();
	mockGetCurrentPaymentMethod.mockReset();
	mockHasJQuery.mockReset();
	mockHasJQuery.mockReturnValue( true );
	document.body.innerHTML = '';
	global.jQuery = jQuery;
	( { revealGateway, syncGatewayVisibility } = require( './gatewayPlacement' ) );
} );

describe( 'revealGateway()', () => {
	test( 'removes only the hide-gateway style tag for this method', () => {
		document.body.innerHTML =
			'<style data-hide-gateway="googlepay"></style>' +
			'<style data-hide-gateway="applepay"></style>';

		revealGateway( 'googlepay' );

		expect(
			document.querySelector( 'style[data-hide-gateway="googlepay"]' )
		).toBeNull();
		expect(
			document.querySelector( 'style[data-hide-gateway="applepay"]' )
		).not.toBeNull();
	} );

	test( 'clears an inline display:none on this method\'s row, ' +
		'leaving another method\'s row hidden', () => {
		document.body.innerHTML =
			'<div class="wc_payment_method payment_method_googlepay" ' +
			'style="display: none"></div>' +
			'<div class="wc_payment_method payment_method_applepay" ' +
			'style="display: none"></div>';

		revealGateway( 'googlepay' );

		expect(
			document.querySelector( '.payment_method_googlepay' ).style
				.display
		).toBe( '' );
		expect(
			document.querySelector( '.payment_method_applepay' ).style
				.display
		).toBe( 'none' );
	} );

	test( 'does nothing when neither the style tag nor the row exist', () => {
		expect( () => revealGateway( 'googlepay' ) ).not.toThrow();
	} );
} );

describe( 'syncGatewayVisibility()', () => {
	function setDom() {
		document.body.innerHTML =
			'<div id="wallet-wrapper"></div><div id="place_order"></div>';
	}

	test( 'shows the wallet wrapper and hides "Place order" when this ' +
		'method is currently selected', () => {
		setDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );

		syncGatewayVisibility( 'googlepay', '#wallet-wrapper' );

		expect(
			document.querySelector( '#wallet-wrapper' ).style.display
		).toBe( '' );
		expect(
			document.querySelector( '#place_order' ).style.display
		).toBe( 'none' );
	} );

	test( 'hides the wallet wrapper and shows "Place order" when a ' +
		'different method is selected', () => {
		setDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

		syncGatewayVisibility( 'googlepay', '#wallet-wrapper' );

		expect(
			document.querySelector( '#wallet-wrapper' ).style.display
		).toBe( 'none' );
		expect(
			document.querySelector( '#place_order' ).style.display
		).toBe( '' );
	} );

	test( 're-evaluates and swaps visibility when the checkout fires ' +
		'payment_method_selected', () => {
		setDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

		syncGatewayVisibility( 'googlepay', '#wallet-wrapper' );
		expect(
			document.querySelector( '#wallet-wrapper' ).style.display
		).toBe( 'none' );

		mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );
		jQuery( document.body ).trigger( 'payment_method_selected' );

		expect(
			document.querySelector( '#wallet-wrapper' ).style.display
		).toBe( '' );
		expect(
			document.querySelector( '#place_order' ).style.display
		).toBe( 'none' );
	} );

	test( 'registers the checkout-update listener only once across ' +
		'repeated calls for the same method, but again for a different one', () => {
		setDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );
		const onSpy = jest.spyOn( jQuery.fn, 'on' );

		syncGatewayVisibility( 'googlepay', '#wallet-wrapper' );
		syncGatewayVisibility( 'googlepay', '#wallet-wrapper' );

		expect( onSpy ).toHaveBeenCalledTimes( 1 );

		syncGatewayVisibility( 'applepay', '#wallet-wrapper' );

		expect( onSpy ).toHaveBeenCalledTimes( 2 );

		onSpy.mockRestore();
	} );

	test( 'reveals the wallet wrapper without throwing when jQuery is ' +
		'absent, and never registers a listener', () => {
		setDom();
		mockHasJQuery.mockReturnValue( false );
		mockGetCurrentPaymentMethod.mockReturnValue( 'googlepay' );
		const onSpy = jest.spyOn( jQuery.fn, 'on' );

		expect( () =>
			syncGatewayVisibility( 'googlepay', '#wallet-wrapper' )
		).not.toThrow();
		expect(
			document.querySelector( '#wallet-wrapper' ).style.display
		).toBe( '' );
		expect( onSpy ).not.toHaveBeenCalled();

		onSpy.mockRestore();
	} );
} );
