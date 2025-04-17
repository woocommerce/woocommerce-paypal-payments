/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigDefault, products } from '../../resources';
import { paymentMethodsData } from './_test-data';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
} );

for ( const testData of paymentMethodsData.defaultUi ) {
	const { testKey, country, gateways } = testData;

	test( `${ testKey } | Settings - ${ country } - Styling - Default UI`, async ( {
		utils,
		pcpPaymentMethods,
		product,
		cart,
		classicCart,
		checkout,
		classicCheckout,
	}, testInfo ) => {
		const snapshotName = testInfo.title;

		await pcpPaymentMethods.visit();
		await pcpPaymentMethods.snapshotContent(
			`${ snapshotName } - Content`
		);
		await expect
			.soft( pcpPaymentMethods.paymentMethodContainers() )
			.toHaveCount( gateways.length );

		for ( const gatewayName of gateways ) {

		}
	} );
}
