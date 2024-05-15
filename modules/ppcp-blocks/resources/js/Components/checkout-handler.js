import {useEffect} from '@wordpress/element';
import {usePayPalCardFields} from "@paypal/react-paypal-js";

export const CheckoutHandler = ({onPaymentSetup, responseTypes}) => {
    const {cardFieldsForm} = usePayPalCardFields();

    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {

            cardFieldsForm.submit()
                .then(() => {
                    return {
                        type: responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                foo: 'bar',
                            }
                        }
                    };
                })
                .catch((err) => {
                    return {
                        type: responseTypes.ERROR,
                        message: err
                    }
                });
        })

        return unsubscribe
    }, [onPaymentSetup, cardFieldsForm]);

    return null
}
