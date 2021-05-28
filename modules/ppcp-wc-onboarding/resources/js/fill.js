/**
 * External dependencies
 */
import { WooRemotePaymentForm } from '@woocommerce/components';
import { registerPlugin } from '@wordpress/plugins';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ConnectionButton } from './connection-button';
import { ConnectionForm } from './connection-form';

const PpcpWcOnboardingFill = () => {
	const [ showConnectionForm, setShowConnectionForm ] = useState( false );

	return (
		<WooRemotePaymentForm id="payfast">
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
		</WooRemotePaymentForm>
	);
};

registerPlugin( 'ppcp-wc-onboarding-fill', {
	render: PpcpWcOnboardingFill,
} );
