/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { oxxo } from '../../../resources';

setup( 'Setup OXXO', async ( { utils } ) => {
	await utils.pcpPaymentMethodIsEnabled( oxxo.method );
} );
