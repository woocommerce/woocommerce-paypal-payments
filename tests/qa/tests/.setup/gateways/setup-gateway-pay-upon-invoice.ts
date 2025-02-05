/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { payUponInvoice } from '../../../resources';

setup( 'Setup Pay upon Invoice', async ( { utils } ) => {
	await utils.pcpPaymentMethodIsEnabled( payUponInvoice.method );
} );
