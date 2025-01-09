import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	ToggleControl,
	RadioControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import PaymentMethodModal from '../../../../ReusableComponents/PaymentMethodModal';
import { getPaymentMethods } from './PaymentMethods';

const Modal = ( { method, setModalIsVisible, onSave } ) => {
	const [ settings, setSettings ] = useState( () => {
		if ( ! method?.id ) {
			return {};
		}

		const methodConfig = getPaymentMethods( method );
		if ( ! methodConfig?.fields ) {
			return {};
		}

		const initialSettings = {};
		Object.entries( methodConfig.fields ).forEach( ( [ key, field ] ) => {
			initialSettings[ key ] = field.default;
		} );
		return initialSettings;
	} );

	if ( ! method?.id ) {
		return null;
	}

	const methodConfig = getPaymentMethods( method );
	if ( ! methodConfig?.fields ) {
		return null;
	}

	const renderField = ( key, field ) => {
		switch ( field.type ) {
			case 'text':
				return (
					<div className="ppcp-r-modal__field-row">
						<TextControl
							className="ppcp-r-vertical-text-control"
							label={ field.label }
							value={ settings[ key ] }
							onChange={ ( value ) =>
								setSettings( ( prev ) => ( {
									...prev,
									[ key ]: value,
								} ) )
							}
						/>
					</div>
				);

			case 'toggle':
				return (
					<div className="ppcp-r-modal__field-row">
						<ToggleControl
							label={ field.label }
							checked={ settings[ key ] }
							onChange={ ( value ) =>
								setSettings( ( prev ) => ( {
									...prev,
									[ key ]: value,
								} ) )
							}
						/>
					</div>
				);

			case 'radio':
				return (
					<>
						<strong className="ppcp-r-modal__content-title">
							{ field.label }
						</strong>
						{ field.description && (
							<p className="ppcp-r-modal__description">
								{ field.description }
							</p>
						) }
						<div className="ppcp-r-modal__field-row">
							<RadioControl
								selected={ settings[ key ] }
								options={ field.options }
								onChange={ ( value ) =>
									setSettings( ( prev ) => ( {
										...prev,
										[ key ]: value,
									} ) )
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
					<Button variant="primary" onClick={ handleSave }>
						{ __( 'Save changes', 'woocommerce-paypal-payments' ) }
					</Button>
				</div>
			</div>
		</PaymentMethodModal>
	);
};

export default Modal;
