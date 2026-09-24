import { render, screen } from '@testing-library/react';
import { useEffect } from '@wordpress/element';
import '@testing-library/jest-dom';
import Edit from './edit';

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => children,
	useBlockProps: () => ( {} ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
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
	PayPalMessages: jest.fn( () => null ),
} ) );

const {
	useScriptParams,
} = require( '@ppcp-paylater-block/hooks/script-params' );
const {
	usePreviewController,
} = require( '@ppcp-paylater-block/hooks/use-preview-controller' );

const defaultConfig = {
	placementEnabled: true,
	isSdkV6Active: false,
	settingsUrl: '/wp-admin/settings',
	payLaterSettingsUrl: '/wp-admin/paylater-settings',
	ajax: {
		cart_script_params: { endpoint: '/wp-json/ppcp/cart-script-params' },
	},
	config: {
		product: {
			layout: 'text',
			color: 'blue',
			ratio: '1x1',
			'logo-position': 'left',
			'logo-type': 'primary',
			'text-color': 'black',
			'text-size': '14',
		},
	},
};

const defaultProps = {
	attributes: { ppcpId: 'ppcp-test' },
	clientId: 'test-client-id',
	setAttributes: jest.fn(),
};

beforeEach( () => {
	global.PcpProductPayLaterBlock = { ...defaultConfig };
	jest.clearAllMocks();
	usePreviewController.mockReturnValue( {
		containerRef: { current: null },
		renderKey: 0,
	} );
} );

afterEach( () => {
	delete global.PcpProductPayLaterBlock;
} );

describe( 'Edit', () => {
	test( 'ignores a legacy payLaterDisabledByVaulting flag on the global and still renders the preview', () => {
		global.PcpProductPayLaterBlock = {
			...defaultConfig,
			payLaterDisabledByVaulting: true,
		};
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );

		render( <Edit { ...defaultProps } /> );

		expect(
			screen.queryByText( /PayPal Vaulting is active/ )
		).not.toBeInTheDocument();
	} );

	test( 'shows the placement-disabled warning when the "Product" placement is disabled', () => {
		global.PcpProductPayLaterBlock = {
			...defaultConfig,
			placementEnabled: false,
		};
		useScriptParams.mockReturnValue( null );

		render( <Edit { ...defaultProps } /> );

		expect(
			screen.getByText( /“Product” messaging placement is disabled/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'PayPal Payments Settings' } )
		).toHaveAttribute( 'href', defaultConfig.payLaterSettingsUrl );
	} );

	test( 'shows the preview placeholder while script params are not yet resolved', () => {
		useScriptParams.mockReturnValue( null );

		render( <Edit { ...defaultProps } /> );

		expect(
			document.querySelector( '.ppcp-preview-placeholder' )
		).toBeInTheDocument();
	} );

	test( 'renders the PayPal messages preview once script params resolve', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );

		render( <Edit { ...defaultProps } /> );

		const { PayPalMessages } = require( '@paypal/react-paypal-js' );
		expect( PayPalMessages ).toHaveBeenCalled();
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

			render( <Edit { ...defaultProps } /> );

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

			const { rerender } = render( <Edit { ...defaultProps } /> );
			expect( mountCount ).toBe( 1 );

			usePreviewController.mockReturnValue( {
				containerRef: { current: null },
				renderKey: 2,
			} );
			rerender( <Edit { ...defaultProps } /> );

			expect( mountCount ).toBe( 2 );
		} );
	} );
} );
