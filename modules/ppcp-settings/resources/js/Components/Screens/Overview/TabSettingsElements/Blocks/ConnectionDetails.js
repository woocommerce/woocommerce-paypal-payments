import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import {
	AccordionSettingsBlock,
	RadioSettingsBlock,
	InputSettingsBlock,
} from '../../../../ReusableComponents/SettingsBlocks';
import {
	sandboxData,
	productionData,
} from '../../../../../data/settings/connection-details-data';

const ConnectionDetails = ( { settings, updateFormValue } ) => {
	const isSandbox = settings.sandboxConnected;

	const modeConfig = isSandbox
		? productionData( { settings, updateFormValue } )
		: sandboxData( { settings, updateFormValue } );

	const modeKey = isSandbox ? 'productionMode' : 'sandboxMode';

	return (
		<AccordionSettingsBlock
			title={ modeConfig.title }
			description={ modeConfig.description }
		>
			<RadioSettingsBlock
				title={ modeConfig.connectTitle }
				description={ modeConfig.connectDescription }
				options={ modeConfig.options }
				actionProps={ {
					key: modeKey,
					currentValue: settings[ modeKey ],
					callback: updateFormValue,
				} }
			/>
		</AccordionSettingsBlock>
	);
};

export default ConnectionDetails;
