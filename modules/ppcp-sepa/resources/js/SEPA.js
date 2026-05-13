import { useEffect, useState } from '@wordpress/element';

export function SEPA( { eventRegistration, emitResponse } ) {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;

	const [ iban, setIban ] = useState( '' );

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					return {
						type: responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								ppcp_sepa_iban: iban,
							},
						},
					};
				}

				return handlePaymentProcessing();
			} ),
		[ onPaymentSetup, iban ]
	);

	return (
		<input
			name="ppcp_sepa_iban"
			value={ iban }
			onChange={ ( e ) => setIban( e.target.value ) }
		/>
	);
}
