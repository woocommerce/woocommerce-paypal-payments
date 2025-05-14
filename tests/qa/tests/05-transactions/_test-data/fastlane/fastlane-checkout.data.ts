/**
 * Internal dependencies
 */
import { payments, orders, guests } from '../../../../resources';

const { fastlaneGary, fastlaneRyan } = payments;

export const fastlaneCheckout = [
	{
		title: 'PCP-4005 | Transaction - Checkout - Fastlane - Gary - Default order',
		...orders.default,
		payment: fastlaneGary,
		customer: guests.usaFastlaneGary,
	},
	{
		title: 'PCP-4578 | Transaction - Checkout - Fastlane - Ryan - Default order',
		...orders.default,
		payment: fastlaneRyan,
		customer: guests.usaFastlaneRyan,
	},
];
