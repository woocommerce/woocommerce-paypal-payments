/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	pcpConfigUsa,
	storeConfigSubscriptionUsa,
	products,
} from '../../resources';

test.describe( 'Subscription > Frontend UI', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( storeConfigSubscriptionUsa );
		await utils.configurePcp( pcpConfigUsa );
	} );

	test( 'PCP-1493 | Subscription - Frontend UI - Classic cart - Guest can not see PayPal buttons on subscription product', async ( {
		cart,
		utils,
	} ) => {
		await utils.fillVisitorsCart( [ products.subscription10 ] );
		await cart.visit();
		await cart.ppui.assertPayLaterButtonVisibility( false );
	} );
} );
