/**
 * External dependencies
 */
import fs from 'fs';
import {
	APIRequestContext,
	Page,
	VideoMode,
	ViewportSize,
} from '@playwright/test';
import {
	test as base,
	expect,
	WooCommerceApi,
	BaseExtend as BaseExtendBase,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalApi, PcpApi, Utils } from '.';

// PCP tabs
import {
	PcpOnboarding,
	PcpOverview,
	PcpPaymentMethods,
	PcpSettings,
	PcpStyling,
	PcpPayLaterMessaging,
	WooCommerceOrderEdit,
	WooCommerceSubscriptionEdit,
} from './admin';

// WooCommerce front end
import {
	PayPalUi,
	PayPalUiClassic,
	Shop,
	Product,
	Cart,
	Checkout,
	ClassicCart,
	ClassicCheckout,
	PayForOrder,
	OrderReceived,
	CustomerAccount,
	CustomerPaymentMethods,
	CustomerSubscriptions,
	ClassicPayForOrder,
} from './frontend';

export type BaseExtend = BaseExtendBase & {
	recordVideoOptions?: {
		mode: VideoMode;
		size?: ViewportSize;
	};
	payPalApi: PayPalApi;
	pcpApi: PcpApi;
	visitorPage: Page;
	visitorRequest: APIRequestContext;
	visitorWooCommerceApi: WooCommerceApi;
	payPalUi: PayPalUi;
	payPalUiClassic: PayPalUiClassic;

	// PCP tabs
	pcpOnboarding: PcpOnboarding;
	pcpOverview: PcpOverview;
	pcpPaymentMethods: PcpPaymentMethods;
	pcpSettings: PcpSettings;
	pcpStyling: PcpStyling;
	pcpPayLaterMessaging: PcpPayLaterMessaging;

	// WooCommerce dashboard
	wooCommerceOrderEdit: WooCommerceOrderEdit;
	wooCommerceSubscriptionEdit: WooCommerceSubscriptionEdit;

	// WooCommerce Guest front end
	shop: Shop;
	product: Product;
	cart: Cart;
	checkout: Checkout;
	classicCart: ClassicCart;
	classicCheckout: ClassicCheckout;
	classicPayForOrder: ClassicPayForOrder;
	payForOrder: PayForOrder;
	orderReceived: OrderReceived;
	customerAccount: CustomerAccount;
	customerPaymentMethods: CustomerPaymentMethods;
	customerSubscriptions: CustomerSubscriptions;

	// Utils & preconditions
	utils: Utils;
};

