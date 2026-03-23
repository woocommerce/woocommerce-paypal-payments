/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	orders,
	acdc,
	merchants,
} from '../../resources';

test( 'PCP-2064 | Frontend UI - Pay by link - ACDC - Unchecked Terms and conditions @Critical', async ( {
	wooCommerceUtils,
	wooCommerceApi,
	classicPayForOrder,
} ) => {
	const testOrder = {
		...orders.default,
		payment: acdc,
	};
	let order = await wooCommerceUtils.createApiOrder( testOrder );

	await classicPayForOrder.visit( order.id, order.order_key );
	await classicPayForOrder.ppui.completeAcdcPayment(
		acdc,
		merchants.usa
	)

	await classicPayForOrder.assertUrl( order.id, order.order_key );
	await expect(
		classicPayForOrder.page.getByText(
			'Please read and accept the terms and conditions to proceed with your order.'
		)
	).toBeVisible();

	order = await wooCommerceApi.getOrder( order.id );
	await expect( order.status ).toEqual( 'pending' );
	await expect( order.transaction_id ).toHaveLength( 0 );
} );
