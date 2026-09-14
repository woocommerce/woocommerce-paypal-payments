import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

jest.mock( '@paypal/react-paypal-js', () => ( {
	PayPalScriptProvider: ( { children } ) => children,
	PayPalCardFieldsProvider: jest.fn( ( { children } ) => children ),
	PayPalNameField: () => null,
	PayPalNumberField: () => null,
	PayPalExpiryField: () => null,
	PayPalCVVField: () => null,
	usePayPalCardFields: () => ( {
		cardFieldsForm: { submit: jest.fn().mockResolvedValue() },
	} ),
} ) );

jest.mock( '@ppcp-blocks/card-fields-config', () => ( {
	createOrder: jest.fn(),
	onApprove: jest.fn(),
	createVaultSetupToken: jest.fn(),
	onApproveSavePayment: jest.fn(),
} ) );

import { PayPalCardFieldsProvider } from '@paypal/react-paypal-js';
import { createOrder } from '@ppcp-blocks/card-fields-config';
import { CardFields } from './card-fields';

const baseConfig = ( {
	scriptData: scriptDataOverrides,
	...overrides
} = {} ) => ( {
	name_on_card: 'no',
	save_card_text: 'Save my card',
	is_vaulting_enabled: true,
	...overrides,
	scriptData: {
		client_id: 'client-id',
		is_free_trial_cart: false,
		locations_with_subscription_product: { cart: true },
		hosted_fields: { labels: { fields_not_valid: 'Invalid fields' } },
		...scriptDataOverrides,
	},
} );

const renderCardFields = ( configOverrides = {} ) => {
	const onPaymentSetup = jest.fn( () => () => {} );

	render(
		<CardFields
			config={ baseConfig( configOverrides ) }
			eventRegistration={ { onPaymentSetup } }
			emitResponse={ {
				responseTypes: { SUCCESS: 'success', ERROR: 'error' },
			} }
		/>
	);
};

describe( 'CardFields', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'a submit for a subscription cart always requests the card be vaulted', () => {
		renderCardFields( {
			scriptData: { locations_with_subscription_product: { cart: true } },
		} );

		const providerCreateOrder =
			PayPalCardFieldsProvider.mock.calls[ 0 ][ 0 ].createOrder;

		providerCreateOrder();
		providerCreateOrder();

		expect( createOrder ).toHaveBeenCalledTimes( 2 );
		expect( createOrder ).toHaveBeenNthCalledWith( 1, true );
		expect( createOrder ).toHaveBeenNthCalledWith( 2, true );
	} );

	test( 'a retried submit for a non-subscription cart keeps asking to vault once the buyer opted in', async () => {
		renderCardFields( {
			scriptData: {
				locations_with_subscription_product: { cart: false },
			},
		} );

		await userEvent.click( screen.getByLabelText( 'Save my card' ) );

		const providerCreateOrder =
			PayPalCardFieldsProvider.mock.calls[ 0 ][ 0 ].createOrder;

		providerCreateOrder();
		providerCreateOrder();

		expect( createOrder ).toHaveBeenNthCalledWith( 1, true );
		expect( createOrder ).toHaveBeenNthCalledWith( 2, true );
	} );
} );
