import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

import { ControlButton } from '../../../../../ReusableComponents/Controls';
import { CommonHooks } from '../../../../../../data';
import {
	NOTIFICATION_ERROR,
	NOTIFICATION_SUCCESS,
} from '../../../../../ReusableComponents/Icons';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';

const SimulationBlock = () => {
	const {
		createSuccessNotice,
		createInfoNotice,
		createErrorNotice,
		removeNotice,
	} = useDispatch( noticesStore );
	const { startWebhookSimulation, checkWebhookSimulationState } =
		CommonHooks.useWebhooks();
	const [ simulating, setSimulating ] = useState( false );
	const sleep = ( ms ) => {
		return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	};
	const startSimulation = async ( maxRetries ) => {
		const webhookInfoNoticeId = 'paypal-webhook-simulation-info-notice';
		const triggerWebhookInfoNotice = () => {
			createInfoNotice(
				__(
					'Waiting for the webhook to arrive…',
					'woocommerce-paypal-payments'
				),
				{
					id: webhookInfoNoticeId,
				}
			);
		};

		const stopSimulation = () => {
			removeNotice( webhookInfoNoticeId );
			setSimulating( false );
		};

		setSimulating( true );

		triggerWebhookInfoNotice();

		try {
			await startWebhookSimulation();
		} catch ( error ) {
			console.error( error );
			setSimulating( false );
			createErrorNotice(
				__(
					'Operation failed. Check WooCommerce logs for more details.',
					'woocommerce-paypal-payments'
				),
				{
					icon: NOTIFICATION_ERROR,
				}
			);
			return;
		}

		for ( let i = 0; i < maxRetries; i++ ) {
			await sleep( 2000 );

			const simulationStateResponse = await checkWebhookSimulationState();
			try {
				if ( ! simulationStateResponse.success ) {
					console.error(
						'Simulation state query failed: ' +
							simulationStateResponse?.data
					);
					continue;
				}

				if ( simulationStateResponse?.data?.state === 'received' ) {
					createSuccessNotice(
						__(
							'The webhook was received successfully.',
							'woocommerce-paypal-payments'
						),
						{
							icon: NOTIFICATION_SUCCESS,
						}
					);
					stopSimulation();
					return;
				}
				removeNotice( webhookInfoNoticeId );
				triggerWebhookInfoNotice();
			} catch ( error ) {
				console.error( error );
			}
		}
		stopSimulation();
		createErrorNotice(
			__(
				'Looks like the webhook cannot be received. Check that your website is accessible from the internet.',
				'woocommerce-paypal-payments'
			),
			{
				icon: NOTIFICATION_ERROR,
			}
		);
	};

	return (
		<SettingsBlock
			title={ __( 'Test webhooks', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Send a test-webhook from PayPal to confirm that webhooks are being received and processed correctly.',
				'woocommerce-paypal-payments'
			) }
			horizontalLayout={ true }
			className="ppcp--webhook-simulation"
		>
			<ControlButton
				type={ 'secondary' }
				isBusy={ simulating }
				onClick={ () => startSimulation( 30 ) }
				buttonLabel={ __(
					'Simulate webhooks',
					'woocommerce-paypal-payments'
				) }
			/>
		</SettingsBlock>
	);
};
export default SimulationBlock;
