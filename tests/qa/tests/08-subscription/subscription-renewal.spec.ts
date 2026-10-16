/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { disableWebhookVerificationPlugin, products } from '../../resources';
import { subscriptionRenewal } from './_test-data';
import {
	testDuplicateRenewalWebhook,
	testFreeTrialSubscriptionRenewal,
	testSubscriptionRenewal,
} from './_test-scenarios';

const {
	vaultingRenewal,
	vaultingFreeTrialRenewal,
	payPalRenewal,
	payPalFreeTrialRenewal,
	payPalDuplicateWebhook,
} = subscriptionRenewal;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
	await utils.configureStore( {
		enableClassicPages: false,
		enableSubscriptionsPlugin: true,
		products: [ products.subscription100, products.subscriptionFreeTrial ],
	} );
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

	// Keep webhook verification disabled: later specs (sign-up fee) also send simulated webhooks
	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( {
			savePaypalAndVenmo: true,
			saveCardDetails: true,
		} );
	} );

	for ( const testOrder of payPalRenewal ) {
		testSubscriptionRenewal( testOrder );
	}

	for ( const testOrder of payPalFreeTrialRenewal ) {
		testFreeTrialSubscriptionRenewal( testOrder );
	}

	// Skipped until the duplicate renewal order bug is fixed
	test.describe.skip( 'Duplicate renewal webhook', () => {
		for ( const testOrder of payPalDuplicateWebhook ) {
			testDuplicateRenewalWebhook( testOrder );
		}
	} );
} );
