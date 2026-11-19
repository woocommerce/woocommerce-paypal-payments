jest.mock(
	'../../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading',
	() => ( {
		loadPayPalScript: jest.fn( () => Promise.resolve() ),
	} )
);
jest.mock(
	'../../../../ppcp-button/resources/js/modules/ButtonModuleWatcher',
	() => ( {
		__esModule: true,
		default: { registerContextBootstrap: jest.fn() },
	} )
);
jest.mock(
	'../../../../ppcp-button/resources/js/modules/Helper/Spinner',
	() => ( {
		__esModule: true,
		default: {
			fullPage: () => ( { block: jest.fn(), unblock: jest.fn() } ),
		},
	} )
);
jest.mock(
	'../../../../ppcp-button/resources/js/modules/Helper/Style',
	() => ( {
		normalizeStyleForFundingSource: () => ( {} ),
	} )
);

// The save endpoint call is out of scope here; the component's contract is the
// callback it hands over to run once the account has been saved.
const mockOnApproveSavePayment = jest.fn();
jest.mock( '../paypal-config', () => ( {
	completeApproval: jest.requireActual( '../paypal-config' ).completeApproval,
	createOrder: jest.fn(),
	createSubscription: jest.fn(),
	createVaultSetupToken: jest.fn(),
	handleApprove: jest.fn(),
	handleApproveSubscription: jest.fn(),
	onApproveSavePayment: ( ...args ) => mockOnApproveSavePayment( ...args ),
} ) );

import '@ppcp-test/helpers/silenceConsole';
import { act, render, waitFor } from '@testing-library/react';
import { PayPalComponent } from './paypal';

let buttonProps;

const baseConfig = ( overrides = {} ) => ( {
	id: 'ppcp-gateway',
	finalReviewEnabled: false,
	scriptData: {
		context: 'checkout',
		redirect: 'https://example.com/checkout/',
		button: { style: {} },
		appswitch: { enabled: false },
		server_side_shipping_callback: { enabled: false },
		locations_with_subscription_product: { cart: true },
		is_free_trial_cart: true,
		data_client_id: {},
	},
	...overrides,
} );

function renderFreeTrialButton( {
	config = baseConfig(),
	onSubmit,
	onError = jest.fn(),
	onClose = jest.fn(),
} = {} ) {
	const unsubscribe = () => () => {};
	render(
		<PayPalComponent
			config={ config }
			onClick={ jest.fn() }
			onClose={ onClose }
			onSubmit={ onSubmit }
			onError={ onError }
			eventRegistration={ {
				onPaymentSetup: unsubscribe,
				onCheckoutFail: unsubscribe,
				onCheckoutValidation: unsubscribe,
			} }
			emitResponse={ { responseTypes: { SUCCESS: 'success' } } }
			activePaymentMethod="ppcp-gateway"
			shippingData={ { needsShipping: false } }
			isEditing={ false }
			fundingSource="paypal"
		/>
	);
	return waitFor( () => expect( buttonProps ).toBeDefined() );
}

async function approveSavedAccount() {
	mockOnApproveSavePayment.mockImplementation(
		async ( _token, _config, onComplete ) => onComplete()
	);

	await act( async () => {
		await buttonProps.onApprove( { vaultSetupToken: 'VAULT1' } );
	} );
}

describe( 'PayPalComponent', () => {
	let originalReact;
	let originalReactDOM;
	let originalButtons;

	beforeEach( () => {
		jest.clearAllMocks();
		buttonProps = undefined;
		originalReact = global.React;
		originalReactDOM = global.ReactDOM;
		originalButtons = global.ppcpBlocksPaypalExpressButtons;

		global.React = require( 'react' );
		global.ReactDOM = {};
		global.ppcpBlocksPaypalExpressButtons = {
			Buttons: {
				driver: () => ( props ) => {
					buttonProps = props;
					return null;
				},
			},
		};
		delete window.ppcpFundingSource;
	} );

	afterEach( () => {
		global.React = originalReact;
		global.ReactDOM = originalReactDOM;
		global.ppcpBlocksPaypalExpressButtons = originalButtons;
	} );

	describe( 'free-trial subscription cart', () => {
		test.each( [
			[ 'submits', false, 1 ],
			[ 'does not submit', true, 0 ],
		] )(
			'%s the checkout once the account is saved when finalReviewEnabled is %s',
			async ( _label, finalReviewEnabled, expectedSubmits ) => {
				const onSubmit = jest.fn();
				await renderFreeTrialButton( {
					config: baseConfig( { finalReviewEnabled } ),
					onSubmit,
				} );

				await approveSavedAccount();

				expect( onSubmit ).toHaveBeenCalledTimes( expectedSubmits );
			}
		);
	} );
} );
