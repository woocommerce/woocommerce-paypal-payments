import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';

import { CommonHooks } from '../../../../../../data';
import { HStack } from '../../../../../ReusableComponents/Stack';

const DisconnectButton = () => {
	const [ isOpen, setIsOpen ] = useState( false );
	const { disconnectMerchant } = CommonHooks.useDisconnectMerchant();

	const handleOpen = useCallback( () => {
		setIsOpen( true );
	}, [] );

	const handleCancel = useCallback( () => {
		setIsOpen( false );
	}, [] );

	const handleConfirm = useCallback( async () => {
		await disconnectMerchant();
		window.location.reload();
	}, [ disconnectMerchant ] );

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
					<HStack>
						<Button variant="tertiary" onClick={ handleCancel }>
							{ __( 'Cancel', 'woocommerce-paypal-payments' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive={ true }
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
