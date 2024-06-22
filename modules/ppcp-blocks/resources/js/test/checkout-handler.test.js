import {render, screen} from '@testing-library/react';
import '@testing-library/jest-dom';
import {CheckoutHandler} from "../Components/checkout-handler";

test('checkout handler label display given text', async () => {
    render(
        <CheckoutHandler
            getCardFieldsForm={() => {}}
            saveCardText="Foo"
            is_vaulting_enabled={true}
        />
    );

    await expect(screen.getByLabelText('Foo')).toBeInTheDocument();
});
