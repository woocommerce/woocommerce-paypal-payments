const visa: WooCommerce.CreditCard = {
	card_number: '4005519200000004',
	// card_number: '4444333322221111',
	// card_number: '4111111111111111',
	expiration_date: '12/30',
	card_cvv: '029',
	card_type: 'VISA',
};

const visa3ds: WooCommerce.CreditCard = {
	card_number: '4020024518402084',
	expiration_date: '01/25',
	card_cvv: '123',
	card_type: 'VISA',
	code_3ds: '1234',
};

const mastercard: WooCommerce.CreditCard = {
	card_number: '2223000048400011',
	expiration_date: '12/30',
	card_cvv: '456',
	card_type: 'Master',
};

const declined: WooCommerce.CreditCard = {
	card_number: '4032037524607534',
	expiration_date: '09/25',
	card_cvv: '340',
	card_type: 'VISA',
};

export const cards: {
	[ key: string ]: WooCommerce.CreditCard;
} = {
	visa,
	visa3ds,
	mastercard,
	declined,
};
