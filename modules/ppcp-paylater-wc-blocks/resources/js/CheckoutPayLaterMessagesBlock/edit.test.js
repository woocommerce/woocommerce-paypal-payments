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

jest.mock( '@paypal/react-paypal-js', () => ( {
	PayPalScriptProvider: ( { children } ) => children,
	PayPalMessages: jest.fn( () => null ),
} ) );

const {
	useScriptParams,
} = require( '@ppcp-paylater-block/hooks/script-params' );

const defaultConfig = {
	vaultingEnabled: false,
	placementEnabled: true,
	settingsUrl: '/wp-admin/settings',
	payLaterSettingsUrl: '/wp-admin/paylater-settings',
	ajax: {
		cart_script_params: { endpoint: '/wp-json/ppcp/cart-script-params' },
	},
	config: {
		checkout: {
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
	global.PcpCheckoutPayLaterBlock = { ...defaultConfig };
	global.wp = {
		data: { select: () => ( { getEditedPostContent: () => '' } ) },
	};
	jest.clearAllMocks();
	jest.clearAllTimers();
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

test( 'shows vaulting warning when vaulting is enabled', () => {
	global.PcpCheckoutPayLaterBlock = {
		...defaultConfig,
		vaultingEnabled: true,
	};
	useScriptParams.mockReturnValue( null );

	render( <Edit { ...defaultProps } /> );

	expect(
		screen.getByText( /cannot be used while PayPal Vaulting is active/ )
	).toBeInTheDocument();
} );

test( 'shows placement warning when placement is disabled', () => {
	global.PcpCheckoutPayLaterBlock = {
		...defaultConfig,
		placementEnabled: false,
	};
	useScriptParams.mockReturnValue( null );

	render( <Edit { ...defaultProps } /> );

	expect(
		screen.getByText( /messaging placement is disabled/ )
	).toBeInTheDocument();
} );
