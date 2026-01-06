import { __ } from '@wordpress/i18n';
import FeatureSettingsBlock from '../../../../../ReusableComponents/SettingsBlocks/FeatureSettingsBlock';
import data from '../../../../../../utils/data';
import { useBlueprintExport } from '../../../../../../hooks/useBlueprintExport';

const BlueprintExportImport = () => {
	const { blueprint } = data();
	const { exportBlueprint, isExporting } = useBlueprintExport();

	if ( ! blueprint?.isActive ) {
		return null;
	}

	return (
		<FeatureSettingsBlock
			title={ __(
				'WooCommerce Blueprint Export & Import',
				'woocommerce-paypal-payments'
			) }
			description={ __(
				'Export or import your current PayPal Payments settings across WooCommerce sites.',
				'woocommerce-paypal-payments'
			) }
			actionProps={ {
				isBusy: isExporting,
				buttons: [
					{
						text: __( 'Export', 'woocommerce-paypal-payments' ),
						type: 'secondary',
						class: 'small-button',
						onClick: exportBlueprint,
					},
					{
						text: __( 'Import', 'woocommerce-paypal-payments' ),
						type: 'tertiary',
						class: 'small-button',
						url: blueprint.importUrl,
						target: false,
					},
				],
			} }
		/>
	);
};

export default BlueprintExportImport;
