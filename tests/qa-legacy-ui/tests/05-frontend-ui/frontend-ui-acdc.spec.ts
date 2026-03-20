/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	orders,
	acdc,
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
	await classicPayForOrder.ppui.acdcGateway().click();
	await classicPayForOrder.ppui
		.cardNumberInput()
		.fill( testOrder.payment.card.card_number );
	await classicPayForOrder.ppui.cardExpirationInput().click();
	await classicPayForOrder.ppui.page.keyboard.type(
		testOrder.payment.card.expiration_date
	);
	await classicPayForOrder.ppui
		.cardCVVInput()
		.fill( testOrder.payment.card.card_cvv );
	await classicPayForOrder.payForOrderButton().click();

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
