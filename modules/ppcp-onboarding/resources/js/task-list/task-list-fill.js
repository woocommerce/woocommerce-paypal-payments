/**
 * External dependencies
 */
import { WooPaymentGatewayConfigure } from '@woocommerce/onboarding';
import { registerPlugin } from '@wordpress/plugins';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ConnectionButton } from './connection-button';
import { ConnectionForm } from './connection-form';

const TaskListFill = () => {
	const [ showConnectionForm, setShowConnectionForm ] = useState( false );

	return (
		<WooPaymentGatewayConfigure id="ppcp-gateway">
			{ ( { markConfigured, paymentGateway } ) => {
				return showConnectionForm ? (
					<ConnectionForm
						markConfigured={ markConfigured }
						paymentGateway={ paymentGateway }
					/>
				) : (
					<ConnectionButton
						markConfigured={ markConfigured }
						onError={ () => setShowConnectionForm( true ) }
					/>
				);
			} }
		</WooPaymentGatewayConfigure>
	);
};

registerPlugin( 'ppcp-task-list-fill', {
	render: TaskListFill,
	scope: 'woocommerce-admin'
} );
