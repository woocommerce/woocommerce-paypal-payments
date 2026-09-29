import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

const mockUseV6ButtonPreview = jest.fn();
jest.mock( './use-v6-button-preview', () => ( {
	useV6ButtonPreview: ( ...args ) => mockUseV6ButtonPreview( ...args ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Spinner: () => <div className="components-spinner" />,
} ) );

import { V6ButtonPreview } from './v6-button-preview';

const baseProps = () => ( {
	sdkV6: {
		sdkUrl: 'https://example.test/v6-sdk.js',
		clientId: 'client-id',
		locale: 'en_US',
	},
	pageType: 'cart',
	style: {
		colorClass: 'paypal-blue',
		borderRadius: '8px',
		height: '35px',
	},
} );

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'V6ButtonPreview', () => {
	test( 'shows a spinner while the preview is loading', () => {
		mockUseV6ButtonPreview.mockReturnValue( {
			containerRef: jest.fn(),
			loaded: false,
			failed: false,
		} );

		render( <V6ButtonPreview { ...baseProps() } /> );

		expect(
			document.querySelector( '.components-spinner' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Pay Later messaging preview unavailable/ )
		).not.toBeInTheDocument();
	} );

	test( 'shows the unavailable text immediately when the preview failed', () => {
		mockUseV6ButtonPreview.mockReturnValue( {
			containerRef: jest.fn(),
			loaded: false,
			failed: true,
		} );

		render( <V6ButtonPreview { ...baseProps() } /> );

		expect(
			screen.getByText( /Pay Later messaging preview unavailable/ )
		).toBeInTheDocument();
	} );

	test( 'shows neither placeholder nor spinner once the preview has loaded', () => {
		mockUseV6ButtonPreview.mockReturnValue( {
			containerRef: jest.fn(),
			loaded: true,
			failed: false,
		} );

		render( <V6ButtonPreview { ...baseProps() } /> );

		expect(
			document.querySelector( '.components-spinner' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( /Pay Later messaging preview unavailable/ )
		).not.toBeInTheDocument();
	} );

	test( 'attaches the container ref to the overlay child that hosts the button', () => {
		const containerRef = jest.fn();
		mockUseV6ButtonPreview.mockReturnValue( {
			containerRef,
			loaded: false,
			failed: false,
		} );

		const { container } = render( <V6ButtonPreview { ...baseProps() } /> );

		const target = container.querySelector( '.ppcp-overlay-child' );
		expect( target ).not.toHaveClass( 'ppcp-unclicable-overlay' );
		expect( containerRef ).not.toHaveBeenCalledWith( null );
	} );
} );
