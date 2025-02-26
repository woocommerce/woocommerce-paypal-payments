import { __ } from '@wordpress/i18n';
import { FeatureSettingsBlock } from '../../../../../ReusableComponents/SettingsBlocks';
import { Content } from '../../../../../ReusableComponents/Elements';
import { TITLE_BADGE_POSITIVE } from '../../../../../ReusableComponents/TitleBadge';
import { selectTab, TAB_IDS } from '../../../../../../utils/tabSelector';
import { setActiveModal } from '../../../../../../data/common/actions';

const FeatureItem = ( {
	isBusy,
	isSandbox,
	title,
	description,
	buttons,
	enabled,
	notes,
} ) => {
	const getButtonUrl = ( button ) => {
		if ( button.urls ) {
			return isSandbox ? button.urls.sandbox : button.urls.live;
		}

		return button.url;
	};

	const visibleButtons = buttons.filter(
		( button ) =>
			! button.showWhen || // Learn more buttons
			( enabled && button.showWhen === 'enabled' ) ||
			( ! enabled && button.showWhen === 'disabled' )
	);
	const handleClick = async ( feature ) => {
		if ( feature.action?.type === 'tab' ) {
			const tabId = TAB_IDS[ feature.action.tab.toUpperCase() ];
			await selectTab( tabId, feature.action.section );
		}
		if ( feature.action?.modal ) {
			setActiveModal( feature.action.modal );
		}
	};

	const actionProps = {
		isBusy,
		enabled,
		notes,
		buttons: visibleButtons.map( ( button ) => ( {
			...button,
			url: getButtonUrl( button ),
			onClick: () => handleClick( button ),
		} ) ),
	};

	if ( enabled ) {
		actionProps.badge = {
			text: __( 'Active', 'woocommerce-paypal-payments' ),
			type: TITLE_BADGE_POSITIVE,
		};
	}

	return (
		<Content>
			<FeatureSettingsBlock
				title={ title }
				description={ description }
				actionProps={ actionProps }
			/>
		</Content>
	);
};

export default FeatureItem;
