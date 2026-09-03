const mockCreateOrder = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	createOrder: ( ...args ) => mockCreateOrder( ...args ),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

const mockRevealWalletGateway = jest.fn();
jest.mock( '../methods/gatewayPlacement', () => ( {
	revealMethodGateway: ( ...args ) => mockRevealWalletGateway( ...args ),
} ) );

import { initCardButton } from './renderCardButton';

const baseConfig = ( overrides = {} ) => ( {
	page_context: 'checkout',
	buyer_country: 'US',
	card_button: {
		row: true,
		wrapper: '#card-button-wrapper',
		payment_method: 'ppcp-card-button-gateway',
		styles: {},
	},
	...overrides,
} );

function sessionsWithCard( session ) {
	return jest.fn().mockResolvedValue( { map: { card: session } } );
}

beforeEach( () => {
	jest.clearAllMocks();
	document.body.innerHTML = '<div id="card-button-wrapper"></div>';
} );

describe( 'initCardButton', () => {
	test( 'does nothing when card_button.row is false', async () => {
		const ensureSessions = jest.fn();

		await initCardButton(
			baseConfig( { card_button: { row: false } } ),
			ensureSessions
		);

		expect( ensureSessions ).not.toHaveBeenCalled();
		expect( document.querySelector( '#card-button-wrapper' ).childElementCount ).toBe( 0 );
	} );

	test( 'does nothing when the wrapper is not in the DOM', async () => {
		document.body.innerHTML = '';
		const ensureSessions = jest.fn();

		await initCardButton( baseConfig(), ensureSessions );

		expect( ensureSessions ).not.toHaveBeenCalled();
	} );

	test( 'does nothing when the wrapper already has children', async () => {
		document.querySelector( '#card-button-wrapper' ).innerHTML =
			'<button></button>';
		const ensureSessions = jest.fn();

		await initCardButton( baseConfig(), ensureSessions );

		expect( ensureSessions ).not.toHaveBeenCalled();
	} );

	test( 'renders one button when the initial render and a checkout update overlap', async () => {
		const session = { start: jest.fn() };
		const config = baseConfig();
		const ensureSessions = sessionsWithCard( session );

		const first = initCardButton( config, ensureSessions );
		const second = initCardButton( config, ensureSessions );
		await Promise.all( [ first, second ] );

		const wrapper = document.querySelector( '#card-button-wrapper' );
		expect( wrapper.childElementCount ).toBe( 1 );
		expect(
			wrapper.querySelectorAll( 'paypal-basic-card-container' )
		).toHaveLength( 1 );
	} );

	test( 'lands the button in the replacement wrapper when the order-review DOM is swapped mid-flight', async () => {
		const session = { start: jest.fn() };
		const config = baseConfig();
		const oldWrapper = document.querySelector( '#card-button-wrapper' );

		const ensureSessions = jest.fn().mockImplementation( async () => {
			oldWrapper.remove();
			const newWrapper = document.createElement( 'div' );
			newWrapper.id = 'card-button-wrapper';
			document.body.appendChild( newWrapper );
			return { map: { card: session } };
		} );

		await initCardButton( config, ensureSessions );

		expect( oldWrapper.childElementCount ).toBe( 0 );
		expect(
			document.querySelector( '#card-button-wrapper' )
				.childElementCount
		).toBe( 1 );
	} );

	test( 'leaves the row empty and does not reveal the gateway when no card session exists (buyer ineligible)', async () => {
		const ensureSessions = jest
			.fn()
			.mockResolvedValue( { map: {} } );

		await initCardButton( baseConfig(), ensureSessions );

		expect(
			document.querySelector( '#card-button-wrapper' ).childElementCount
		).toBe( 0 );
		expect( mockRevealWalletGateway ).not.toHaveBeenCalled();
	} );

	test( 'assembles the button inside its container before either enters the document', async () => {
		const session = { start: jest.fn() };
		const target = document.querySelector( '#card-button-wrapper' );
		let containerAtInsertion = null;
		const originalAppendChild = target.appendChild.bind( target );
		target.appendChild = jest.fn( ( node ) => {
			containerAtInsertion = {
				tagName: node.tagName,
				childTagName: node.firstElementChild?.tagName,
				childAlreadyConnected: node.firstElementChild?.isConnected,
			};
			return originalAppendChild( node );
		} );

		await initCardButton( baseConfig(), sessionsWithCard( session ) );

		expect( containerAtInsertion.tagName ).toBe(
			'PAYPAL-BASIC-CARD-CONTAINER'
		);
		expect( containerAtInsertion.childTagName ).toBe(
			'PAYPAL-BASIC-CARD-BUTTON'
		);
		// The button was inside the container before either node was connected.
		expect( containerAtInsertion.childAlreadyConnected ).toBe( false );
	} );

	test( 'reveals the wallet gateway row after the button is inserted', async () => {
		const session = { start: jest.fn() };
		const config = baseConfig();

		await initCardButton( config, sessionsWithCard( session ) );

		expect(
			document.querySelector( '#card-button-wrapper' ).childElementCount
		).toBeGreaterThan( 0 );
		expect( mockRevealWalletGateway ).toHaveBeenCalledWith(
			{
				id: 'ppcp-card-button-gateway',
				wrapper: '#card-button-wrapper',
			},
			config
		);
	} );

	test( 'does not react to a plain click event', async () => {
		const session = { start: jest.fn() };

		await initCardButton( baseConfig(), sessionsWithCard( session ) );

		const button = document.querySelector(
			'#card-button-wrapper paypal-basic-card-button'
		);
		button.dispatchEvent( new Event( 'click', { bubbles: true } ) );

		expect( session.start ).not.toHaveBeenCalled();
	} );

	test( 'starts the session with the button as target and forwards the gateway id to createOrder on bcdc-click', async () => {
		const session = { start: jest.fn().mockResolvedValue( undefined ) };
		const config = baseConfig();
		mockCreateOrder.mockReturnValue( 'ORDER_PROMISE' );

		await initCardButton( config, sessionsWithCard( session ) );

		const button = document.querySelector(
			'#card-button-wrapper paypal-basic-card-button'
		);
		button.dispatchEvent( new Event( 'bcdc-click' ) );
		await Promise.resolve();
		await Promise.resolve();

		expect( mockCreateOrder ).toHaveBeenCalledWith(
			config,
			'checkout',
			'card',
			undefined,
			'ppcp-card-button-gateway'
		);
		expect( session.start ).toHaveBeenCalledWith(
			{ presentationMode: 'auto', targetElement: button },
			'ORDER_PROMISE'
		);
	} );

	test( 'routes a thrown session.start error to handleError', async () => {
		const error = new Error( 'card session failed' );
		const session = { start: jest.fn().mockRejectedValue( error ) };

		await initCardButton( baseConfig(), sessionsWithCard( session ) );

		const button = document.querySelector(
			'#card-button-wrapper paypal-basic-card-button'
		);
		button.dispatchEvent( new Event( 'bcdc-click' ) );
		await Promise.resolve();
		await Promise.resolve();

		expect( mockHandleError ).toHaveBeenCalledWith( error );
	} );
} );
