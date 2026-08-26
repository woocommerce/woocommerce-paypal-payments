import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';

import { CommonHooks } from '@ppcp-settings/data';
import FeatureSettingsBlock from '../../../../../ReusableComponents/SettingsBlocks/FeatureSettingsBlock';
import data from '../../../../../../utils/data';
import { useBlueprintExport } from '../../../../../../hooks/useBlueprintExport';
import { useToggleState } from '../../../../../../hooks/useToggleState';
import BlueprintExportModal from '../Parts/BlueprintExportModal';

const BlueprintExportImport = () => {
	const { blueprint } = data();
	const { exportBlueprint, isExporting } = useBlueprintExport();
	const { isConnected } = CommonHooks.useMerchant();
	const { isReady } = CommonHooks.useStore();
	// No id: opening the dialog from the URL would skip the isConnected branch.
	const { isOpen, setIsOpen } = useToggleState();

	const handleExportClick = useCallback( () => {
		// isConnected defaults to false until the merchant resolves.
		if ( ! isReady ) {
			return;
		}

		// Nothing to opt in to while disconnected.
		if ( ! isConnected ) {
			exportBlueprint();
			return;
		}

		setIsOpen( true );
	}, [ isReady, isConnected, exportBlueprint, setIsOpen ] );

	const handleCancel = useCallback( () => {
		setIsOpen( false );
	}, [ setIsOpen ] );

	const handleExport = useCallback(
		( options ) => {
			setIsOpen( false );
			exportBlueprint( options );
		},
		[ setIsOpen, exportBlueprint ]
	);

	if ( ! blueprint?.isActive ) {
		return null;
	}

	return (
		<>
			<FeatureSettingsBlock
				title={ __(
					'WooCommerce Blueprint Export & Import',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Export or import your current PayPal Payments settings across WooCommerce sites. Connection credentials are excluded from the export unless you choose to include them.',
					'woocommerce-paypal-payments'
				) }
				actionProps={ {
					isBusy: isExporting || ! isReady,
					buttons: [
						{
							text: __( 'Export', 'woocommerce-paypal-payments' ),
							type: 'secondary',
							class: 'small-button',
							onClick: handleExportClick,
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
			{ isOpen && (
				<BlueprintExportModal
					onExport={ handleExport }
					onCancel={ handleCancel }
				/>
			) }
		</>
	);
};

export default BlueprintExportImport;
