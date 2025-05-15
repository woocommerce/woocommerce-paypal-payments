/**
 * Internal dependencies
 */
import { Pcp } from './types';

const invalid: Pcp.Merchant = {
	email: '123sb-vzlb326615278@business.example.com',
	client_id:
		'123AV7C3agl0lCTUEi4gm-5Ku9vagoqOxzKQoc9BIvirXGr5lRrbX3TyxOFzHWTTUXs74BI_XkK3C5LemHZ',
	client_secret:
		'123EFzI8FCerbL8qvMs0baJiVAqvA4SwXka3WM-WWE-o0c6b2acaGu_Q7a4n1nEGQf2-dnCgtmKLgm0AXmC',
	account_id: '123MQEBC2LND7J3L',
};

const usa: Pcp.Merchant = {
	email: process.env.MERCHANT_USA_EMAIL,
	client_id: process.env.MERCHANT_USA_CLIENT_ID,
	client_secret: process.env.MERCHANT_USA_CLIENT_SECRET,
	account_id: process.env.MERCHANT_USA_ACCOUNT_ID,
};

export const merchants: {
	[ key: string ]: Pcp.Merchant;
} = {
	invalid,
	usa,
};
