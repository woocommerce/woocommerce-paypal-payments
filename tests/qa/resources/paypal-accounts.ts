/**
 * Internal dependencies
 */
import { PayPalAccount } from './types';

const usa: PayPalAccount = {
	email: process.env.PAYPAL_PERSONAL_EMAIL_US,
	password: process.env.PAYPAL_PERSONAL_PASS_US,
};

const germany: PayPalAccount = {
	email: process.env.PAYPAL_PERSONAL_EMAIL_DE,
	password: process.env.PAYPAL_PERSONAL_PASS_DE,
};

const mexico: PayPalAccount = {
	email: process.env.PAYPAL_PERSONAL_EMAIL_MX,
	password: process.env.PAYPAL_PERSONAL_PASS_MX,
};

export const payPalAccounts: {
	[ key: string ]: PayPalAccount;
} = {
	germany,
	usa,
	mexico,
};
