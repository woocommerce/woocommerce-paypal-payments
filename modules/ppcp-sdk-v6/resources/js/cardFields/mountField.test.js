jest.mock( './cardFieldStyles', () => ( {
	hostedFieldTextStyles: () => ( { color: 'rgb(0, 0, 0)' } ),
} ) );

jest.mock(
	'@ppcp-button/Helper/Hiding',
	() => ( { hide: jest.fn() } ),
	{ virtual: true }
);

import { mountField } from './mountField';

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

beforeEach( () => {
	document.body.innerHTML = `
		<div id="number-slot"><input id="number" /></div>
		<div id="expiry-slot"><input id="expiry" /></div>
	`;
	numberSlot = document.querySelector( '#number-slot' );
	expirySlot = document.querySelector( '#expiry-slot' );
	numberInput = document.querySelector( '#number' );
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'mountField()', () => {
	test( 'adds a hosted field next to the input and hides the input', () => {
		const session = makeCardSession( 'one' );
		mountField( session, 'number', numberInput );

		const fields = hostedFieldsIn( numberSlot );
		expect( fields ).toHaveLength( 1 );
		expect( fields[ 0 ].dataset.session ).toBe( 'one' );
		expect( numberInput.hidden ).toBe( true );
		expect( hostedFieldsIn( expirySlot ) ).toHaveLength( 0 );
	} );

	test.each( [ [ null ], [ undefined ] ] )(
		'does nothing when the input is %p',
		( input ) => {
			const session = makeCardSession( 'one' );

			expect( () => mountField( session, 'number', input ) ).not.toThrow();
			expect( session.createCardFieldsComponent ).not.toHaveBeenCalled();
		}
	);

	test( 'does nothing when the input is already hidden', () => {
		numberInput.hidden = true;

		mountField( makeCardSession( 'one' ), 'number', numberInput );

		expect( hostedFieldsIn( numberSlot ) ).toHaveLength( 0 );
	} );
} );
