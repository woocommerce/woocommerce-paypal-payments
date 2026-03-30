/**
 * Internal dependencies
 */
import { payments, orders, guests, ShopOrder } from '../../../../resources';

const { fastlaneGary, fastlaneRyan } = payments;

export const fastlaneCheckout: ShopOrder[] = [
	{
		title: 'PCP-4005 | Transaction - Checkout - Fastlane - Gary - Default order @Critical @Smoke',
		...orders.default,
		payment: fastlaneGary,
		customer: guests.usaFastlaneGary,
	},
	{
		title: 'PCP-4578 | Transaction - Checkout - Fastlane - Ryan - Default order @Critical @Smoke',
		...orders.default,
		payment: fastlaneRyan,
		customer: guests.usaFastlaneRyan,
	},
];
