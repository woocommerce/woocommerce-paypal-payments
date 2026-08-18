import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import InvoicePrefix from './InvoicePrefix';

const mockUseSettings = {
	invoicePrefix: '',
	setInvoicePrefix: jest.fn(),
};

jest.mock( '@ppcp-settings/data', () => ( {
	SettingsHooks: {
		useSettings: () => mockUseSettings,
	},
} ) );

jest.mock( '@ppcp-settings/Components/ReusableComponents/SettingsBlock', () => {
	return ( { title, className, children } ) => (
		<div data-testid="settings-block" className={ className }>
			<h3>{ title }</h3>
			{ children }
		</div>
	);
} );

jest.mock( '@ppcp-settings/Components/ReusableComponents/Controls', () => ( {
	ControlTextInput: ( { value, onChange, onBlur, placeholder, error } ) => (
		<div data-testid="control-text-input">
			<input
				placeholder={ placeholder }
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
				onBlur={ onBlur }
			/>
			{ error && <p className="ppcp-r-control-error">{ error }</p> }
		</div>
	),
} ) );

const ERROR_MESSAGE =
	'Only letters, numbers, hyphens and underscores are allowed.';

describe( 'InvoicePrefix', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockUseSettings.invoicePrefix = '';
	} );

	test.each( [
		[ 'a space', 'WC Store' ],
		[ 'an at sign', 'WC@Store' ],
		[ 'a dot', 'WC.Store' ],
		[ 'a slash', 'WC/Store' ],
	] )(
		'rejects a value containing %s: does not call setInvoicePrefix and shows an error',
		( _label, value ) => {
			render( <InvoicePrefix /> );

			const input = screen.getByPlaceholderText( 'Input prefix' );
			fireEvent.change( input, { target: { value } } );

			expect( mockUseSettings.setInvoicePrefix ).not.toHaveBeenCalled();
			expect( screen.getByText( ERROR_MESSAGE ) ).toBeInTheDocument();
		}
	);

	test( 'accepts letters, numbers, hyphens and underscores and shows no error', () => {
		render( <InvoicePrefix /> );

		const input = screen.getByPlaceholderText( 'Input prefix' );
		fireEvent.change( input, { target: { value: 'WC-Store_1' } } );

		expect( mockUseSettings.setInvoicePrefix ).toHaveBeenCalledWith(
			'WC-Store_1'
		);
		expect( screen.queryByText( ERROR_MESSAGE ) ).not.toBeInTheDocument();
	} );

	test( 'accepts an empty value', () => {
		mockUseSettings.invoicePrefix = 'WC-Store_1';
		render( <InvoicePrefix /> );

		const input = screen.getByPlaceholderText( 'Input prefix' );
		fireEvent.change( input, { target: { value: '' } } );

		expect( mockUseSettings.setInvoicePrefix ).toHaveBeenCalledWith( '' );
	} );

	test( 'keeps the message visible while typing continues after a rejected character', () => {
		render( <InvoicePrefix /> );

		const input = screen.getByPlaceholderText( 'Input prefix' );
		fireEvent.change( input, { target: { value: 'WC Store' } } );

		expect( screen.getByText( ERROR_MESSAGE ) ).toBeInTheDocument();

		fireEvent.change( input, { target: { value: 'WC-Store' } } );

		expect( screen.getByText( ERROR_MESSAGE ) ).toBeInTheDocument();
		expect( mockUseSettings.setInvoicePrefix ).toHaveBeenCalledWith(
			'WC-Store'
		);
	} );

	test( 'clears the error on blur', () => {
		render( <InvoicePrefix /> );

		const input = screen.getByPlaceholderText( 'Input prefix' );
		fireEvent.change( input, { target: { value: 'WC Store' } } );

		expect( screen.getByText( ERROR_MESSAGE ) ).toBeInTheDocument();

		fireEvent.blur( input );

		expect( screen.queryByText( ERROR_MESSAGE ) ).not.toBeInTheDocument();
	} );
} );
