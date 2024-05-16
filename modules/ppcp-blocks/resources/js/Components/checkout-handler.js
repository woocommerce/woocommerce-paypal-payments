import {useEffect} from '@wordpress/element';
import {usePayPalCardFields} from "@paypal/react-paypal-js";

export const CheckoutHandler = ({onPaymentSetup, responseTypes}) => {
    const {cardFieldsForm} = usePayPalCardFields();

    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {

            await cardFieldsForm.submit()
                .catch((error) => {
                    console.error(error)
                    return {
                        type: responseTypes.ERROR,
                    }
                });
        })

        return unsubscribe
    }, [onPaymentSetup, cardFieldsForm]);

    return null
}
