/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { disableWebhookVerificationPlugin, products } from '../../resources';
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

test.beforeAll( async ( { utils, wooCommerceApi, pcpApi } ) => {
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
	await utils.configureStore( {
		enableClassicPages: false,
		enableSubscriptionsPlugin: true,
		products: [ products.subscription100, products.subscriptionFreeTrial ],
	} );
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
