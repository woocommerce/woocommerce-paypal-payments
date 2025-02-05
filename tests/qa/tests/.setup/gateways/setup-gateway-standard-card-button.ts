/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { standardCardButton } from '../../../resources';

setup( 'Setup Standard Card Button', async ( { utils } ) => {
	await utils.pcpPaymentMethodIsEnabled( standardCardButton.method );
} );
