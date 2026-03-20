/**
 * Internal dependencies
 */
import { expect, test as setup } from '../../../utils';
import {
	pcpConfigDefault,
	storeConfigClassic,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup Frontend UI', async ( { utils, payLater } ) => {
	setup.setTimeout( 5 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( storeConfigClassic );
	await utils.configurePcp( pcpConfigDefault );
	await payLater.visit();
	const enableGatewayCheckbox = payLater.enableGatewayCheckbox();
	await expect(
		enableGatewayCheckbox,
		'Assert enable Gateway checkbox is visible.'
	).toBeVisible();
	await enableGatewayCheckbox.check();
	await payLater.removeItemsFromSelectBox( 'Pay Later Button Locations', [
		'Cart',
		'Classic Cart',
		'Express Checkout',
		'Classic Checkout',
		'Single Product',
	] );
	await payLater.saveChanges();
	await utils.updatePcpPlugin();
} );
