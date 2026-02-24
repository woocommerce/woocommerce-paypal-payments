/**
 * Internal dependencies
 */
import { PcpMerchant } from './types';

const invalid: PcpMerchant = {
	email: process.env.MERCHANT_INVALID_EMAIL,
	client_id: process.env.MERCHANT_INVALID_CLIENT_ID,
	client_secret: process.env.MERCHANT_INVALID_CLIENT_SECRET,
	account_id: process.env.MERCHANT_INVALID_ACCOUNT_ID,
	password: process.env.MERCHANT_INVALID_PASS,
};

const germany: PcpMerchant = {
	email: process.env.MERCHANT_DE_EMAIL,
	client_id: process.env.MERCHANT_DE_CLIENT_ID,
	client_secret: process.env.MERCHANT_DE_CLIENT_SECRET,
	account_id: process.env.MERCHANT_DE_ACCOUNT_ID,
	password: process.env.MERCHANT_DE_PASS,
};

const usa: PcpMerchant = {
	email: process.env.MERCHANT_USA_EMAIL,
	client_id: process.env.MERCHANT_USA_CLIENT_ID,
	client_secret: process.env.MERCHANT_USA_CLIENT_SECRET,
	account_id: process.env.MERCHANT_USA_ACCOUNT_ID,
	password: process.env.MERCHANT_USA_PASS,
};

const mexico: PcpMerchant = {
	email: process.env.MERCHANT_MX_EMAIL,
	client_id: process.env.MERCHANT_MX_CLIENT_ID,
	client_secret: process.env.MERCHANT_MX_CLIENT_SECRET,
	account_id: process.env.MERCHANT_MX_ACCOUNT_ID,
	password: process.env.MERCHANT_MX_PASS,
};

export const merchants: {
	[ key: string ]: PcpMerchant;
} = {
	invalid,
	germany,
	usa,
	mexico,
};
