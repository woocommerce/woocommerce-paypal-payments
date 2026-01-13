import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { __, sprintf } from '@wordpress/i18n';
import '@testing-library/jest-dom';
import SavePaymentMethods from './SavePaymentMethods';

const mockUseSettings = {
	savePaypalAndVenmo: false,
	setSavePaypalAndVenmo: jest.fn(),
	saveCardDetails: false,
	setSaveCardDetails: jest.fn(),
};

jest.mock( '@ppcp-settings/data', () => ( {
	SettingsHooks: {
		useSettings: () => mockUseSettings,
	},
} ) );

const mockUseMerchantInfo = {
	features: {
		save_paypal_and_venmo: {
			enabled: true,
		},
	},
};

jest.mock( '@ppcp-settings/data/common/hooks', () => ( {
	useMerchantInfo: () => mockUseMerchantInfo,
} ) );

jest.mock( '@ppcp-settings/Components/ReusableComponents/SettingsBlock', () => {
	return ( { title, description, className, children } ) => (
		<div data-testid="settings-block" className={ className }>
			<h3>{ title }</h3>
			<p>{ description }</p>
			{ children }
		</div>
	);
} );

jest.mock( '@ppcp-settings/Components/ReusableComponents/Controls', () => ( {
	ControlToggleButton: ( {
		id,
		label,
		description,
		value,
		onChange,
		disabled,
	} ) => (
		<div data-testid="control-toggle-button">
			<label htmlFor={ id }>{ label }</label>
			<div dangerouslySetInnerHTML={ { __html: description } } />
			<input
				id={ id }
				type="checkbox"
				checked={ value }
				onChange={ ( e ) => onChange( e.target.checked ) }
				disabled={ disabled }
			/>
		</div>
	),
} ) );

