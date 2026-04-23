/**
 * Expected "Learn more" and fee links on onboarding initial page.
 * Must stay in sync with modules/ppcp-settings/resources/js/utils/countryInfoLinks.js
 * (learnMoreLinks). WooCommerce country code (e.g. US:AZ) maps to store country (US) in the app.
 * Each link has url and title (prod page title, for reference).
 */
export interface LearnMoreLink {
	url: string;
	/** Expected page title from production (for reference). */
	title: string;
}

export const learnMoreLinksByCountry: Record<
	string,
	{
		testTitle: string;
		wooCommerceCountryCode: string;
		links: LearnMoreLink[];
	}
> = {
	US: {
		testTitle:
			'PCP-4327 | Settings - United States - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'US:AZ',
		links: [
			{
				url: 'https://www.paypal.com/us/business/accept-payments/checkout',
				title: 'PayPal Checkout Solutions for Businesses | PayPal US',
			},
			{
				url: 'https://www.paypal.com/us/business/accept-payments/checkout/installments',
				title: 'Installment Payments with PayPal Pay Later | PayPal US',
			},
			{
				url: 'https://www.paypal.com/us/enterprise/payment-processing/accept-venmo',
				title: 'Venmo for Business | Accept Venmo Payments | PayPal US',
			},
			{
				url: 'https://www.paypal.com/us/digital-wallet/manage-money/crypto',
				title: 'Buy and Sell Crypto | Cryptocurrency Wallet | PayPal US',
			},
			{
				url: 'https://www.paypal.com/us/business/accept-payments/checkout/integration#expanded-checkout',
				title: 'PayPal Checkout Solutions for Businesses | PayPal US',
			},
			{
				url: 'https://www.paypal.com/us/enterprise/payment-processing/guest-checkout',
				title: 'Guest Checkout for Your Business with Fastlane | PayPal US',
			},
			{
				url: 'https://www.paypal.com/us/business/paypal-business-fees',
				title: 'Fees | Merchant and Business | PayPal US',
			},
		],
	},
	CA: {
		testTitle:
			'PCP-4328 | Settings - Canada - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'CA:ON',
		links: [
			{
				url: 'https://www.paypal.com/ca/business/accept-payments/checkout',
				title: 'PayPal Checkout: Custom Checkout Integration  | PayPal CA',
			},
			{
				url: 'https://www.paypal.com/ca/business/paypal-business-fees',
				title: 'Fees | Merchant and Business | PayPal CA',
			},
		],
	},
	GB: {
		testTitle:
			'PCP-4329 | Settings - United Kingdom - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'GB',
		links: [
			{
				url: 'https://www.paypal.com/uk/business/accept-payments/checkout',
				title: 'PayPal Checkout | Online Checkout Solutions | PayPal UK',
			},
			{
				url: 'https://www.paypal.com/uk/business/accept-payments/checkout/installments',
				title: 'Accept Instalment Payments | Pay in 3 for Business | PayPal UK',
			},
			{
				url: 'https://www.paypal.com/uk/business/paypal-business-fees',
				title: 'PayPal Merchant Fees - Seller Fees | PayPal UK',
			},
		],
	},
	FR: {
		testTitle:
			'PCP-4331 | Settings - France - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'FR',
		links: [
			{
				url: 'https://www.paypal.com/fr/business/accept-payments/checkout',
				title: 'PayPal Checkout | Solutions de commande en ligne | PayPal FR',
			},
			{
				url: 'https://www.paypal.com/fr/business/accept-payments/checkout/installments',
				title: 'Acceptez les Paiements Échelonnés | Paiement en 4X pour Entreprises | PayPal FR | PayPal FR',
			},
			{
				url: 'https://www.paypal.com/fr/business/paypal-business-fees',
				title: 'Tarifs - Frais PayPal pour les marchands | PayPal FR',
			},
		],
	},
	ES: {
		testTitle:
			'PCP-4332 | Settings - Spain - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'ES:B',
		links: [
			{
				url: 'https://www.paypal.com/es/business/accept-payments/checkout',
				title: 'Checkout de PayPal | Soluciones de checkout online | PayPal ES',
			},
			{
				url: 'https://www.paypal.com/es/business/accept-payments/checkout/installments',
				title: 'Acepta pago a plazos | Paga a plazos para empresas | PayPal ES',
			},
			{
				url: 'https://www.paypal.com/es/business/paypal-business-fees',
				title: 'Tarifas de Vendedor PayPal | PayPal ES',
			},
		],
	},
	IT: {
		testTitle:
			'PCP-4333 | Settings - Italy - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'IT:RM',
		links: [
			{
				url: 'https://www.paypal.com/it/business/accept-payments/checkout',
				title: 'PayPal Checkout | Soluzioni di Checkout Online | PayPal IT',
			},
			{
				url: 'https://www.paypal.com/it/business/accept-payments/checkout/installments',
				title: 'Accetta pagamenti a rate | Paga in rate per aziende | PayPal IT',
			},
			{
				url: 'https://www.paypal.com/it/business/paypal-business-fees',
				title: 'Tariffe per i Venditori PayPal | PayPal IT',
			},
		],
	},
	DE: {
		testTitle:
			'PCP-4334 | Settings - Germany - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'DE:DE-BE',
		links: [
			{
				url: 'https://www.paypal.com/de/business/accept-payments/checkout',
				title: 'PayPal Checkout | Online Checkout Lösung | PayPal DE',
			},
			{
				url: 'https://www.paypal.com/de/business/accept-payments/checkout/installments',
				title: 'Ratenzahlung | Später Bezahlen | PayPal DE',
			},
			{
				url: 'https://www.paypal.com/de/business/paypal-business-fees',
				title: 'PayPal Händler - und Verkäufergebühren | PayPal DE',
			},
		],
	},
	AU: {
		testTitle:
			'PCP-4335 | Settings - Australia - Onboarding - Links Learn more and link for fees in footer',
		wooCommerceCountryCode: 'AU:NSW',
		links: [
			{
				url: 'https://www.paypal.com/au/business/accept-payments/checkout',
				title: 'PayPal Checkout | Online Checkout Solutions | PayPal AU',
			},
			{
				url: 'https://www.paypal.com/au/business/accept-payments/checkout/installments',
				title: 'Instalment Payments with PayPal Pay Later | PayPal AU',
			},
			{
				url: 'https://www.paypal.com/au/business/paypal-business-fees',
				title: 'PayPal Fees for Sellers (Business) | PayPal AU',
			},
		],
	},
};
