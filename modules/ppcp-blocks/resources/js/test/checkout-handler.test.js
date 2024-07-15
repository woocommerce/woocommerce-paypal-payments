import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { CheckoutHandler } from '../Components/checkout-handler';

test( 'checkbox label displays the given text', async () => {
	render(
		<CheckoutHandler
			getCardFieldsForm={ () => {} }
			saveCardText="Foo"
			is_vaulting_enabled={ true }
		/>
	);

	await expect( screen.getByLabelText( 'Foo' ) ).toBeInTheDocument();
} );

test( 'click checkbox calls function passing checked value', async () => {
	const getSavePayment = jest.fn();

	render(
		<CheckoutHandler
			getSavePayment={ getSavePayment }
			getCardFieldsForm={ () => {} }
			saveCardText="Foo"
			is_vaulting_enabled={ true }
		/>
	);

	await userEvent.click( screen.getByLabelText( 'Foo' ) );

	await expect( getSavePayment.mock.calls ).toHaveLength( 1 );
	await expect( getSavePayment.mock.calls[ 0 ][ 0 ] ).toBe( true );
} );
