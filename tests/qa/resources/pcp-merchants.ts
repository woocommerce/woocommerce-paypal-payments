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

const italy: Pcp.Merchant = {
	email: process.env.MERCHANT_IT_EMAIL,
	client_id: process.env.MERCHANT_IT_CLIENT_ID,
	client_secret: process.env.MERCHANT_IT_CLIENT_SECRET,
	account_id: process.env.MERCHANT_IT_ACCOUNT_ID,
};

const uk: Pcp.Merchant = {
	email: process.env.MERCHANT_GB_EMAIL,
	client_id: process.env.MERCHANT_GB_CLIENT_ID,
	client_secret: process.env.MERCHANT_GB_CLIENT_SECRET,
	account_id: process.env.MERCHANT_GB_ACCOUNT_ID,
};

const france: Pcp.Merchant = {
	email: process.env.MERCHANT_FR_EMAIL,
	client_id: process.env.MERCHANT_FR_CLIENT_ID,
	client_secret: process.env.MERCHANT_FR_CLIENT_SECRET,
	account_id: process.env.MERCHANT_FR_ACCOUNT_ID,
};

const australia: Pcp.Merchant = {
	email: process.env.MERCHANT_AU_EMAIL,
	client_id: process.env.MERCHANT_AU_CLIENT_ID,
	client_secret: process.env.MERCHANT_AU_CLIENT_SECRET,
	account_id: process.env.MERCHANT_AU_ACCOUNT_ID,
};

const spain: Pcp.Merchant = {
	email: process.env.MERCHANT_ES_EMAIL,
	client_id: process.env.MERCHANT_ES_CLIENT_ID,
	client_secret: process.env.MERCHANT_ES_CLIENT_SECRET,
	account_id: process.env.MERCHANT_ES_ACCOUNT_ID,
};

const canada: Pcp.Merchant = {
	email: process.env.MERCHANT_CA_EMAIL,
	client_id: process.env.MERCHANT_CA_CLIENT_ID,
	client_secret: process.env.MERCHANT_CA_CLIENT_SECRET,
	account_id: process.env.MERCHANT_CA_ACCOUNT_ID,
};

export const merchants: {
	[ key: string ]: Pcp.Merchant;
} = {
	invalid,
	usa,
	usaNoRef,
	germany,
	mexico,
	italy,
	uk,
	france,
	australia,
	spain,
	canada,
};
