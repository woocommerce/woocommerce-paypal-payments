/**
 * Internal dependencies
 */
import { PayPalAccount } from './types';

const usa: PayPalAccount = {
	email: process.env.PAYPAL_PERSONAL_EMAIL_US,
	password: process.env.PAYPAL_PERSONAL_PASS_US,
};

const usaRefund: PayPalAccount = {
	email: process.env.PAYPAL_PERSONAL_EMAIL_US_2,
	password: process.env.PAYPAL_PERSONAL_PASS_US_2,
};

const usaVaulting: PayPalAccount = {
	email: process.env.PAYPAL_PERSONAL_EMAIL_US_3,
	password: process.env.PAYPAL_PERSONAL_PASS_US_3,
};

const usaSubscription: PayPalAccount = {
	email: process.env.PAYPAL_PERSONAL_EMAIL_US_4,
	password: process.env.PAYPAL_PERSONAL_PASS_US_4,
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
	usa,
	usaRefund,
	usaVaulting,
	usaSubscription,
	germany,
	mexico,
};
