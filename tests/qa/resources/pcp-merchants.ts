/**
 * Internal dependencies
 */
import { Pcp } from './types';

const invalid: Pcp.Merchant = {
	email: process.env.MERCHANT_INVALID_EMAIL,
	client_id: process.env.MERCHANT_INVALID_CLIENT_ID,
	client_secret: process.env.MERCHANT_INVALID_CLIENT_SECRET,
	account_id: process.env.MERCHANT_INVALID_ACCOUNT_ID,
};

const usa: Pcp.Merchant = {
	email: process.env.MERCHANT_USA_EMAIL,
	client_id: process.env.MERCHANT_USA_CLIENT_ID,
	client_secret: process.env.MERCHANT_USA_CLIENT_SECRET,
	account_id: process.env.MERCHANT_USA_ACCOUNT_ID,
};

const germany: Pcp.Merchant = {
	email: process.env.MERCHANT_DE_EMAIL,
	client_id: process.env.MERCHANT_DE_CLIENT_ID,
	client_secret: process.env.MERCHANT_DE_CLIENT_SECRET,
	account_id: process.env.MERCHANT_DE_ACCOUNT_ID,
};

const mexico: Pcp.Merchant = {
	email: process.env.MERCHANT_MX_EMAIL,
	client_id: process.env.MERCHANT_MX_CLIENT_ID,
	client_secret: process.env.MERCHANT_MX_CLIENT_SECRET,
	account_id: process.env.MERCHANT_MX_ACCOUNT_ID,
};

const usaNoRef: Pcp.Merchant = {
	email: process.env.MERCHANT_USA_NOREF_EMAIL,
	client_id: process.env.MERCHANT_USA_NOREF_CLIENT_ID,
	client_secret: process.env.MERCHANT_USA_NOREF_CLIENT_SECRET,
	account_id: process.env.MERCHANT_USA_NOREF_ACCOUNT_ID,
};

export const merchants: {
	[ key: string ]: Pcp.Merchant;
} = {
	invalid,
	usa,
	usaNoRef,
	germany,
	mexico,
};
