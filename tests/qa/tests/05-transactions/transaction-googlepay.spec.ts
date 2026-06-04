/**
 * Internal dependencies
 */
import { test, annotateVisitor } from '../../utils';
import { payments, orders, customers } from '../../resources';

const customer = customers.usa;
const testOrder = { ...orders.default, payment: payments.googlePay, customer };

test(
	'PCP-2655 | Transaction - Checkout - Google Pay - Order by customer @Smoke',
	annotateVisitor( customer ),
	async ( { checkout, utils } ) => {
		await utils.fillVisitorsCart( testOrder.products );
		await checkout.visit();
		await checkout.completeCheckoutDetails( testOrder );

		const popupPromise = checkout.payPalUi.page.waitForEvent( 'popup', {
			timeout: 20_000,
		} );

		await checkout.payPalUi.clickGooglepayButton();

		const popup = await popupPromise;
		await popup
			.waitForURL( ( url ) => url.href !== 'about:blank', {
				timeout: 15_000,
			} )
			.catch( () => {} );

		await popup.close();
	}
);
