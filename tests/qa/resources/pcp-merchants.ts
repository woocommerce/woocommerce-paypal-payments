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
	email: 'new-us-3wbh337372180@business.example.com',
	//password: '3!G4!m(x',
	client_id:
		'AZC4b9RPfnq3xtiFZ-_3xDsgPvQwQdNPtVqYXBRUJBpHSC3Qffli97AOYlfbb4Ej27WApTOtGJwv_V9E',
	client_secret:
		'EPKkYlxrQcl7SS-qHzijnfLD0W7E5i0pNUdaNJqk3t1325F2GboiuCrwrF6q61Iq5sn3J_aTdc4TzoRM',
	account_id: '3GZZS42NRDTMY',
};

export const merchants: {
	[ key: string ]: Pcp.Merchant;
} = {
	invalid,
	usa,
};
