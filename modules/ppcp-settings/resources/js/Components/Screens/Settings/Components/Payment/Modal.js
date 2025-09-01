import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	ToggleControl,
	RadioControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

import PaymentMethodModal from '../../../../ReusableComponents/PaymentMethodModal';
import { PaymentHooks } from '../../../../../data';

const Modal = ( { method, setModalIsVisible, onSave } ) => {
	const { all: paymentMethods } = PaymentHooks.usePaymentMethods();
	const { paypalShowLogo, fastlaneCardholderName, fastlaneDisplayWatermark } =
		PaymentHooks.usePaymentMethodsModal();

	const [ settings, setSettings ] = useState( () => {
		if ( ! method?.id ) {
			return {};
		}

		const methodConfig = paymentMethods.find( ( i ) => i.id === method.id );
		if ( ! methodConfig?.fields ) {
			return {};
		}

		const initialSettings = {};
		Object.entries( methodConfig.fields ).forEach( ( [ key, field ] ) => {
			switch ( key ) {
				case 'checkoutPageTitle':
					initialSettings[ key ] = methodConfig.title;
					break;
				case 'checkoutPageDescription':
					initialSettings[ key ] = methodConfig.description;
					break;
				default:
					initialSettings[ key ] = field.default;
			}
		} );

		initialSettings.paypalShowLogo = paypalShowLogo;
		initialSettings.fastlaneCardholderName = fastlaneCardholderName;
		initialSettings.fastlaneDisplayWatermark = fastlaneDisplayWatermark;

		return initialSettings;
	} );

	if ( ! method?.id ) {
		return null;
	}

	const methodConfig = paymentMethods.find( ( i ) => i.id === method.id );
	if ( ! methodConfig?.fields ) {
		return null;
	}

	const renderField = ( key, field ) => {
		switch ( field.type ) {
			case 'text':
				return (
					<div key={ key } className="ppcp-r-modal__field-row">
						<TextControl
							__nextHasNoMarginBottom
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
					<div key={ key } className="ppcp-r-modal__field-row">
						<ToggleControl
							__nextHasNoMarginBottom
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
