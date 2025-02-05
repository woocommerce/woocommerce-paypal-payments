/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { venmo } from '../../../resources';

setup( 'Setup Venmo', async ( { utils } ) => {
	await utils.pcpPaymentMethodIsEnabled( venmo.method );
} );
