/**
 * Internal dependencies
 */
import { PcpMerchant } from './types';

const invalid: PcpMerchant = {
	email: '123sb-vzlb326615278@business.example.com',
	password: '123-*Gv5z#X',
	client_id:
		'123AV7C3agl0lCTUEi4gm-5Ku9vagoqOxzKQoc9BIvirXGr5lRrbX3TyxOFzHWTTUXs74BI_XkK3C5LemHZ',
	client_secret:
		'123EFzI8FCerbL8qvMs0baJiVAqvA4SwXka3WM-WWE-o0c6b2acaGu_Q7a4n1nEGQf2-dnCgtmKLgm0AXmC',
	account_id: '123MQEBC2LND7J3L',
};
const germany: PcpMerchant = {
	email: 'sb-vzlb326615278@business.example.com',
	password: '-*Gv5z#X',
	client_id:
		'AV7C3agl0lCTUEi4gm-5Ku9vagoqOxzKQoc9BIvirXGr5lRrbX3TyxOFzHWTTUXs74BI_XkK3C5LemHZ',
	client_secret:
		'EFzI8FCerbL8qvMs0baJiVAqvA4SwXka3WM-WWE-o0c6b2acaGu_Q7a4n1nEGQf2-dnCgtmKLgm0AXmC',
	account_id: 'MQEBC2LND7J3L',
};
const usa: PcpMerchant = {
	email: 'sb-hskbh29881597@business.example.com',
	password: '',
	client_id:
		'AUuFjwA2QOosiCIaE9etgEq3GN8R1EfEWUAJ1NhuZ6LW6z0-TAJRRmTyO3vJof5dFKVJNHfer8k83eDc',
	client_secret:
		'EIXJLpKYTzPF4bS5ZKZYxO47t99i52OBBuQ_3JMpuV-G_SUnoWg0YHpZxa_JSxdlmzbn8AGs7_5pGB5R',
	account_id: 'RRPUW6YXX22W2',
};

// const usa: PcpMerchant = {
//   email: 'sb-1vqws26578261@business.example.com',
//   password: 'xc\'#0\'Xf',
//   client_id: 'ASld_H2Yc6Fpkh6Q0NZuQvckyAjAfWfm_kW-IpROgYPUhpsAxU6KCYRcEw1sf60n6xULzj4K_n_Jg_Y5',
//   client_secret: 'EJ88I1fmwA7QkGvkkuYC4z1_R_M6ZfV511OpRDPI61D6TuAaCm9rULpr-wxqeVTKJfZV1Zzszj9XtDVR',
//   account_id: 'QTGBXV4YCQLD6'
// };

const mexico: PcpMerchant = {
	email: 'sb-zafdx26915775@business.example.com',
	password: 'S*j$Sty5',
	client_id:
		'AVDHMoK8jjBdN1aHHU1KhxBuqX60dBVDSGDxMcUDT5KsEm2ukaSoAah6B7BDQKSrUNO01tlus4aZMDqW',
	client_secret:
		'ENTGiM-woqy7YtzmOTbvAjz7KXsfTkaovkIua9dUB3uVcXjMewUsY1vXovfLnIDiE3oTj2CBnpqd6nCg',
	account_id: 'Q5UMB8J7HH6DN',
};

export const merchants: {
	[ key: string ]: PcpMerchant;
} = {
	invalid,
	germany,
	usa,
	mexico,
};
