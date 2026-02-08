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
	};
	pcp?: {
		resetDb?: boolean;
		onboarding?: {
			merchant?: Pcp.Merchant;
			onboardingOptions?: Pcp.Api.OnboardingOptions;
		};
		settings?: Pcp.Api.Settings;
		paymentMethods?: Pcp.Api.PaymentMethods;
	};
};

const configureEnv = ( data: EnvConfig ) => {
	const { title, store, pcp } = data;

	if ( store ) {
		setup( `${ title } Setup store settings`, async ( { utils } ) => {
			await utils.configureStore( store );
		} );
	}

	if ( pcp ) {
		setup( `${ title } Install/activate PCP`, async ( { utils } ) => {
			await utils.installAndActivatePcp();
		} );

		if ( pcp?.resetDb ) {
			setup( `${ title } Reset PCP DB`, async ( { pcpApi } ) => {
				await pcpApi.resetDb();
			} );
		}

		if ( pcp?.onboarding ) {
			setup( `${ title } Onboarding`, async ( { pcpApi } ) => {
				const { merchant, onboardingOptions } = pcp.onboarding;
				await pcpApi.connectMerchant(
					merchant.client_id,
					merchant.client_secret,
					onboardingOptions
				);
			} );
		}

		if ( pcp?.settings ) {
			setup( `${ title } Update PCP settings`, async ( { pcpApi } ) => {
				await pcpApi.updatePcpSettings( pcp.settings );
			} );
		}

		if ( pcp?.paymentMethods ) {
			setup(
				`${ title } Update PCP payment methods`,
				async ( { pcpApi } ) => {
					await pcpApi.updatePcpPaymentMethods( pcp.paymentMethods );
				}
			);
		}
	}
};

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
		},
	},
} );

configureEnv( {
	title: 'setup:pcp:germany;',
	store: storeConfigGermany,
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.germany,
		},
	},
} );

configureEnv( {
	title: 'setup:pcp:mexico;',
	store: storeConfigMexico,
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.mexico,
		},
	},
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
			},
		},
		settings: {
			savePaypalAndVenmo: true,
			saveCardDetails: true,
		},
	},
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
			},
		},
		settings: {
			savePaypalAndVenmo: true,
			saveCardDetails: true,
		},
	},
} );

configureEnv( {
	title: 'setup:pcp:usa:vaulting:subscription;',
	store: {
		...storeConfigUsa,
		subscription: true,
		products: [ products.subscription100, products.subscriptionFreeTrial ],
	},
	pcp: {
		resetDb: true,
		onboarding: {
			merchant: merchants.usa,
			onboardingOptions: {
				isCasualSeller: false,
				areOptionalPaymentMethodsEnabled: true,
				products: [ 'physical', 'virtual', 'subscriptions' ],
			},
		},
	},
} );

setup( 'setup:pcp:usa:paypal:subscription;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		enableClassicPages: false,
		enableSubscriptionsPlugin: true,
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
} );
