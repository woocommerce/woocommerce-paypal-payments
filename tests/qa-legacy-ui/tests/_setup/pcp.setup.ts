/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	acdc,
	debitOrCreditCard,
	payLater,
	payPal,
	pcpConfigGermany,
	pcpConfigMexico,
	pcpConfigUsa,
	standardCardButton,
	storeConfigGermany,
	storeConfigMexico,
	storeConfigUsa,
} from '../../resources';

setup.describe( () => {
	setup.beforeAll( async ( { utils } ) => {
		await utils.resetEnvironment();
		await utils.createStorageStates();
	} );

	setup( 'setup:env:reset;', async ( { utils } ) => {
		setup.setTimeout( 4 * 60_000 );
		await utils.setupStore();
	} );

	setup( 'setup:pcp:usa;', async ( { utils } ) => {
		setup.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigUsa );
		await utils.configurePcp( pcpConfigUsa );
	} );
	
	setup( 'setup:pcp:usa:classic:vertical:paypal-paylater-acdc;', async ( { utils, standardPayments } ) => {
		setup.setTimeout( 6 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigUsa );
			await utils.pcpPaymentMethodIsEnabled( payPal.method );
			await utils.pcpPaymentMethodIsEnabled( payLater.method );
			await utils.pcpPaymentMethodIsEnabled( acdc.method );
			await standardPayments.setup( {
				classicCartButtonLayout: 'Vertical',
				classicCheckoutButtonLayout: 'Vertical',
				singleProductButtonLayout: 'Vertical',
			} );
	} );
	
	setup( 'setup:pcp:usa:classic:horizontal:paypal-paylater-acdc;', async ( { utils, standardPayments } ) => {
		setup.setTimeout( 6 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigUsa );
			await utils.pcpPaymentMethodIsEnabled( payPal.method );
			await utils.pcpPaymentMethodIsEnabled( payLater.method );
			await utils.pcpPaymentMethodIsEnabled( acdc.method );
			await standardPayments.setup( {
				disableAlternativePaymentMethods: [ 'Venmo' ],
				classicCartButtonLayout: 'Horizontal',
				classicCheckoutButtonLayout: 'Horizontal',
				singleProductButtonLayout: 'Horizontal',
			} );
	} );
	
	setup( 'setup:pcp:usa:classic:bcdc-paypal;', async ( { utils, standardPayments } ) => {
		setup.setTimeout( 6 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigUsa );
		await utils.pcpPaymentMethodIsEnabled( debitOrCreditCard.method );
		await standardPayments.setup( {
			disableAlternativePaymentMethods: [ 'Venmo' ],
		} );
	} );
	
	setup( 'setup:pcp:usa:classic:bcdc;', async ( { utils, standardPayments } ) => {
		setup.setTimeout( 6 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigUsa );
		await utils.pcpPaymentMethodIsEnabled( standardCardButton.method );
		await standardPayments.setup( {
			disableAlternativePaymentMethods: [ 'Venmo' ],
		} );
	} );
	
	setup( 'setup:pcp:usa:classic:acdc;', async ( { utils, standardPayments } ) => {
		setup.setTimeout( 6 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigUsa );
		await utils.pcpPaymentMethodIsEnabled( acdc.method );
		await standardPayments.setup( {
			disableAlternativePaymentMethods: [ 'Venmo' ],
		} );
	} );

	setup( 'setup:pcp:germany;', async ( { utils } ) => {
		setup.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigGermany );
		await utils.configurePcp( pcpConfigGermany );
	} );

	setup( 'setup:pcp:mexico;', async ( { utils } ) => {
		setup.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigMexico );
		await utils.configurePcp( pcpConfigMexico );
	} );
} );

setup( 'setup:pcp:update;', async ( { utils } ) => {
	await utils.updatePcpPlugin();
} );

setup( 'setup:classic:pages;', async ( { utils } ) => {
	await utils.configureStore( {
		classicPages: true,
	} );
} );

setup( 'setup:block:pages;', async ( { utils } ) => {
	await utils.configureStore( {
		classicPages: false,
	} );
} );
