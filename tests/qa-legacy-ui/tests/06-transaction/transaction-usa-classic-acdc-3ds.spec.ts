/**
 * Internal dependencies
 */
import {
	acdc,
	pcpConfigUsa,
	storeConfigUsa
} from '../../resources';
import { test } from '../../utils';
import {
	acdcClassicCheckout3ds
} from './_test-data/acdc';
import {
	transactionsOnClassicCheckout
} from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( {
			...pcpConfigUsa,
			advancedCardProcessing: {
				enableGateway: true,
				threeDSecure: 'Always trigger 3D Secure',
			}
	 	} );
		await utils.updatePcpPlugin();
	} );

	transactionsOnClassicCheckout( acdcClassicCheckout3ds );
} );
