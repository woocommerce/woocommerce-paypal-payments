import { render, screen } from '@testing-library/react';
import { useEffect } from '@wordpress/element';
import '@testing-library/jest-dom';
import Edit from './edit';

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( {} ),
} ) );

jest.mock( '@ppcp-paylater-block/hooks/script-params', () => ( {
	useScriptParams: jest.fn(),
} ) );

jest.mock(
	'@ppcp-paylater-block/hooks/use-preview-timeout',
	() => ( {
		usePreviewTimeout: jest.fn( () => false ),
	} ),
	{ virtual: true }
);

jest.mock(
	'@ppcp-paylater-block/hooks/use-preview-controller',
	() => ( {
		usePreviewController: jest.fn(),
	} ),
	{ virtual: true }
);

jest.mock(
	'@ppcp-paylater-block/components/preview-placeholder',
	() => ( {
		PreviewPlaceholder: ( { timedOut } ) => (
			<div className="ppcp-preview-placeholder">
				{ timedOut ? 'timed-out' : 'loading' }
			</div>
		),
	} ),
	{ virtual: true }
);

jest.mock( '@paypal/react-paypal-js', () => ( {
	PayPalScriptProvider: jest.fn( ( { children } ) => children ),
	PayPalButtons: jest.fn( () => null ),
} ) );

const {
	useScriptParams,
} = require( '@ppcp-paylater-block/hooks/script-params' );
const {
	usePreviewController,
} = require( '@ppcp-paylater-block/hooks/use-preview-controller' );

const defaultConfig = {
	placementEnabled: true,
	settingsUrl: '/wp-admin/settings',
	ajax: {
		cart_script_params: { endpoint: '/wp-json/ppcp/cart-script-params' },
	},
};

beforeEach( () => {
	global.PcpProductSmartButtonsBlock = { ...defaultConfig };
	jest.clearAllMocks();
	usePreviewController.mockReturnValue( {
		containerRef: { current: null },
		renderKey: 0,
	} );
} );

afterEach( () => {
	delete global.PcpProductSmartButtonsBlock;
} );

describe( 'Edit', () => {
	test( 'shows the placement-disabled warning and settings link when the "Product" buttons placement is disabled', () => {
		global.PcpProductSmartButtonsBlock = {
			...defaultConfig,
			placementEnabled: false,
		};
		useScriptParams.mockReturnValue( null );

		render( <Edit /> );

		expect(
			screen.getByText( /“Product” buttons placement is disabled/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'PayPal Payments Settings' } )
		).toHaveAttribute( 'href', defaultConfig.settingsUrl );
	} );

	test( 'shows the preview placeholder while script params are not yet resolved', () => {
		useScriptParams.mockReturnValue( null );

		render( <Edit /> );

		expect(
			document.querySelector( '.ppcp-preview-placeholder' )
		).toBeInTheDocument();
	} );

	test( 'renders the PayPal buttons preview once script params resolve', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );

		render( <Edit /> );

		const { PayPalButtons } = require( '@paypal/react-paypal-js' );
		expect( PayPalButtons ).toHaveBeenCalled();
	} );

	describe( 'preview controller integration', () => {
		test( 'attaches the container ref returned by usePreviewController to the overlay child', () => {
			useScriptParams.mockReturnValue( {
				url_params: { 'client-id': 'test' },
			} );
			const containerRef = { current: null };
			usePreviewController.mockReturnValue( {
				containerRef,
				renderKey: 0,
			} );

			render( <Edit /> );

			expect( containerRef.current ).toHaveClass( 'ppcp-overlay-child' );
		} );

		test( 'remounts the PayPalScriptProvider subtree when the render key changes', () => {
			useScriptParams.mockReturnValue( {
				url_params: { 'client-id': 'test' },
			} );
			const {
				PayPalScriptProvider,
			} = require( '@paypal/react-paypal-js' );
			let mountCount = 0;
			PayPalScriptProvider.mockImplementation( ( { children } ) => {
				useEffect( () => {
					mountCount++;
				}, [] );
				return children;
			} );
			usePreviewController.mockReturnValue( {
				containerRef: { current: null },
				renderKey: 1,
			} );

			const { rerender } = render( <Edit /> );
			expect( mountCount ).toBe( 1 );

			usePreviewController.mockReturnValue( {
				containerRef: { current: null },
				renderKey: 2,
			} );
			rerender( <Edit /> );

			expect( mountCount ).toBe( 2 );
		} );
	} );
} );
