import { render, act, screen } from '@testing-library/react';
import { useEffect } from '@wordpress/element';
import '@testing-library/jest-dom';
import Edit from './edit';

jest.useFakeTimers();

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => children,
	useBlockProps: () => ( {} ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
	Spinner: () => <div className="components-spinner" />,
} ) );

jest.mock( '@ppcp-paylater-block/hooks/script-params', () => ( {
	useScriptParams: jest.fn(),
} ) );

jest.mock(
	'@ppcp-paylater-block/hooks/use-preview-controller',
	() => ( {
		usePreviewController: jest.fn(),
	} ),
	{ virtual: true }
);

jest.mock( '@paypal/react-paypal-js', () => ( {
	PayPalScriptProvider: jest.fn( ( { children } ) => children ),
	PayPalMessages: jest.fn( () => null ),
} ) );

jest.mock( '@ppcp-paylater-block/components/v6-message-preview', () => ( {
	V6MessagePreview: jest.fn( () => <div data-testid="v6-message-preview" /> ),
} ) );

const {
	useScriptParams,
} = require( '@ppcp-paylater-block/hooks/script-params' );
const {
	usePreviewController,
} = require( '@ppcp-paylater-block/hooks/use-preview-controller' );
const {
	V6MessagePreview,
} = require( '@ppcp-paylater-block/components/v6-message-preview' );

