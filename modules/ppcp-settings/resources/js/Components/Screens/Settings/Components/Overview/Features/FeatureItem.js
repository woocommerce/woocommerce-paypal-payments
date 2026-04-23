import { __ } from '@wordpress/i18n';
import { FeatureSettingsBlock } from '@ppcp-settings/Components/ReusableComponents/SettingsBlocks';
import { Content } from '@ppcp-settings/Components/ReusableComponents/Elements';
import { TITLE_BADGE_POSITIVE } from '@ppcp-settings/Components/ReusableComponents/TitleBadge';
import { selectTab, TAB_IDS } from '@ppcp-settings/utils/tabSelector';
import { useDispatch } from '@wordpress/data';
import { STORE_NAME as COMMON_STORE_NAME } from '@ppcp-settings/data/common';

const FeatureItem = ( {
	isBusy,
	isSandbox,
	title,
	description,
	buttons,
	enabled,
	notes,
} ) => {
	const { setActiveModal } = useDispatch( COMMON_STORE_NAME );
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
			const highlight =
				feature.action?.highlight === undefined
					? true
					: Boolean( feature.action.highlight );
			const tabId = TAB_IDS[ feature.action.tab.toUpperCase() ];
			await selectTab( tabId, feature.action.section, highlight );
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
