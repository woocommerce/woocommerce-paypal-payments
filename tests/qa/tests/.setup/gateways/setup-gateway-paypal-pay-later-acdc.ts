/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { acdc, payLater, payPal } from '../../../resources';

setup( 'Setup PayPal, Pay Later, ACDC', async ( { utils } ) => {
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( payLater.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
} );
