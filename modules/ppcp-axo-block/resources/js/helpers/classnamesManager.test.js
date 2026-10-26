jest.mock( '@ppcp-axo/Helper/Debug', () => ( {
	log: jest.fn(),
} ) );

jest.mock( '@ppcp-axo-block/stores/axoStore', () => ( {
	STORE_NAME: 'woocommerce-paypal-payments/axo-block',
} ) );

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	subscribe: jest.fn(),
} ) );

import { select, subscribe } from '@wordpress/data';
import { setupAuthenticationClassToggle } from './classnamesManager';

const AUTH_CLASS = 'wc-block-axo-is-authenticated';

const renderExpressPayment = ( initialClass = '' ) => {
	document.body.innerHTML = `
		<div class="wc-block-components-express-payment wc-block-components-express-payment--checkout ${ initialClass }"></div>
		<div class="wc-block-components-express-payment-continue-rule wc-block-components-express-payment-continue-rule--checkout ${ initialClass }"></div>
	`;

	return {
		expressBox: document.querySelector(
			'.wc-block-components-express-payment--checkout'
		),
		separator: document.querySelector(
			'.wc-block-components-express-payment-continue-rule--checkout'
		),
	};
};

describe( 'setupAuthenticationClassToggle', () => {
	let isGuest;

	beforeEach( () => {
		jest.clearAllMocks();
		document.body.innerHTML = '';
		isGuest = true;
		select.mockReturnValue( { getIsGuest: () => isGuest } );
		subscribe.mockReturnValue( jest.fn() );
	} );

	test( 'marks both the express payment box and the separator as authenticated when the user is not a guest', () => {
		isGuest = false;
		const { expressBox, separator } = renderExpressPayment();

		setupAuthenticationClassToggle();

		expect( expressBox.classList.contains( AUTH_CLASS ) ).toBe( true );
		expect( separator.classList.contains( AUTH_CLASS ) ).toBe( true );
	} );

	test( 'leaves both elements unmarked when the user is a guest', () => {
		const { expressBox, separator } = renderExpressPayment();

		setupAuthenticationClassToggle();

		expect( expressBox.classList.contains( AUTH_CLASS ) ).toBe( false );
		expect( separator.classList.contains( AUTH_CLASS ) ).toBe( false );
	} );

	test( 'removes a previously added class from both elements when the user is a guest', () => {
		const { expressBox, separator } = renderExpressPayment( AUTH_CLASS );

		setupAuthenticationClassToggle();

		expect( expressBox.classList.contains( AUTH_CLASS ) ).toBe( false );
		expect( separator.classList.contains( AUTH_CLASS ) ).toBe( false );
	} );

	test.each( [
		[ 'guest to authenticated', true, false ],
		[ 'authenticated to guest', false, true ],
	] )(
		'updates both elements together when the store changes from %s',
		( _label, initialIsGuest, nextIsGuest ) => {
			isGuest = initialIsGuest;
			const { expressBox, separator } = renderExpressPayment();

			setupAuthenticationClassToggle();
			isGuest = nextIsGuest;
			const onStoreChange = subscribe.mock.calls[ 0 ][ 0 ];
			onStoreChange();

			const expectAuthenticated = ! nextIsGuest;
			expect( expressBox.classList.contains( AUTH_CLASS ) ).toBe(
				expectAuthenticated
			);
			expect( separator.classList.contains( AUTH_CLASS ) ).toBe(
				expectAuthenticated
			);
		}
	);

	test( 'does not throw when no target elements are in the DOM', () => {
		isGuest = false;

		expect( () => {
			setupAuthenticationClassToggle();
			subscribe.mock.calls[ 0 ][ 0 ]();
		} ).not.toThrow();
	} );

	test( 'returns the unsubscribe function from the store subscription', () => {
		const unsubscribe = jest.fn();
		subscribe.mockReturnValue( unsubscribe );

		expect( setupAuthenticationClassToggle() ).toBe( unsubscribe );
	} );
} );
