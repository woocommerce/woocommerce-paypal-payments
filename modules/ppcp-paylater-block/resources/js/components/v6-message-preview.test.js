import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

const mockUseV6MessagePreview = jest.fn();
jest.mock( '../hooks/use-v6-message-preview', () => ( {
	useV6MessagePreview: ( ...args ) => mockUseV6MessagePreview( ...args ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Spinner: () => <div className="components-spinner" />,
} ) );

import { V6MessagePreview } from './v6-message-preview';

const baseProps = () => ( {
	sdkV6: {
		sdkUrl: 'https://example.test/v6-sdk.js',
		clientId: 'client-id',
		currency: 'USD',
		locale: 'en_US',
	},
	amount: '50.00',
	pageType: 'cart',
	style: {
		logoType: 'WORDMARK',
		logoPosition: 'LEFT',
		textColor: 'BLACK',
		fontSize: '',
	},
} );

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'V6MessagePreview', () => {
	test( 'shows a spinner while the preview is loading', () => {
		mockUseV6MessagePreview.mockReturnValue( {
			containerRef: jest.fn(),
			loaded: false,
			failed: false,
		} );

		render( <V6MessagePreview { ...baseProps() } /> );

		expect(
			document.querySelector( '.components-spinner' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Pay Later messaging preview unavailable/ )
		).not.toBeInTheDocument();
	} );

	test( 'shows the unavailable text immediately when the preview failed', () => {
		mockUseV6MessagePreview.mockReturnValue( {
			containerRef: jest.fn(),
			loaded: false,
			failed: true,
		} );

		render( <V6MessagePreview { ...baseProps() } /> );

		expect(
			screen.getByText( /Pay Later messaging preview unavailable/ )
		).toBeInTheDocument();
	} );

	test( 'shows neither placeholder nor spinner once the preview has loaded', () => {
		mockUseV6MessagePreview.mockReturnValue( {
			containerRef: jest.fn(),
			loaded: true,
			failed: false,
		} );

		render( <V6MessagePreview { ...baseProps() } /> );

		expect(
			document.querySelector( '.components-spinner' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( /Pay Later messaging preview unavailable/ )
		).not.toBeInTheDocument();
	} );

	test( 'attaches the container ref to the overlay child that hosts the message', () => {
		const containerRef = jest.fn();
		mockUseV6MessagePreview.mockReturnValue( {
			containerRef,
			loaded: false,
			failed: false,
		} );

		const { container } = render( <V6MessagePreview { ...baseProps() } /> );

		const target = container.querySelector( '.ppcp-overlay-child' );
		expect( target ).not.toHaveClass( 'ppcp-unclicable-overlay' );
		expect( containerRef ).not.toHaveBeenCalledWith( null );
	} );
} );