const test = base.extend< BaseExtend >( {
	recordVideoOptions: [ null, { option: true } ],
	payPalApi: async ( { request }, use ) => {
		await use( new PayPalApi( { request } ) );
	},
	pcpApi: async ( { request, requestUtils }, use ) => {
		await use( new PcpApi( { request, requestUtils } ) );
	},
	visitorPage: async ( { browser, recordVideoOptions }, use, testInfo ) => {
		// check if visitor is specified in test otherwise use guest
		const storageStateName =
			testInfo.annotations?.find( ( el ) => el.type === 'visitor' )
				?.description || 'guest';
		const storageStatePath = `${ process.env.STORAGE_STATE_PATH }/${ storageStateName }.json`;
		// apply current visitor's storage state to the context
		const context = await browser.newContext( {
			...testInfo.project.use, // Spread project's use config
			storageState: fs.existsSync( storageStatePath )
				? storageStatePath
				: undefined,
			...( recordVideoOptions && {
				recordVideo: {
					...recordVideoOptions,
					dir: testInfo.outputDir, // Override recordVideo to use correct output dir
				},
			} ),
		} );
		const page = await context.newPage();
		await use( page );

		// Save video path BEFORE closing
		const video = page.video();
		await page.close();
		await context.close();

		// Attach video to report after context is closed
		if ( video ) {
			const videoPath = await video.path();
			await testInfo.attach( 'video', {
				path: videoPath,
				contentType: 'video/webm',
			} );
		}
	},
	visitorRequest: async ( { visitorPage }, use ) => {
		const request = visitorPage.request;
		await use( request );
	},
	visitorWooCommerceApi: async ( { visitorRequest }, use ) => {
		await use( new WooCommerceApi( { request: visitorRequest } ) );
	},
	payPalUi: async ( { visitorPage, payPalApi }, use ) => {
		await use( new PayPalUi( { page: visitorPage, payPalApi } ) );
	},
	payPalUiClassic: async ( { visitorPage, payPalApi }, use ) => {
		await use( new PayPalUiClassic( { page: visitorPage, payPalApi } ) );
	},

	// PCP settings
	pcpOnboarding: async ( { page }, use ) => {
		await use( new PcpOnboarding( { page } ) );
	},
	pcpOverview: async ( { page }, use ) => {
		await use( new PcpOverview( { page } ) );
	},
	pcpPaymentMethods: async ( { page }, use ) => {
		await use( new PcpPaymentMethods( { page } ) );
	},
	pcpSettings: async ( { page }, use ) => {
		await use( new PcpSettings( { page } ) );
	},
	pcpStyling: async ( { page }, use ) => {
		const pcpStyling = new PcpStyling( { page } );
		await use( pcpStyling );
	},
	pcpPayLaterMessaging: async ( { page }, use ) => {
		const pcpPayLaterMessaging = new PcpPayLaterMessaging( { page } );
		await use( pcpPayLaterMessaging );
	},

	// WooCommerce dashboard
	wooCommerceOrderEdit: async ( { page }, use ) => {
		await use( new WooCommerceOrderEdit( { page } ) );
	},
	wooCommerceSubscriptionEdit: async ( { page, requestUtils }, use ) => {
		await use(
			new WooCommerceSubscriptionEdit( { page, requestUtils } )
		);
	},

	// WooCommerce front end
	shop: async ( { visitorPage, payPalUiClassic }, use ) => {
		await use(
			new Shop( { page: visitorPage, payPalUi: payPalUiClassic } )
		);
	},
	product: async ( { visitorPage, payPalUiClassic }, use ) => {
		await use(
			new Product( { page: visitorPage, payPalUi: payPalUiClassic } )
		);
	},
	cart: async ( { visitorPage, payPalUi }, use ) => {
		await use( new Cart( { page: visitorPage, payPalUi } ) );
	},
	checkout: async ( { visitorPage, payPalUi }, use ) => {
		await use( new Checkout( { page: visitorPage, payPalUi } ) );
	},
	classicCart: async ( { visitorPage, payPalUiClassic }, use ) => {
		await use(
			new ClassicCart( { page: visitorPage, payPalUi: payPalUiClassic } )
		);
	},
	classicCheckout: async ( { visitorPage, payPalUiClassic }, use ) => {
		await use(
			new ClassicCheckout( {
				page: visitorPage,
				payPalUi: payPalUiClassic,
			} )
		);
	},
	classicPayForOrder: async ( { visitorPage, payPalUiClassic }, use ) => {
		await use(
			new ClassicPayForOrder( {
				page: visitorPage,
				payPalUi: payPalUiClassic,
			} )
		);
	},
	payForOrder: async ( { visitorPage, payPalUiClassic }, use ) => {
		await use(
			new PayForOrder( { page: visitorPage, payPalUi: payPalUiClassic } )
		);
	},
	orderReceived: async ( { visitorPage }, use ) => {
		await use( new OrderReceived( { page: visitorPage } ) );
	},
	customerAccount: async ( { visitorPage }, use ) => {
		await use( new CustomerAccount( { page: visitorPage } ) );
	},
	customerPaymentMethods: async ( { visitorPage, payPalUiClassic }, use ) => {
		await use(
			new CustomerPaymentMethods( {
				page: visitorPage,
				payPalUi: payPalUiClassic,
			} )
		);
	},
	customerSubscriptions: async ( { visitorPage }, use ) => {
		await use( new CustomerSubscriptions( { page: visitorPage } ) );
	},

	// Utils & preconditions
	utils: async (
		{
			requestUtils,
			wooCommerceApi,
			visitorWooCommerceApi,
			wooCommerceUtils,
			plugins,
			payForOrder,
			checkout,
			classicCheckout,
			orderReceived,
			cli,
		},
		use
	) => {
		await use(
			new Utils( {
				requestUtils,
				wooCommerceApi,
				visitorWooCommerceApi,
				wooCommerceUtils,
				plugins,
				payForOrder,
				checkout,
				classicCheckout,
				orderReceived,
				cli,
			} )
		);
	},
} );

export { test, expect };
