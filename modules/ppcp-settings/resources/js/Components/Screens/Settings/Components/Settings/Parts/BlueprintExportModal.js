import { __ } from '@wordpress/i18n';
import { useCallback, useState } from '@wordpress/element';

import { CommonHooks } from '@ppcp-settings/data';
import ConfirmationModal from '@ppcp-settings/Components/ReusableComponents/ConfirmationModal';

/**
 * Toggle help text: states the outcome for the importing store, not the risk.
 *
 * @param {boolean} includeConnection Whether credentials are included.
 * @param {boolean} isSandbox         Whether the connected account is a sandbox account.
 * @return {string} Help text shown under the toggle.
 */
const outcomeDescription = ( includeConnection, isSandbox ) => {
	if ( ! includeConnection ) {
		return __(
			'Export settings only. The store that imports this file will need to connect its own PayPal account.',
			'woocommerce-paypal-payments'
		);
	}

	return isSandbox
		? __(
				'The store that imports this file will connect as your sandbox PayPal account.',
				'woocommerce-paypal-payments'
		  )
		: __(
				'The store that imports this file will connect as your live PayPal account.',
				'woocommerce-paypal-payments'
		  );
};

/**
 * States the risk of holding the exported file.
 *
 * Deliberately identical for sandbox and live: a stale environment flag would
 * otherwise show the reassuring copy to someone exporting live credentials.
 *
 * @return {string} Warning shown when credentials are included.
 */
const credentialsWarning = () =>
	__(
		'Warning: The exported file will contain your client ID, client secret and merchant details in plain text. Anyone who has the file can act on your PayPal account, so store it securely and do not share it.',
		'woocommerce-paypal-payments'
	);

/**
 * Confirmation dialog shown before a Blueprint export.
 *
 * The credentials choice is asked per export and never stored: a persisted setting
 * would carry credentials into every later export without asking again.
 *
 * @param {Object}   props          Component props.
 * @param {Function} props.onExport Called with { includeConnection } to run the export.
 * @param {Function} props.onCancel Called when the dialog is dismissed.
 */
const BlueprintExportModal = ( { onExport, onCancel } ) => {
	const [ includeConnection, setIncludeConnection ] = useState( false );
	const { isSandbox } = CommonHooks.useMerchant();

	const handleConfirm = useCallback( () => {
		onExport( { includeConnection } );
	}, [ onExport, includeConnection ] );

	return (
		<ConfirmationModal
			className="ppcp--modal-blueprint-export"
			title={ __( 'Export settings', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Exports your PayPal Payments settings so they can be imported into another WooCommerce store.',
				'woocommerce-paypal-payments'
			) }
			toggle={ {
				className: 'ppcp--toggle-warning',
				checked: includeConnection,
				onChange: setIncludeConnection,
				label: __(
					'Include connection credentials',
					'woocommerce-paypal-payments'
				),
				help: outcomeDescription( includeConnection, isSandbox ),
			} }
			confirmLabel={ __( 'Export', 'woocommerce-paypal-payments' ) }
			onConfirm={ handleConfirm }
			onCancel={ onCancel }
		>
			{ /* Kept mounted and revealed via CSS so the modal grows smoothly. */ }
			<div
				className={ `ppcp--credentials-warning${
					includeConnection ? ' ppcp--is-open' : ''
				}` }
				aria-hidden={ ! includeConnection }
			>
				<div className="ppcp--content">
					{ /* Announced on reveal; the toggle's help text states the
					     outcome, not the risk. */ }
					<span className="ppcp--warning-body" role="alert">
						{ credentialsWarning() }
					</span>
				</div>
			</div>
		</ConfirmationModal>
	);
};

export default BlueprintExportModal;
