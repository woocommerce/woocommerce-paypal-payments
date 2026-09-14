const mockCreatedButtons = [];
const mockCreateMethodButton = jest.fn( () => {
	const button = document.createElement( 'paypal-button' );
	mockCreatedButtons.push( button );
	return button;
} );

jest.mock( '../../components/buttonRenderer', () => ( {
	createMethodButton: ( ...args ) => mockCreateMethodButton( ...args ),
} ) );

import { render } from '@testing-library/react';
import { createElement, StrictMode } from '@wordpress/element';
import { V6ButtonContainer } from '../../blocks/V6ButtonContainer';

const baseProps = {
	method: 'paypal',
	styles: {},
	createOrderFn: () => {},
	payLaterDetails: null,
};

function renderContainer( overrides ) {
	return render(
		createElement( V6ButtonContainer, { ...baseProps, ...overrides } )
	);
}

afterEach( () => {
	mockCreateMethodButton.mockClear();
	mockCreatedButtons.length = 0;
} );

describe( 'V6ButtonContainer', () => {
	test( 'mounts exactly one configured button', () => {
		const { container } = renderContainer( { session: {} } );

		expect( mockCreateMethodButton ).toHaveBeenCalledTimes( 1 );
		expect( container.querySelectorAll( 'paypal-button' ) ).toHaveLength(
			1
		);
	} );

	test( 'removes the button on unmount', () => {
		const { unmount } = renderContainer( { session: {} } );
		const button = mockCreatedButtons[ 0 ];

		unmount();

		expect( button.isConnected ).toBe( false );
	} );

	test( 'does not recreate the button when re-rendered with the same session', () => {
		const session = {};
		const { rerender, container } = renderContainer( { session } );

		rerender(
			createElement( V6ButtonContainer, {
				...baseProps,
				session,
				createOrderFn: () => {},
			} )
		);

		expect( mockCreateMethodButton ).toHaveBeenCalledTimes( 1 );
		expect( container.querySelectorAll( 'paypal-button' ) ).toHaveLength(
			1
		);
	} );

	test( 'recreates the button when the session changes', () => {
		const { rerender, container } = renderContainer( {
			session: { id: 1 },
		} );
		const first = mockCreatedButtons[ 0 ];

		rerender(
			createElement( V6ButtonContainer, {
				...baseProps,
				session: { id: 2 },
			} )
		);

		expect( mockCreateMethodButton ).toHaveBeenCalledTimes( 2 );
		expect( first.isConnected ).toBe( false );
		expect( container.querySelectorAll( 'paypal-button' ) ).toHaveLength(
			1
		);
	} );

	test( 'survives StrictMode double-mount with a single button', () => {
		const { container } = render(
			createElement(
				StrictMode,
				null,
				createElement( V6ButtonContainer, {
					...baseProps,
					session: {},
				} )
			)
		);

		expect( container.querySelectorAll( 'paypal-button' ) ).toHaveLength(
			1
		);
	} );
} );
