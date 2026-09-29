import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import Edit from './edit';

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( { className } ) => ( { className } ),
} ) );

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

jest.mock( './v6-button-preview', () => ( {
	V6ButtonPreview: ( { pageType } ) => (
		<div className="ppcp-v6-button-preview">{ pageType }</div>
	),
} ) );

const defaultConfig = {
	placementEnabled: true,
	settingsUrl: '/wp-admin/settings',
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
	global.PcpMiniCartSmartButtonsBlock = { ...defaultConfig };
} );

afterEach( () => {
	delete global.PcpMiniCartSmartButtonsBlock;
} );

describe( 'Edit', () => {
	test( 'shows the placement-disabled warning and settings link when the "Mini cart" buttons placement is disabled', () => {
		global.PcpMiniCartSmartButtonsBlock = {
			...defaultConfig,
			placementEnabled: false,
		};

		render( <Edit /> );

		expect(
			screen.getByText( /“Mini cart” buttons placement is disabled/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'PayPal Payments Settings' } )
		).toHaveAttribute( 'href', defaultConfig.settingsUrl );
		expect(
			screen.queryByText( /Pay Later messaging preview unavailable/ )
		).not.toBeInTheDocument();
	} );

	test( 'renders the v6 button preview when the v6 SDK flag and its config are present', () => {
		render( <Edit /> );

		expect(
			document.querySelector( '.ppcp-v6-button-preview' )
		).toHaveTextContent( 'cart' );
	} );

	test( 'falls back to the generic placeholder when the v6 SDK flag is off', () => {
		global.PcpMiniCartSmartButtonsBlock = {
			...defaultConfig,
			isSdkV6Active: false,
		};

		render( <Edit /> );

		expect(
			document.querySelector( '.ppcp-v6-button-preview' )
		).not.toBeInTheDocument();
		expect(
			document.querySelector( '.ppcp-preview-placeholder' )
		).toBeInTheDocument();
	} );

	test( 'falls back to the generic placeholder when the v6 sdk config is missing', () => {
		global.PcpMiniCartSmartButtonsBlock = {
			...defaultConfig,
			sdkV6: null,
		};

		render( <Edit /> );

		expect(
			document.querySelector( '.ppcp-v6-button-preview' )
		).not.toBeInTheDocument();
		expect(
			document.querySelector( '.ppcp-preview-placeholder' )
		).toBeInTheDocument();
	} );

	test( 'falls back to the generic placeholder when the button style config is missing', () => {
		global.PcpMiniCartSmartButtonsBlock = {
			...defaultConfig,
			buttonStyle: null,
		};

		render( <Edit /> );

		expect(
			document.querySelector( '.ppcp-v6-button-preview' )
		).not.toBeInTheDocument();
		expect(
			document.querySelector( '.ppcp-preview-placeholder' )
		).toBeInTheDocument();
	} );
} );
