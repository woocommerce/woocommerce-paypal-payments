const visa: WooCommerce.CreditCard = {
	card_number: '4444333322221111',
	expiration_date: '12/30',
	card_cvv: '029',
	card_type: 'Visa',
};

const visa2: WooCommerce.CreditCard = {
	card_number: '4012000077777777',
	expiration_date: '12/30',
	card_cvv: '123',
	card_type: 'Visa',
};

const visaFastlane: WooCommerce.CreditCard = {
	card_number: '4000000000002701', // for Ryan's fastlane only last 4 digits matter
	expiration_date: '12/30',
	card_cvv: '123',
	card_type: 'Visa',
};

const visa3ds: WooCommerce.CreditCard = {
	card_number: '4020024518402084',
	expiration_date: '01/30',
	card_cvv: '123',
	card_type: 'Visa',
	code_3ds: '1234',
};

const mastercard: WooCommerce.CreditCard = {
	card_number: '2223000048400011',
	expiration_date: '12/30',
	card_cvv: '456',
	card_type: 'Mastercard',
};

const declined: WooCommerce.CreditCard = {
	card_number: '4032037524607534',
	expiration_date: '09/30',
	card_cvv: '340',
	card_type: 'Visa',
};

export const cards: {
	[ key: string ]: WooCommerce.CreditCard;
} = {
	visa,
	visa2,
	visaFastlane,
	visa3ds,
	mastercard,
	declined,
};
