import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	ToggleControl,
	RadioControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

import PaymentMethodModal from '@ppcp-settings/Components/ReusableComponents/PaymentMethodModal';
import { PaymentHooks } from '@ppcp-settings/data';

const Modal = ( { method, setModalIsVisible, onSave } ) => {
	const { all: paymentMethods } = PaymentHooks.usePaymentMethods();
	const persistentValues = PaymentHooks.usePaymentMethodsModal();

	const methodConfig = paymentMethods.find( ( i ) => i.id === method?.id );

	// Build a unified value map from all sources.
	// Persistent store values cover fields like paypalShowLogo, puiBrandName, etc.
	// Method-level values cover checkout page title and description.
	const currentValues = {
		...persistentValues,
		checkoutPageTitle: methodConfig?.title,
		checkoutPageDescription: methodConfig?.description,
	};

	const [ settings, setSettings ] = useState( () => {
		if ( ! methodConfig?.fields ) {
			return {};
		}

		const initial = {};
		Object.entries( methodConfig.fields ).forEach( ( [ key, field ] ) => {
			initial[ key ] = currentValues[ key ] ?? field.default;
		} );
		return initial;
	} );

	if ( ! method?.id || ! methodConfig?.fields ) {
		return null;
	}

	/**
	 * Check if all required fields have non-empty values.
	 *
	 * @return {boolean} True if all required fields are filled.
	 */
	const areRequiredFieldsValid = () => {
		return Object.entries( methodConfig.fields ).every(
			( [ key, field ] ) => {
				if ( ! field.required ) {
					return true;
				}
				const value = settings[ key ];
				return typeof value === 'string'
					? value.trim() !== ''
					: value != null;
			}
		);
	};

	const updateSetting = ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const renderField = ( key, field ) => {
		const fieldLabel = field.required ? `${ field.label } *` : field.label;

		switch ( field.type ) {
			case 'text':
				return (
					<div key={ key } className="ppcp-r-modal__field-row">
						<TextControl
							__nextHasNoMarginBottom
							className="ppcp-r-vertical-text-control"
							label={ fieldLabel }
							help={ field.description }
							value={ settings[ key ] }
							onChange={ ( value ) =>
								updateSetting( key, value )
							}
						/>
					</div>
				);

			case 'toggle':
				return (
					<div key={ key } className="ppcp-r-modal__field-row">
						<ToggleControl
							__nextHasNoMarginBottom
							label={ field.label }
							checked={ settings[ key ] }
							onChange={ ( value ) =>
								updateSetting( key, value )
							}
						/>
					</div>
				);

			case 'radio':
				return (
					<>
						<div className="ppcp-r-modal__field-row">
							<strong className="ppcp-r-modal__content-title">
								{ field.label }
							</strong>
							{ field.description && (
								<span className="ppcp-r-modal__field-description">
									{ field.description }
								</span>
							) }
						</div>
						<div className="ppcp-r-modal__field-row">
							<RadioControl
								selected={ settings[ key ] }
								options={ field.options }
								onChange={ ( value ) =>
									updateSetting( key, value )
								}
							/>
						</div>
					</>
				);

			default:
				return null;
		}
	};

	const handleSave = () => {
		if ( ! areRequiredFieldsValid() ) {
			return;
		}
		onSave?.( method.id, settings );
		setModalIsVisible( false );
	};

	return (
		<PaymentMethodModal
			setModalIsVisible={ setModalIsVisible }
			icon={ methodConfig.icon }
			title={ method.title }
		>
			<div className="ppcp-r-modal__field-rows">
				{ Object.entries( methodConfig.fields ).map(
					( [ key, field ] ) => renderField( key, field )
				) }

				<div className="ppcp-r-modal__field-row ppcp-r-modal__field-row--save">
					<Button
						variant="primary"
						onClick={ handleSave }
						disabled={ ! areRequiredFieldsValid() }
					>
						{ __( 'Save changes', 'woocommerce-paypal-payments' ) }
					</Button>
				</div>
			</div>
		</PaymentMethodModal>
	);
};

export default Modal;
