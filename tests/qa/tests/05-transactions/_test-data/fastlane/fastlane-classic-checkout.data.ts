/**
 * Internal dependencies
 */
import { payments, orders, guests } from '../../../../resources';

const { fastlaneGary, fastlaneRyan } = payments;

export const fastlaneClassicCheckout = [
	{
		title: 'PCP-4596 | Transaction - Classic checkout - Fastlane - Gary - Default order',
		...orders.default,
		payment: fastlaneGary,
		customer: guests.usaFastlaneGary,
	},
	{
		title: 'PCP-3079 | Transaction - Classic checkout - Fastlane - Ryan - Default order',
		...orders.default,
		payment: fastlaneRyan,
		customer: guests.usaFastlaneRyan,
	},
];
