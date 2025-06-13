/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	storeConfigUsa,
	merchants,
	storeConfigGermany,
	storeConfigMexico,
	taxSettings,
	gateways,
	Pcp,
	products,
} from '../../resources';

const { payPal, venmo, acdc, fastlane } = gateways;

type EnvConfig = {
	title: string;
	store?: {
		classicPages?: boolean; // false = block cart and checkout (default), true = classic cart & checkout pages
		wpDebugging?: boolean; // WP Debugging plugin is deactivated
		subscription?: boolean; // WC Subscription plugin is deactivated
		settings?: WooCommerce.Settings; // WC settings
		taxes?: {
			options: WooCommerce.Settings; // Tax settings in WC > Settings > General tab
			rates: WooCommerce.CreateTax[]; // Tax rates to be active in WC > Settings > Taxes > Tax rates tab
		};
		customer?: WooCommerce.CreateCustomer; // Add registered customer
		products?: WooCommerce.CreateProducts[]; // Products to be created if not existing
	},
	pcp?: {
		resetDb?: boolean;
		onboarding?: {
			merchant?: Pcp.Merchant;
			onboardingOptions?: Pcp.Api.OnboardingOptions
		};
		settings?: Pcp.Api.Settings;
		paymentMethods?: Pcp.Api.PaymentMethods;
	}
};

const configureEnv = ( data: EnvConfig ) => {
	const { title, store, pcp } = data;

	setup( title, async ( { utils, pcpApi } ) => {
		await setup.step('Install/activate PCP', async () => {
			await utils.installAndActivatePcp();
		} );

		if( store ) {
			await setup.step('Setup store settings', async () => {
				await utils.configureStore( store );
			} );
		}

		if( pcp?.resetDb ) {
			await setup.step('Reset PCP DB', async () => {
				await pcpApi.resetDb();
			} );
		}

		if( pcp?.onboarding ) {
			const { merchant, onboardingOptions } = pcp.onboarding;
			await setup.step('Onboarding', async () => {
				await pcpApi.connectMerchant(
					merchant.client_id,
					merchant.client_secret,
					onboardingOptions,
				);
			} );
		}

		if( pcp?.settings ) {
			await setup.step('Update PCP settings', async () => {
				await pcpApi.updatePcpSettings( pcp.settings );
			} );
		}
		
		if( pcp?.paymentMethods ) {
			await setup.step('Update PCP payment methods', async () => {
				await pcpApi.updatePcpPaymentMethods( pcp.paymentMethods );
			} );
		}
	} );
}

configureEnv( {
	title: 'setup:checkout:block;',
	store: { classicPages: false },
} );

configureEnv( {
	title: 'setup:checkout:classic;',
	store: { classicPages: true },
} );

configureEnv( {
	title: 'setup:tax:inc;',
	store: { taxes: taxSettings.including },
} );

configureEnv( {
	title: 'setup:tax:exc;',
	store: { taxes: taxSettings.excluding },
} );

configureEnv( {
	title: 'setup:pcp:usa;',
	store: storeConfigUsa,
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.usa,
		},
		paymentMethods: {
			'ppcp-credit-card-gateway': {
				id: 'ppcp-credit-card-gateway',
				enabled: true,
			},
		}
	}
} );

configureEnv( {
	title: 'setup:pcp:germany;',
	store: storeConfigGermany,
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.germany,
		},
	}
} );

configureEnv( {
	title: 'setup:pcp:mexico;',
	store: storeConfigMexico,
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.mexico,
		},
	}
} );

configureEnv( {
	title: 'setup:pcp:usa:vaulting;',
	store: storeConfigUsa,
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.usa,
			onboardingOptions: {
				isCasualSeller: false,
				areOptionalPaymentMethodsEnabled: true,
			}
		},
		settings: {
			savePaypalAndVenmo: true,
			saveCardDetails: true,
		},
	}
} );

configureEnv( {
	title: 'setup:pcp:usa:vaulting:classic;',
	store: {
		...storeConfigUsa,
		classicPages: true,
	},
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.usa,
			onboardingOptions: {
				isCasualSeller: false,
				areOptionalPaymentMethodsEnabled: true,
			}
		},
		settings: {
			savePaypalAndVenmo: true,
			saveCardDetails: true,
		},
	}
} );

configureEnv( {
	title: 'setup:pcp:usa:subscription;',
	store: {
		...storeConfigUsa,
		subscription: true,
		products: [
			products.subscription10,
			products.subscriptionFreeTrial,
			products.subscriptionPayPal,
			products.subscriptionPayPalFreeTrial,
		],
	},
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.usa,
			onboardingOptions: {
				isCasualSeller: false,
				areOptionalPaymentMethodsEnabled: true,
				products: [ 'physical', 'virtual', 'subscriptions' ],
			}
		},
	}
} );