const defaultConfig = {
	placementEnabled: true,
	settingsUrl: '/wp-admin/settings',
	payLaterSettingsUrl: '/wp-admin/paylater-settings',
	ajax: {
		cart_script_params: { endpoint: '/wp-json/ppcp/cart-script-params' },
	},
	config: {
		cart: {
			layout: 'text',
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
	global.PcpCartPayLaterBlock = { ...defaultConfig };
	global.wp = {
		data: { select: () => ( { getEditedPostContent: () => '' } ) },
	};
	jest.clearAllMocks();
	jest.clearAllTimers();
	usePreviewController.mockReturnValue( {
		containerRef: { current: null },
		renderKey: 0,
	} );
} );

test( 'shows spinner while script params are loading', () => {
	useScriptParams.mockReturnValue( null );

	render( <Edit { ...defaultProps } /> );

	expect(
		document.querySelector( '.components-spinner' )
	).toBeInTheDocument();
} );

test( 'shows placeholder after 10 seconds when script params never resolve', () => {
	useScriptParams.mockReturnValue( null );

	render( <Edit { ...defaultProps } /> );

	act( () => jest.advanceTimersByTime( 10000 ) );

	expect(
		screen.getByText(
			'Pay Later messaging preview unavailable in editor. Messaging will display on the frontend when eligibility conditions are met.'
		)
	).toBeInTheDocument();
	expect(
		document.querySelector( '.components-spinner' )
	).not.toBeInTheDocument();
} );

test( 'shows placeholder after 10 seconds when PayPalMessages never renders', () => {
	useScriptParams.mockReturnValue( {
		url_params: { 'client-id': 'test' },
	} );

	render( <Edit { ...defaultProps } /> );

	act( () => jest.advanceTimersByTime( 10000 ) );

	expect(
		screen.getByText(
			'Pay Later messaging preview unavailable in editor. Messaging will display on the frontend when eligibility conditions are met.'
		)
	).toBeInTheDocument();
} );

test( 'does not show placeholder when PayPalMessages renders within 10 seconds', () => {
	useScriptParams.mockReturnValue( {
		url_params: { 'client-id': 'test' },
	} );

	const { PayPalMessages } = require( '@paypal/react-paypal-js' );
	PayPalMessages.mockImplementation( ( { onRender } ) => {
		useEffect( () => onRender(), [] );
		return null;
	} );

	render( <Edit { ...defaultProps } /> );

	act( () => jest.advanceTimersByTime( 10000 ) );

	expect(
		screen.queryByText( /Pay Later messaging preview unavailable/ )
	).not.toBeInTheDocument();
} );

test( 'ignores a legacy payLaterDisabledByVaulting flag on the global and still renders the preview', () => {
	global.PcpCartPayLaterBlock = {
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

test( 'shows placement warning when placement is disabled', () => {
	global.PcpCartPayLaterBlock = { ...defaultConfig, placementEnabled: false };
	useScriptParams.mockReturnValue( null );

	render( <Edit { ...defaultProps } /> );

	expect(
		screen.getByText( /messaging placement is disabled/ )
	).toBeInTheDocument();
} );

test( 'forces a text preview style when the v6 SDK is active even though the settings use a flex layout', () => {
	global.PcpCartPayLaterBlock = {
		...defaultConfig,
		isSdkV6Active: true,
		config: {
			cart: { ...defaultConfig.config.cart, layout: 'flex' },
		},
	};
	useScriptParams.mockReturnValue( { url_params: { 'client-id': 'test' } } );
	const { PayPalMessages } = require( '@paypal/react-paypal-js' );

	render( <Edit { ...defaultProps } /> );

	expect( PayPalMessages.mock.calls[ 0 ][ 0 ].style.layout ).toBe( 'text' );
} );

test( 'passes through a flex preview style when the v6 SDK is inactive', () => {
	global.PcpCartPayLaterBlock = {
		...defaultConfig,
		config: {
			cart: { ...defaultConfig.config.cart, layout: 'flex' },
		},
	};
	useScriptParams.mockReturnValue( { url_params: { 'client-id': 'test' } } );
	const { PayPalMessages } = require( '@paypal/react-paypal-js' );

	render( <Edit { ...defaultProps } /> );

	expect( PayPalMessages.mock.calls[ 0 ][ 0 ].style.layout ).toBe( 'flex' );
} );

describe( 'PayPal SDK v6 preview', () => {
	const sdkV6 = {
		sdkUrl: 'https://example.test/v6-sdk.js',
		clientId: 'client-id',
		currency: 'USD',
		locale: 'en_US',
	};
	const messageStyle = {
		logoType: 'WORDMARK',
		logoPosition: 'LEFT',
		textColor: 'BLACK',
		fontSize: '',
	};

	test( 'renders V6MessagePreview with the cart page type and sample amount when v6 is active', () => {
		global.PcpCartPayLaterBlock = {
			...defaultConfig,
			isSdkV6Active: true,
			sdkV6,
			messageStyle,
		};

		render( <Edit { ...defaultProps } /> );

		expect(
			screen.getByTestId( 'v6-message-preview' )
		).toBeInTheDocument();
		expect( V6MessagePreview.mock.calls[ 0 ][ 0 ] ).toEqual(
			expect.objectContaining( {
				sdkV6,
				amount: '50.00',
				pageType: 'cart',
				style: messageStyle,
			} )
		);
		expect( useScriptParams ).not.toHaveBeenCalled();
		const { PayPalMessages } = require( '@paypal/react-paypal-js' );
		expect( PayPalMessages ).not.toHaveBeenCalled();
	} );

	test( 'falls back to the v5 preview when v6 is active but sdkV6 data is missing', () => {
		global.PcpCartPayLaterBlock = {
			...defaultConfig,
			isSdkV6Active: true,
			sdkV6: null,
			messageStyle,
		};
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );

		render( <Edit { ...defaultProps } /> );

		expect(
			screen.queryByTestId( 'v6-message-preview' )
		).not.toBeInTheDocument();
		expect( useScriptParams ).toHaveBeenCalled();
	} );

	test( 'shows the unavailable text immediately when the v5 script params request fails', () => {
		useScriptParams.mockReturnValue( false );

		render( <Edit { ...defaultProps } /> );

		expect(
			screen.getByText( /Pay Later messaging preview unavailable/ )
		).toBeInTheDocument();
	} );
} );

describe( 'preview controller integration', () => {
	test( 'attaches the container ref returned by usePreviewController to the overlay child', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );
		const containerRef = { current: null };
		usePreviewController.mockReturnValue( { containerRef, renderKey: 0 } );

		render( <Edit { ...defaultProps } /> );

		expect( containerRef.current ).toHaveClass( 'ppcp-overlay-child' );
	} );

	test( 'remounts the PayPalScriptProvider subtree when the render key changes', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );
		const { PayPalScriptProvider } = require( '@paypal/react-paypal-js' );
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
