import { __ } from '@wordpress/i18n';

import { CommonHooks } from '../../../../../../data';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import { Title } from '../../../../../ReusableComponents/Elements';

const HooksListBlock = () => {
	const { webhooks } = CommonHooks.useWebhooks();
	const { url, events } = webhooks;

	if ( ! url || ! events?.length ) {
		return <div>...</div>;
	}

	return (
		<SettingsBlock separatorAndGap={ false } className="ppcp--webhooks">
			<WebhookUrl url={ url } />
			<WebhookEvents events={ events } />
		</SettingsBlock>
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
			<ul className="ppcp--webhook-list">
				{ events.map( ( event, index ) => (
					<li key={ index }>{ event }</li>
				) ) }
			</ul>
		</div>
	);
};

export default HooksListBlock;
