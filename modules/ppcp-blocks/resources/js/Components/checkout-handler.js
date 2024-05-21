import {useEffect} from '@wordpress/element';
import {usePayPalCardFields} from "@paypal/react-paypal-js";

export const CheckoutHandler = ({getCardFieldsForm}) => {
    const {cardFieldsForm} = usePayPalCardFields();

    useEffect(() => {
        getCardFieldsForm(cardFieldsForm)
    }, []);

    return null
}
