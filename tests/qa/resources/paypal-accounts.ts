/**
 * Internal dependencies
 */
import { PayPalAccount } from './types';

const germany: PayPalAccount = {
	email: 'sb-pshsb27001797@personal.example.com',
	password: '#D0:c!DO',
};

const usa: PayPalAccount = {
	email: 'sb-tb1aj26722276@personal.example.com',
	password: 'Z9+6Az-G',
};

const mexico: PayPalAccount = {
	email: 'sb-z2t6h26996394@personal.example.com',
	password: 'C-cg>?33',
};

export const payPalAccounts: {
	[ key: string ]: PayPalAccount;
} = {
	germany,
	usa,
	mexico,
};
