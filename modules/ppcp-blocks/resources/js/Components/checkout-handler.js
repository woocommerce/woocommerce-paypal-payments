import {useEffect} from '@wordpress/element';
import {usePayPalCardFields} from "@paypal/react-paypal-js";

export const CheckoutHandler = ({getCardFieldsForm}) => {
    const {cardFieldsForm} = usePayPalCardFields();

    useEffect(() => {
        getCardFieldsForm(cardFieldsForm)
    }, []);

    return (
        <>
            <input type="checkbox" id="save" name="save"/>
            <label htmlFor="save">Save your card</label>
        </>
    )
}
