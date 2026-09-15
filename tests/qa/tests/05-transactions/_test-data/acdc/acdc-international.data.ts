/**
 * Internal dependencies
 */
import { guests, merchants, Pcp } from '../../../../resources';

export { guests };

/**
 * Countries covered by the international ACDC shard, keyed to match the
 * `shopSettings`, `customers` and `guests` fixtures from
 * `@inpsyde/playwright-utils`.
 */
export const acdcInternationalCountries: {
	key: string;
	label: string;
	merchant: Pcp.Merchant;
}[] = [
	{ key: 'germany', label: 'Germany', merchant: merchants.germany },
	{ key: 'usa', label: 'USA', merchant: merchants.usa },
	{ key: 'italy', label: 'Italy', merchant: merchants.italy },
	{ key: 'uk', label: 'UK', merchant: merchants.uk },
	{ key: 'france', label: 'France', merchant: merchants.france },
	{ key: 'australia', label: 'Australia', merchant: merchants.australia },
	{ key: 'spain', label: 'Spain', merchant: merchants.spain },
	{ key: 'canada', label: 'Canada', merchant: merchants.canada },
];
