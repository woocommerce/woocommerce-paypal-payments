import { __ } from '@wordpress/i18n';
import { CommonHooks } from '../../../../../../data';
import { Title } from '../../../../../ReusableComponents/SettingsBlocks';

const HooksTableBlock = () => {
	const { webhooks } = CommonHooks.useWebhooks();
	const { url, events } = webhooks;

	if ( ! url || ! events?.length ) {
		return <div>...</div>;
	}

	return (
		<>
			<WebhookUrl url={ url } />
			<WebhookEvents events={ events } />
		</>
	);
};

const WebhookUrl = ( { url } ) => {
	return (
		<div>
			<Title>
				{ __( 'Notification URL', 'woocommerce-paypal-payments' ) }
			</Title>
			<p>{ url }</p>
		</div>
	);
};

const WebhookEvents = ( { events } ) => {
	return (
		<div>
			<Title>
				{ __( 'Subscribed Events', 'woocommerce-paypal-payments' ) }
			</Title>
			<ul>
				{ events.map( ( event, index ) => (
					<li key={ index }>{ event }</li>
				) ) }
			</ul>
		</div>
	);
};

export default HooksTableBlock;
