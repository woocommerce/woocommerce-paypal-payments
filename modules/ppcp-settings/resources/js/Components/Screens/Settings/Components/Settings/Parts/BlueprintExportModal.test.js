import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

import BlueprintExportModal from './BlueprintExportModal';

// @wordpress/components cannot load here: the repo mocks @wordpress/i18n without
// isRTL, which that package needs at import time.
jest.mock( '@wordpress/components', () => ( {
	Modal: ( { title, children } ) => (
		<div role="dialog" aria-label={ title }>
			{ children }
		</div>
	),
	Button: ( { children, onClick } ) => (
		<button onClick={ onClick }>{ children }</button>
	),
	ToggleControl: ( { label, help, checked, onChange } ) => (
		<>
			<label htmlFor="toggle">{ label }</label>
			<input
				id="toggle"
				type="checkbox"
				checked={ checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
			<span>{ help }</span>
		</>
	),
} ) );

const mockUseMerchant = jest.fn();

jest.mock( '@ppcp-settings/data', () => ( {
	CommonHooks: {
		useMerchant: () => mockUseMerchant(),
	},
} ) );

jest.mock( '@ppcp-settings/Components/ReusableComponents/Stack', () => ( {
	HStack: ( { children, ...props } ) => <div { ...props }>{ children }</div>,
} ) );

const toggle = () =>
	screen.getByLabelText( 'Include connection credentials', {
		exact: false,
	} );

const exportButton = () => screen.getByRole( 'button', { name: 'Export' } );

// Matched by class, not text: the text is in the DOM in both states.
const warning = () => document.querySelector( '.ppcp--credentials-warning' );

describe( 'BlueprintExportModal', () => {
	let onExport;
	let onCancel;

	beforeEach( () => {
		jest.clearAllMocks();
		mockUseMerchant.mockReturnValue( {
			isConnected: true,
			isSandbox: false,
		} );
		onExport = jest.fn();
		onCancel = jest.fn();
	} );

	const renderModal = () =>
		render(
			<BlueprintExportModal onExport={ onExport } onCancel={ onCancel } />
		);

	it( 'leaves the credentials opt-in off when opened', () => {
		renderModal();

		expect( toggle() ).not.toBeChecked();
	} );

	it( 'keeps the credentials warning collapsed and hidden from assistive technology until the opt-in is enabled', () => {
		// Stays mounted for the CSS reveal, so "not shown" means collapsed.
		renderModal();

		expect( warning() ).not.toHaveClass( 'ppcp--is-open' );
		expect( warning() ).toHaveAttribute( 'aria-hidden', 'true' );
	} );

	it( 'reveals the credentials warning when the opt-in is enabled', () => {
		renderModal();

		fireEvent.click( toggle() );

		expect( warning() ).toHaveClass( 'ppcp--is-open' );
		expect( warning() ).toHaveAttribute( 'aria-hidden', 'false' );
	} );

	it( 'exports without credentials when confirmed untouched', () => {
		renderModal();

		fireEvent.click( exportButton() );

		expect( onExport ).toHaveBeenCalledWith( {
			includeConnection: false,
		} );
	} );

	it( 'exports with credentials once the opt-in is enabled', () => {
		renderModal();

		fireEvent.click( toggle() );
		fireEvent.click( exportButton() );

		expect( onExport ).toHaveBeenCalledWith( { includeConnection: true } );
	} );

	it( 'names the live environment in the enabled help text', () => {
		renderModal();

		fireEvent.click( toggle() );

		expect(
			screen.getByText( /connect as your live PayPal account/i )
		).toBeInTheDocument();
	} );

	it( 'names the sandbox environment in the enabled help text', () => {
		mockUseMerchant.mockReturnValue( {
			isConnected: true,
			isSandbox: true,
		} );

		renderModal();

		fireEvent.click( toggle() );

		expect(
			screen.getByText( /connect as your sandbox PayPal account/i )
		).toBeInTheDocument();
	} );

	it( 'warns identically for sandbox and live accounts', () => {
		// Must not soften for sandbox: a stale environment flag would then show
		// the reassuring copy to someone exporting live credentials.
		const warningFor = ( isSandbox ) => {
			mockUseMerchant.mockReturnValue( { isConnected: true, isSandbox } );
			const { unmount } = renderModal();
			fireEvent.click( toggle() );
			const text = screen.getByText( /client secret/i ).textContent;
			unmount();
			return text;
		};

		expect( warningFor( true ) ).toBe( warningFor( false ) );
	} );

	it( 'cancels without exporting', () => {
		renderModal();

		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( onCancel ).toHaveBeenCalled();
		expect( onExport ).not.toHaveBeenCalled();
	} );
} );
