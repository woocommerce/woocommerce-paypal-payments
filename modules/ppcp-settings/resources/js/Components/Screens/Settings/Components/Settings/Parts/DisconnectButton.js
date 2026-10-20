import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';

import { CommonHooks } from '@ppcp-settings/data';
import { useToggleState } from '@ppcp-settings/hooks/useToggleState';
import ConfirmationModal from '@ppcp-settings/Components/ReusableComponents/ConfirmationModal';
import { useNavigation } from '@ppcp-settings/hooks/useNavigation';

const DisconnectButton = () => {
	const { isOpen, setIsOpen } = useToggleState( 'disconnect-merchant' );
	const [ resetFlag, setResetFlag ] = useState( false );
	const { disconnectMerchant } = CommonHooks.useDisconnectMerchant();
	const { goToPluginSettings } = useNavigation();

	const handleOpen = useCallback( () => {
		setIsOpen( true );
	}, [ setIsOpen ] );

	const handleCancel = useCallback( () => {
		setIsOpen( false );
	}, [ setIsOpen ] );

	const handleConfirm = useCallback( async () => {
		await disconnectMerchant( resetFlag );
		goToPluginSettings();
	}, [ disconnectMerchant, resetFlag ] );

	const confirmationTitle = __(
		'Disconnect from PayPal?',
		'woocommerce-paypal-payments'
	);

	return (
		<>
			<Button
				variant="tertiary"
				isDestructive={ true }
				onClick={ handleOpen }
			>
				{ __( 'Disconnect', 'woocommerce-paypal-payments' ) }
			</Button>

			{ isOpen && (
				<ConfirmationModal
					className="ppcp--modal-disconnect"
					title={ confirmationTitle }
					description={ __(
						'Disconnecting your account will restart the connection wizard. Are you sure you want to disconnect from your PayPal account?',
						'woocommerce-paypal-payments'
					) }
					toggle={ {
						className: 'ppcp--toggle-danger',
						checked: resetFlag,
						onChange: setResetFlag,
						label: __(
							'Start over',
							'woocommerce-paypal-payments'
						),
						help: resetFlag
							? __(
									'Attention: The plugin is reset to its initial state!',
									'woocommerce-paypal-payments'
							  )
							: __(
									'Disconnect, but preserve all settings',
									'woocommerce-paypal-payments'
							  ),
					} }
					confirmLabel={ __(
						'Disconnect',
						'woocommerce-paypal-payments'
					) }
					isDestructive={ resetFlag }
					onConfirm={ handleConfirm }
					onCancel={ handleCancel }
				/>
			) }
		</>
	);
};

export default DisconnectButton;
