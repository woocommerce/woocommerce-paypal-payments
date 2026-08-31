import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

import DisconnectButton from './DisconnectButton';

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

const mockDisconnectMerchant = jest.fn();
const mockGoToPluginSettings = jest.fn();

jest.mock( '@ppcp-settings/data', () => ( {
	CommonHooks: {
		useDisconnectMerchant: () => ( {
			disconnectMerchant: ( ...args ) =>
				mockDisconnectMerchant( ...args ),
		} ),
	},
} ) );

jest.mock( '@ppcp-settings/hooks/useNavigation', () => ( {
	useNavigation: () => ( {
		goToPluginSettings: () => mockGoToPluginSettings(),
	} ),
} ) );

jest.mock( '@ppcp-settings/Components/ReusableComponents/Stack', () => ( {
	HStack: ( { children, ...props } ) => <div { ...props }>{ children }</div>,
} ) );

const openDialog = () =>
	fireEvent.click( screen.getByRole( 'button', { name: 'Disconnect' } ) );

const dialog = () => screen.queryByRole( 'dialog' );

const startOverToggle = () =>
	screen.getByLabelText( 'Start over', { exact: false } );

const confirmButton = () =>
	screen.getAllByRole( 'button', { name: 'Disconnect' } ).pop();

describe( 'DisconnectButton', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockDisconnectMerchant.mockResolvedValue( undefined );
		window.location.hash = '';
	} );

	it( 'shows no dialog until the button is clicked', () => {
		render( <DisconnectButton /> );

		expect( dialog() ).not.toBeInTheDocument();
	} );

	it( 'opens the confirmation dialog on click', () => {
		render( <DisconnectButton /> );

		openDialog();

		expect( dialog() ).toBeInTheDocument();
		expect(
			screen.getByText( /restart the connection wizard/i )
		).toBeInTheDocument();
	} );

	it( 'leaves the start-over toggle off by default', () => {
		render( <DisconnectButton /> );

		openDialog();

		expect( startOverToggle() ).not.toBeChecked();
		expect(
			screen.getByText( /preserve all settings/i )
		).toBeInTheDocument();
	} );

	it( 'disconnects without resetting when confirmed untouched', async () => {
		render( <DisconnectButton /> );

		openDialog();
		fireEvent.click( confirmButton() );

		await waitFor( () =>
			expect( mockDisconnectMerchant ).toHaveBeenCalledWith( false )
		);
		expect( mockGoToPluginSettings ).toHaveBeenCalled();
	} );

	it( 'disconnects with a full reset once start-over is enabled', async () => {
		render( <DisconnectButton /> );

		openDialog();
		fireEvent.click( startOverToggle() );

		expect(
			screen.getByText( /reset to its initial state/i )
		).toBeInTheDocument();

		fireEvent.click( confirmButton() );

		await waitFor( () =>
			expect( mockDisconnectMerchant ).toHaveBeenCalledWith( true )
		);
	} );

	it( 'closes without disconnecting when cancelled', () => {
		render( <DisconnectButton /> );

		openDialog();
		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( dialog() ).not.toBeInTheDocument();
		expect( mockDisconnectMerchant ).not.toHaveBeenCalled();
	} );
} );
