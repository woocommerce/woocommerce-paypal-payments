/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { customers, gateways, negative12FeePlugin, taxSettings } from '../../resources';
import {
	transactionsOnCart,
	transactionsOnMiniCart,
	transactionsOnCheckout,
	transactionsOnPayByLink,
	transactionsOnProduct,
} from './_test-scenarios';
import {
	payPalCart,
	payPalCartPayNowOff,
	payPalCheckout,
	payPalCheckoutExcludingTax,
	payPalCheckoutIntentAuthorized,
	payPalCheckoutNegativeFee,
	payPalCheckoutPayNowOff,
	payPalCheckoutVariable,
	payPalCheckoutEmptyForm,
	payPalMiniCart,
	payPalMiniCartPayNowOff,
	payPalPayByLink,
	payPalPayByLinkExcludingTax,
	payPalPayByLinkIntentAuthorized,
	payPalProduct,
	payPalProductPayNowOff,
} from './_test-data/paypal';
import {
	payLaterCart,
	payLaterCartPayNowOff,
	payLaterCheckout,
	payLaterCheckoutExcludingTax,
	payLaterCheckoutIntentAuthorized,
	payLaterProduct,
	payLaterProductPayNowOff,
	payLaterPayByLink,
	payLaterCheckoutNegativeFee,
	payLaterCheckoutPayNowOff,
	payLaterCheckoutEmptyForm,
	payLaterMiniCart,
} from './_test-data/pay-later';
import {
	acdcCheckout,
	acdcCheckoutExcludingTax,
	acdcCheckoutIntentAuthorized,
	acdcCheckout3ds,
	acdcPayByLink,
	acdcPayByLink3ds,
	acdcPayByLinkExcludingTax,
	acdcPayByLinkIntentAuthorized,
	acdcCheckoutNegativeFee,
} from './_test-data/acdc';
import { fastlaneCheckout } from './_test-data/fastlane';

const { fastlane, payPal, payLater } = gateways;

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		enableClassicPages: false,
		customer: customers.usa,
	} );
} );

for ( const testOrder of payPalCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of payLaterCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of acdcCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of payPalPayByLink ) {
	transactionsOnPayByLink( testOrder );
}

for ( const testOrder of acdcPayByLink ) {
	transactionsOnPayByLink( testOrder );
}

for ( const testOrder of payLaterPayByLink ) {
	transactionsOnPayByLink( testOrder );
}

for ( const testOrder of payPalProduct ) {
	transactionsOnProduct( testOrder );
}

for ( const testOrder of payLaterProduct ) {
	transactionsOnProduct( testOrder );
}

for ( const testOrder of payPalCheckoutVariable ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of [
	...payPalCheckoutEmptyForm,
	...payLaterCheckoutEmptyForm,
] ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of payPalCart ) {
	transactionsOnCart( testOrder );
}

for ( const testOrder of payLaterCart ) {
	transactionsOnCart( testOrder );
}

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	for ( const testOrder of payPalCheckoutExcludingTax ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payLaterCheckoutExcludingTax ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of acdcCheckoutExcludingTax ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payPalPayByLinkExcludingTax ) {
		transactionsOnPayByLink( testOrder );
	}

	for ( const testOrder of acdcPayByLinkExcludingTax ) {
		transactionsOnPayByLink( testOrder );
	}

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

// Intent Authorized
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: true } );
	} );

	for ( const testOrder of payPalCheckoutIntentAuthorized ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payLaterCheckoutIntentAuthorized ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of acdcCheckoutIntentAuthorized ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payPalPayByLinkIntentAuthorized ) {
		transactionsOnPayByLink( testOrder );
	}

	for ( const testOrder of acdcPayByLinkIntentAuthorized ) {
		transactionsOnPayByLink( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: false } );
	} );
} );

// ACDC 3DS
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			threeDSecure: 'always-3d-secure',
		} );
	} );

	for ( const testOrder of acdcCheckout3ds ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of acdcPayByLink3ds ) {
		transactionsOnPayByLink( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			threeDSecure: 'no-3d-secure',
		} );
	} );
} );

/**
 * Fastlane (only for USA)
 */

test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			[ fastlane.id ]: { id: fastlane.id, enabled: true },
		} );
	} );

	for ( const testOrder of fastlaneCheckout ) {
		transactionsOnCheckout( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			[ fastlane.id ]: { id: fastlane.id, enabled: false },
		} );
	} );
} );

/**
 * Negative fee snippet
 */

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( negative12FeePlugin.slug );
	} );

	for ( const testOrder of payPalCheckoutNegativeFee ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payLaterCheckoutNegativeFee ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of acdcCheckoutNegativeFee ) {
		transactionsOnCheckout( testOrder );
	}

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( negative12FeePlugin.slug );
	} );
} );

/**
 * Pay Now off: the buyer reviews the order on checkout after PayPal approval
 */

test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { enablePayNow: false } );
	} );

	for ( const testOrder of [
		...payPalProductPayNowOff,
		...payLaterProductPayNowOff,
	] ) {
		transactionsOnProduct( testOrder );
	}

	for ( const testOrder of [ ...payPalCartPayNowOff, ...payLaterCartPayNowOff ] ) {
		transactionsOnCart( testOrder );
	}

	for ( const testOrder of [
		...payPalCheckoutPayNowOff,
		...payLaterCheckoutPayNowOff,
	] ) {
		transactionsOnCheckout( testOrder );
	}

	// Mini cart (off by default)
	test.describe( () => {
		test.beforeAll( async ( { pcpApi } ) => {
			await pcpApi.updatePcpStyling( {
				miniCart: { enabled: true, methods: [ payPal.id, payLater.id ] },
			} );
		} );

		for ( const testOrder of payPalMiniCartPayNowOff ) {
			transactionsOnMiniCart( testOrder );
		}

		test.afterAll( async ( { pcpApi } ) => {
			await pcpApi.updatePcpStyling( { miniCart: { enabled: false } } );
		} );
	} );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { enablePayNow: true } );
	} );
} );

/**
 * Mini cart (off by default)
 */

test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpStyling( {
			miniCart: { enabled: true, methods: [ payPal.id, payLater.id ] },
		} );
	} );

	for ( const testOrder of [ ...payPalMiniCart, ...payLaterMiniCart ] ) {
		transactionsOnMiniCart( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpStyling( { miniCart: { enabled: false } } );
	} );
} );
