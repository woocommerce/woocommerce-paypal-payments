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
	SelectControl: ( { label } ) => <div>{ label }</div>,
	Spinner: () => <div className="components-spinner" />,
} ) );

jest.mock( './hooks/script-params', () => ( {
	useScriptParams: jest.fn(),
} ) );

jest.mock(
	'./hooks/use-preview-controller',
	() => ( {
		usePreviewController: jest.fn(),
	} ),
	{ virtual: true }
);

jest.mock( '@paypal/react-paypal-js', () => ( {
	PayPalScriptProvider: jest.fn( ( { children } ) => children ),
	PayPalMessages: jest.fn( () => null ),
} ) );

const { useScriptParams } = require( './hooks/script-params' );
const { usePreviewController } = require( './hooks/use-preview-controller' );

const defaultConfig = {
	placementEnabled: true,
	payLaterSettingsUrl: '/wp-admin/paylater-settings',
	ajax: {
		cart_script_params: { endpoint: '/wp-json/ppcp/cart-script-params' },
	},
};

const defaultProps = {
	attributes: {
		id: 'ppcp-test',
		layout: 'text',
		logo: 'primary',
		position: 'left',
		color: 'black',
		size: '14',
		flexColor: 'blue',
		flexRatio: '8x1',
		placement: 'auto',
	},
	clientId: 'test-client-id',
	setAttributes: jest.fn(),
};

beforeEach( () => {
	global.PcpPayLaterBlock = { ...defaultConfig };
	global.wp = {
		data: {
			select: () => ( { getEditedPostContent: () => '' } ),
			dispatch: () => ( { removeBlock: jest.fn() } ),
		},
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
	global.PcpPayLaterBlock = {
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
	global.PcpPayLaterBlock = { ...defaultConfig, placementEnabled: false };
	useScriptParams.mockReturnValue( null );

	render( <Edit { ...defaultProps } /> );

	expect(
		screen.getByText( /messaging placement is disabled/ )
	).toBeInTheDocument();
} );

describe( 'when the v6 SDK flag is inactive', () => {
	test( 'renders the Layout control and respects a flex layout attribute', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );
		const { PayPalMessages } = require( '@paypal/react-paypal-js' );

		render(
			<Edit
				{ ...defaultProps }
				attributes={ { ...defaultProps.attributes, layout: 'flex' } }
			/>
		);

		expect( screen.getByText( 'Layout' ) ).toBeInTheDocument();
		expect( PayPalMessages.mock.calls[ 0 ][ 0 ].style.layout ).toBe(
			'flex'
		);
	} );
} );

describe( 'when the v6 SDK flag is active', () => {
	beforeEach( () => {
		global.PcpPayLaterBlock = { ...defaultConfig, isSdkV6Active: true };
	} );

	test( 'hides the Layout control', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );

		render( <Edit { ...defaultProps } /> );

		expect( screen.queryByText( 'Layout' ) ).not.toBeInTheDocument();
	} );

	test( 'hides the banner-only controls even when the stored layout is flex', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );

		render(
			<Edit
				{ ...defaultProps }
				attributes={ { ...defaultProps.attributes, layout: 'flex' } }
			/>
		);

		expect( screen.queryByText( 'Color' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Ratio' ) ).not.toBeInTheDocument();
	} );

	test( 'forces a text preview style even when the stored layout is flex', () => {
		useScriptParams.mockReturnValue( {
			url_params: { 'client-id': 'test' },
		} );
		const { PayPalMessages } = require( '@paypal/react-paypal-js' );

		render(
			<Edit
				{ ...defaultProps }
				attributes={ { ...defaultProps.attributes, layout: 'flex' } }
			/>
		);

		expect( PayPalMessages.mock.calls[ 0 ][ 0 ].style.layout ).toBe(
			'text'
		);
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
