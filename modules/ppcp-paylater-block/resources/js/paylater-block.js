import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';

registerBlockType( 'woocommerce-paypal-payments/paylater-messages', {
    edit: Edit,
    save,
} );
