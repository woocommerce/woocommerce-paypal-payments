const mockHasJQuery = jest.fn( () => true );
jest.mock( './api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

import { refreshCartUi } from './cartUi';

let mockInvalidateResolutionForStore;
let mockDispatch;
let mockJQueryTrigger;

beforeEach( () => {
	mockHasJQuery.mockReturnValue( true );

	mockInvalidateResolutionForStore = jest.fn();
	mockDispatch = jest.fn( () => ( {
		invalidateResolutionForStore: mockInvalidateResolutionForStore,
	} ) );
	global.wp = { data: { dispatch: mockDispatch } };

	mockJQueryTrigger = jest.fn();
	global.jQuery = jest.fn( () => ( { trigger: mockJQueryTrigger } ) );
} );

afterEach( () => {
	delete global.wp;
	delete global.jQuery;
} );

describe( 'refreshCartUi()', () => {
	test.each( [ 'cart-block', 'checkout-block' ] )(
		'invalidates the wc/store/cart resolution on the %s context',
		( context ) => {
			refreshCartUi( context );

			expect( mockDispatch ).toHaveBeenCalledWith( 'wc/store/cart' );
			expect( mockInvalidateResolutionForStore ).toHaveBeenCalledTimes( 1 );
			expect( mockJQueryTrigger ).not.toHaveBeenCalled();
		}
	);

	test( 'triggers a jQuery fragment refresh on the product context', () => {
		refreshCartUi( 'product' );

		expect( mockJQueryTrigger ).toHaveBeenCalledWith( 'wc_fragment_refresh' );
		expect( mockDispatch ).not.toHaveBeenCalled();
	} );

	test( 'does nothing on a context that is neither a block context nor product', () => {
		refreshCartUi( 'checkout' );

		expect( mockDispatch ).not.toHaveBeenCalled();
		expect( mockJQueryTrigger ).not.toHaveBeenCalled();
	} );

	test( 'does not throw on a block context when wp.data is absent', () => {
		delete global.wp;

		expect( () => refreshCartUi( 'checkout-block' ) ).not.toThrow();
	} );

	test( 'does not throw on the product context when jQuery is absent', () => {
		mockHasJQuery.mockReturnValue( false );

		expect( () => refreshCartUi( 'product' ) ).not.toThrow();
		expect( mockJQueryTrigger ).not.toHaveBeenCalled();
	} );
} );
