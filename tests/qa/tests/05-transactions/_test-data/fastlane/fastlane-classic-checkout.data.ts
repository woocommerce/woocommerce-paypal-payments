/**
 * Internal dependencies
 */
import { payments, orders, guests, ShopOrder } from '../../../../resources';

const { fastlaneGary, fastlaneRyan } = payments;

export const fastlaneClassicCheckout: ShopOrder[] = [
	{
		title: 'PCP-4596 | Transaction - Classic checkout - Fastlane - Gary - Default order @Critical @Smoke',
		...orders.default,
		payment: fastlaneGary,
		customer: guests.usaFastlaneGary,
	},
	{
		title: 'PCP-3079 | Transaction - Classic checkout - Fastlane - Ryan - Default order @Critical @Smoke',
		...orders.default,
		payment: fastlaneRyan,
		customer: guests.usaFastlaneRyan,
	},
];
