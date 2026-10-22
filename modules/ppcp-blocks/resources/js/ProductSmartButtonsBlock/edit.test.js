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

jest.mock( '../MiniCartSmartButtonsBlock/v6-button-preview', () => ( {
	V6ButtonPreview: ( { pageType, sdkV6, style } ) => (
		<div
			className="ppcp-v6-button-preview"
			data-page-type={ pageType }
			data-client-id={ sdkV6.clientId }
			data-color-class={ style.colorClass }
		/>
	),
} ) );

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
	isSdkV6Active: false,
	settingsUrl: '/wp-admin/settings',
	ajax: {
		cart_script_params: { endpoint: '/wp-json/ppcp/cart-script-params' },
	},
};

const v6Config = {
	isSdkV6Active: true,
	sdkV6: {
		sdkUrl: 'https://example.test/v6-sdk.js',
		clientId: 'client-id',
		currency: 'USD',
		locale: 'en_US',
	},
	buttonStyle: {
		colorClass: 'paypal-blue',
		borderRadius: '8px',
		height: '35px',
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

	test( 'shows the timed-out placeholder immediately when the script params request fails', () => {
		useScriptParams.mockReturnValue( false );

		render( <Edit /> );

		expect(
			document.querySelector( '.ppcp-preview-placeholder' )
		).toHaveTextContent( 'timed-out' );
	} );

	describe( 'SDK v6 preview', () => {
		test( 'renders the v6 button preview for the product page with the configured sdk and style', () => {
			global.PcpProductSmartButtonsBlock = {
				...defaultConfig,
				...v6Config,
			};

			render( <Edit /> );

			const preview = document.querySelector( '.ppcp-v6-button-preview' );
			expect( preview ).toHaveAttribute(
				'data-page-type',
				'product-details'
			);
			expect( preview ).toHaveAttribute( 'data-client-id', 'client-id' );
			expect( preview ).toHaveAttribute(
				'data-color-class',
				'paypal-blue'
			);
			expect( useScriptParams ).not.toHaveBeenCalled();
		} );

		test.each( [
			[ 'the v6 SDK flag is off', { isSdkV6Active: false } ],
			[ 'the v6 sdk config is missing', { sdkV6: null } ],
			[ 'the button style config is missing', { buttonStyle: null } ],
		] )( 'falls back to the v5 preview when %s', ( _name, overrides ) => {
			global.PcpProductSmartButtonsBlock = {
				...defaultConfig,
				...v6Config,
				...overrides,
			};
			useScriptParams.mockReturnValue( null );

			render( <Edit /> );

			expect(
				document.querySelector( '.ppcp-v6-button-preview' )
			).not.toBeInTheDocument();
			expect(
				document.querySelector( '.ppcp-preview-placeholder' )
			).toBeInTheDocument();
			expect( useScriptParams ).toHaveBeenCalled();
		} );
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
