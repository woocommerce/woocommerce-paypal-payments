/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { debitOrCreditCard } from '../../../resources';

setup( 'Setup Debit or Credit Card', async ( { utils } ) => {
	await utils.pcpPaymentMethodIsEnabled( debitOrCreditCard.method );
} );
