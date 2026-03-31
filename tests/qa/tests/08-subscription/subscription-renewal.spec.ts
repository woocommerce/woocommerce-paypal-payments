/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	disableWebhookVerificationPlugin,
	merchants,
	products,
	storeConfigUsa,
} from '../../resources';
import { subscriptionRenewal } from './_test-data';
import {
	testFreeTrialSubscriptionRenewal,
	testSubscriptionRenewal,
} from './_test-scenarios';

const {
	vaultingRenewal,
	vaultingFreeTrialRenewal,
	payPalRenewal,
	payPalFreeTrialRenewal,
} = subscriptionRenewal;

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		enableWpDebugging: false,
		enableClassicPages: false,
		enableSubscriptionsPlugin: true,
		products: [ products.subscription100, products.subscriptionFreeTrial ],
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret,
		{
			isCasualSeller: false,
			areOptionalPaymentMethodsEnabled: true,
			products: [ 'physical', 'virtual', 'subscriptions' ],
		}
	);
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of vaultingRenewal ) {
	testSubscriptionRenewal( testOrder );
}

for ( const testOrder of vaultingFreeTrialRenewal ) {
	testFreeTrialSubscriptionRenewal( testOrder );
}

test.describe( 'PayPal Subscription', () => {
	test.beforeAll( async ( { utils, pcpApi, requestUtils } ) => {
		await pcpApi.updatePcpSettings( {
			savePaypalAndVenmo: false,
			saveCardDetails: false,
		} );
		await utils.configureStore( {
			products: [
				products.subscriptionPayPal,
				products.subscriptionPayPalFreeTrial,
			],
		} );
		if ( ! process.env.CI ) {
			await requestUtils.activatePlugin(
				disableWebhookVerificationPlugin.slug
			);
		}
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( ! process.env.CI ) {
			await requestUtils.deactivatePlugin(
				disableWebhookVerificationPlugin.slug
			);
		}
	} );

	for ( const testOrder of payPalRenewal ) {
		testSubscriptionRenewal( testOrder );
	}

	for ( const testOrder of payPalFreeTrialRenewal ) {
		testFreeTrialSubscriptionRenewal( testOrder );
	}
} );
