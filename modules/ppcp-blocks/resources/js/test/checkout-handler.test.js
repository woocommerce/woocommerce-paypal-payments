import { render} from '@testing-library/react';
import {CheckoutHandler} from "../Components/checkout-handler";

test('checkout handler', () => {
    render(<CheckoutHandler getCardFieldsForm={() => {}}/>)
});
