import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { useState, useEffect } from '@wordpress/element';

const config = wc.wcSettings.getSetting( 'ppcp-pay-upon-invoice-gateway_data' );

const isGerman = config.siteLanguage === 'de';

const PuiContent = ( { eventRegistration, emitResponse } ) => {
	const { onPaymentSetup } = eventRegistration;
	const [ birthDate, setBirthDate ] = useState( '' );
	const [ phone, setPhone ] = useState( '' );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			if ( config.requiresBirthDate && ! birthDate ) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: isGerman
						? 'Bitte geben Sie Ihr Geburtsdatum ein.'
						: 'Please enter your birth date.',
				};
			}

			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						billing_birth_date: birthDate,
						billing_phone: phone,
					},
				},
			};
		} );

		return () => unsubscribe();
	}, [
		onPaymentSetup,
		emitResponse.responseTypes.SUCCESS,
		emitResponse.responseTypes.ERROR,
		birthDate,
		phone,
	] );

	const ratepayTerms = isGerman
		? config.ratepayTerms.de
		: config.ratepayTerms.en;

	return (
		<div className="ppcp-pui-fields">
			{ config.description && (
				<div
					dangerouslySetInnerHTML={ { __html: config.description } }
				/>
			) }
			<p className="form-row form-row-wide">
				<label htmlFor="ppcp-pui-birth-date">
					{ isGerman ? 'Geburtsdatum' : 'Birth date' }
				</label>
				<input
					type="date"
					id="ppcp-pui-birth-date"
					className="input-text"
					value={ birthDate }
					onChange={ ( event ) => setBirthDate( event.target.value ) }
					required
				/>
			</p>
			{ config.requiresPhone && (
				<p className="form-row form-row-wide">
					<label htmlFor="ppcp-pui-phone">
						{ isGerman ? 'Telefon' : 'Phone' }
					</label>
					<input
						type="tel"
						id="ppcp-pui-phone"
						className="input-text"
						value={ phone }
						onChange={ ( event ) => setPhone( event.target.value ) }
						required
					/>
				</p>
			) }
			<div
				className="ppcp-pui-ratepay-terms"
				dangerouslySetInnerHTML={ { __html: ratepayTerms } }
			/>
		</div>
	);
};

registerPaymentMethod( {
	name: config.id,
	label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
	content: <PuiContent />,
	edit: <div></div>,
	ariaLabel: config.title,
	canMakePayment: () => {
		return true;
	},
	supports: {
		features: config.supports,
	},
} );
