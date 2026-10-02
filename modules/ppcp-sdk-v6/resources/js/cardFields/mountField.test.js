jest.mock( './cardFieldStyles', () => ( {
	hostedFieldTextStyles: () => ( { color: 'rgb(0, 0, 0)' } ),
} ) );

jest.mock(
	'@ppcp-button/Helper/Hiding',
	() => ( { hide: jest.fn() } ),
	{ virtual: true }
);

import { mountField, unmountField } from './mountField';

function makeCardSession( id ) {
	return {
		createCardFieldsComponent: jest.fn( () => {
			const element = document.createElement( 'div' );
			element.dataset.session = id;
			return element;
		} ),
	};
}

function hostedFieldsIn( slot ) {
	return slot.querySelectorAll( '.ppcp-sdk-v6-card-field' );
}

let numberSlot;
let expirySlot;
let numberInput;
let expiryInput;

beforeEach( () => {
	document.body.innerHTML = `
		<div id="number-slot"><input id="number" /></div>
		<div id="expiry-slot"><input id="expiry" /></div>
	`;
	numberSlot = document.querySelector( '#number-slot' );
	expirySlot = document.querySelector( '#expiry-slot' );
	numberInput = document.querySelector( '#number' );
	expiryInput = document.querySelector( '#expiry' );
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'unmountField()', () => {
	test( 'removes only the hosted field next to the given input', () => {
		const session = makeCardSession( 'one' );
		mountField( session, 'number', numberInput );
		mountField( session, 'expiry', expiryInput );

		unmountField( numberInput );

		expect( hostedFieldsIn( numberSlot ) ).toHaveLength( 0 );
		expect( hostedFieldsIn( expirySlot ) ).toHaveLength( 1 );
		expect( numberSlot.contains( numberInput ) ).toBe( true );
	} );

	test( 'makes the input mountable again from a new session', () => {
		mountField( makeCardSession( 'old' ), 'number', numberInput );
		expect( numberInput.hidden ).toBe( true );

		unmountField( numberInput );
		expect( numberInput.hidden ).toBe( false );

		mountField( makeCardSession( 'new' ), 'number', numberInput );

		const fields = hostedFieldsIn( numberSlot );
		expect( fields ).toHaveLength( 1 );
		expect( fields[ 0 ].dataset.session ).toBe( 'new' );
		expect( numberInput.hidden ).toBe( true );
	} );

	test( 'leaves an input without a hosted field usable', () => {
		unmountField( numberInput );

		expect( numberInput.hidden ).toBe( false );
		expect( hostedFieldsIn( numberSlot ) ).toHaveLength( 0 );
	} );

	test.each( [ [ null ], [ undefined ] ] )(
		'does nothing when the input is %p',
		( input ) => {
			mountField( makeCardSession( 'one' ), 'number', numberInput );

			expect( () => unmountField( input ) ).not.toThrow();
			expect( hostedFieldsIn( numberSlot ) ).toHaveLength( 1 );
		}
	);
} );