describe( 'SavePaymentMethods', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockUseSettings.savePaypalAndVenmo = false;
		mockUseSettings.saveCardDetails = false;
		mockUseMerchantInfo.features.save_paypal_and_venmo.enabled = true;
	} );

	describe( 'Rendering', () => {
		it( 'renders the component with correct title and description', () => {
			render( <SavePaymentMethods /> );

			expect(
				screen.getByText( 'Save payment methods' )
			).toBeInTheDocument();
			expect(
				screen.getByText( /Securely store customers' payment methods/ )
			).toBeInTheDocument();
			expect( screen.getByTestId( 'settings-block' ) ).toHaveClass(
				'ppcp--save-payment-methods'
			);
		} );

		it( 'renders PayPal and Venmo toggle button', () => {
			render( <SavePaymentMethods /> );

			expect(
				screen.getByText( 'Save PayPal and Venmo' )
			).toBeInTheDocument();
			expect(
				screen.getByText(
					/Securely store your customers' PayPal accounts/
				)
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'checkbox', {
					name: 'Save PayPal and Venmo',
				} )
			).toBeInTheDocument();
		} );

		it( 'renders credit card toggle button when not in ownBrandOnly mode', () => {
			render( <SavePaymentMethods ownBrandOnly={ false } /> );

			expect(
				screen.getByText( 'Save Credit and Debit Cards' )
			).toBeInTheDocument();
			expect(
				screen.getByText( /Securely store your customer's credit card/ )
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'checkbox', {
					name: 'Save Credit and Debit Cards',
				} )
			).toBeInTheDocument();
		} );

		it( 'Save Credit and Debit Cards is disabled  when in ownBrandOnly mode', () => {
			render( <SavePaymentMethods ownBrandOnly={ true } /> );

			expect(
				screen.queryByText( 'Save Credit and Debit Cards' )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'checkbox', {
					name: 'Save Credit and Debit Cards',
				} )
			).toBeDisabled();
		} );

		it( 'Does not render the component when ownBrandOnly is true and save_paypal_and_venmo feature is disabled', () => {
			mockUseMerchantInfo.features.save_paypal_and_venmo.enabled = false;

			const { container } = render(
				<SavePaymentMethods ownBrandOnly={ true } />
			);

			expect( container.firstChild ).toBeNull();
		} );

		it( 'renders when save_paypal_and_venmo feature is enabled and OwnBrand is true', () => {
			mockUseMerchantInfo.features.save_paypal_and_venmo.enabled = true;

			render( <SavePaymentMethods ownBrandOnly={ true } /> );

			expect(
				screen.getByTestId( 'settings-block' )
			).toBeInTheDocument();
		} );

        it( 'renders when save_paypal_and_venmo feature is enabled and OwnBrand is false', () => {
            mockUseMerchantInfo.features.save_paypal_and_venmo.enabled = true;

			render( <SavePaymentMethods ownBrandOnly={ true } /> );

			expect(
                screen.getByTestId( 'settings-block' )
			).toBeInTheDocument();
		} );
	} );

	describe( 'PayPal and Venmo toggle behavior', () => {
		it( 'displays correct value when feature is enabled', () => {
			mockUseSettings.savePaypalAndVenmo = true;
			mockUseMerchantInfo.features.save_paypal_and_venmo.enabled = true;

			render( <SavePaymentMethods /> );

			const checkbox = screen.getByRole( 'checkbox', {
				name: 'Save PayPal and Venmo',
			} );
			expect( checkbox ).toBeChecked();
		} );

		it( 'is enabled when feature is enabled', () => {
			mockUseMerchantInfo.features.save_paypal_and_venmo.enabled = true;

			render( <SavePaymentMethods /> );

			const checkbox = screen.getByRole( 'checkbox', {
				name: 'Save PayPal and Venmo',
			} );
			expect( checkbox ).not.toBeDisabled();
		} );

		it( 'calls setSavePaypalAndVenmo when toggled', () => {
			render( <SavePaymentMethods /> );

			const checkbox = screen.getByRole( 'checkbox', {
				name: 'Save PayPal and Venmo',
			} );
			fireEvent.click( checkbox );

			expect(
				mockUseSettings.setSavePaypalAndVenmo
			).toHaveBeenCalledWith( true );
		} );
	} );

	describe( 'Credit card toggle behavior', () => {
		beforeEach( () => {
			mockUseSettings.saveCardDetails = false;
		} );

		it( 'displays correct value', () => {
			mockUseSettings.saveCardDetails = true;

			render( <SavePaymentMethods ownBrandOnly={ false } /> );

			const checkbox = screen.getByRole( 'checkbox', {
				name: 'Save Credit and Debit Cards',
			} );
			expect( checkbox ).toBeChecked();
		} );

		it( 'calls setSaveCardDetails when toggled', () => {
			render( <SavePaymentMethods ownBrandOnly={ false } /> );

			const checkbox = screen.getByRole( 'checkbox', {
				name: 'Save Credit and Debit Cards',
			} );
			fireEvent.click( checkbox );

			expect( mockUseSettings.setSaveCardDetails ).toHaveBeenCalledWith(
				true
			);
		} );
	} );

	describe( 'Internationalization', () => {
		it( 'calls __ function for translatable strings', () => {
			render( <SavePaymentMethods /> );

			expect( __ ).toHaveBeenCalledWith(
				'Save payment methods',
				'woocommerce-paypal-payments'
			);
			expect( __ ).toHaveBeenCalledWith(
				'Save PayPal and Venmo',
				'woocommerce-paypal-payments'
			);
			expect( __ ).toHaveBeenCalledWith(
				'Save Credit and Debit Cards',
				'woocommerce-paypal-payments'
			);
		} );

		it( 'calls sprintf for formatted strings', () => {
			render( <SavePaymentMethods /> );

			expect( sprintf ).toHaveBeenCalledWith(
				expect.stringContaining( 'Pay Later' ),
				'https://woocommerce.com/document/woocommerce-paypal-payments/#pay-later'
			);
		} );
	} );

	describe( 'Integration with hooks', () => {
		it( 'uses values from useSettings hook', () => {
			mockUseSettings.savePaypalAndVenmo = true;
			mockUseSettings.saveCardDetails = true;

			render( <SavePaymentMethods ownBrandOnly={ false } /> );

			const paypalCheckbox = screen.getByRole( 'checkbox', {
				name: 'Save PayPal and Venmo',
			} );
			const cardCheckbox = screen.getByRole( 'checkbox', {
				name: 'Save Credit and Debit Cards',
			} );

			expect( paypalCheckbox ).toBeChecked();
			expect( cardCheckbox ).toBeChecked();
		} );
	} );
} );
