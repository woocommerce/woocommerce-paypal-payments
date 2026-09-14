import { __ } from '@wordpress/i18n';
import { Button, Modal, ToggleControl } from '@wordpress/components';

import { HStack } from '@ppcp-settings/Components/ReusableComponents/Stack';

/**
 * Dialog confirming a consequential action, optionally with a toggle that changes
 * the scope of that action.
 *
 * @param {Object}   props                 Component props.
 * @param {string}   props.className       Modal class, used to scope its styling.
 * @param {string}   props.title           Dialog title.
 * @param {string}   props.description     Sentence explaining the action.
 * @param {?Object}  [props.toggle]        {label, help, checked, onChange, className}.
 * @param {*}        [props.children]      Extra content rendered below the toggle.
 * @param {string}   props.confirmLabel    Label of the confirm button.
 * @param {boolean}  [props.isDestructive] Style the confirm button as destructive.
 * @param {Function} props.onConfirm       Called when the merchant confirms.
 * @param {Function} props.onCancel        Called when the dialog is dismissed.
 */
const ConfirmationModal = ( {
	className,
	title,
	description,
	toggle = null,
	children = null,
	confirmLabel,
	isDestructive = false,
	onConfirm,
	onCancel,
} ) => (
	<Modal
		className={ className }
		size="small"
		title={ title }
		onRequestClose={ onCancel }
	>
		<p>{ description }</p>
		{ toggle && (
			<ToggleControl
				__nextHasNoMarginBottom
				className={ toggle.className }
				checked={ toggle.checked }
				onChange={ toggle.onChange }
				label={ toggle.label }
				help={ toggle.help }
			/>
		) }
		{ children }
		<HStack className="ppcp--action-buttons">
			<Button variant="tertiary" onClick={ onCancel }>
				{ __( 'Cancel', 'woocommerce-paypal-payments' ) }
			</Button>
			<Button
				variant="primary"
				isDestructive={ isDestructive }
				onClick={ onConfirm }
			>
				{ confirmLabel }
			</Button>
		</HStack>
	</Modal>
);

export default ConfirmationModal;
