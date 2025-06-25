import { __ } from '@wordpress/i18n';
import { Button, Modal, ToggleControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';

import { CommonHooks } from '../../../../../../data';
import { useToggleState } from '../../../../../../hooks/useToggleState';
import { HStack } from '../../../../../ReusableComponents/Stack';
import { useNavigation } from '../../../../../../hooks/useNavigation';

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
				<Modal
					className="ppcp--modal-disconnect"
					size="small"
					title={ confirmationTitle }
					onRequestClose={ handleCancel }
				>
					<p>
						{ __(
							'Disconnecting your account will restart the connection wizard. Are you sure you want to disconnect from your PayPal account?',
							'woocommerce-paypal-payments'
						) }
					</p>
					<ToggleControl
						__nextHasNoMarginBottom
						className="ppcp--toggle-danger"
						checked={ resetFlag }
						onChange={ setResetFlag }
						label={ __(
							'Start over',
							'woocommerce-paypal-payments'
						) }
						help={
							resetFlag
								? __(
										'Attention: The plugin is reset to its initial state!',
										'woocommerce-paypal-payments'
								  )
								: __(
										'Disconnect, but preserve all settings',
										'woocommerce-paypal-payments'
								  )
						}
					/>
					<HStack className="ppcp--action-buttons">
						<Button variant="tertiary" onClick={ handleCancel }>
							{ __( 'Cancel', 'woocommerce-paypal-payments' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive={ resetFlag }
							onClick={ handleConfirm }
						>
							{ __(
								'Disconnect',
								'woocommerce-paypal-payments'
							) }
						</Button>
					</HStack>
				</Modal>
			) }
		</>
	);
};

export default DisconnectButton;
