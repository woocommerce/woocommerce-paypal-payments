export type PayPalAccount = {
	email: string;
	password: string;
};

export namespace Pcp {
	export type Merchant = {
		email: string;
		client_id: string;
		client_secret: string;
		account_id: string;
	};

	export type GatewayId =
		| 'ppcp-gateway'
		| 'pay-later'
		| 'venmo'
		| 'ppcp-applepay'
		| 'ppcp-card-button-gateway'
		| 'ppcp-credit-card-gateway'
		| 'ppcp-axo-gateway'
		| 'ppcp-bancontact'
		| 'ppcp-blik'
		| 'ppcp-eps'
		| 'ppcp-googlepay'
		| 'ppcp-ideal'
		| 'ppcp-multibanco'
		| 'ppcp-mybank'
		| 'ppcp-oxxo-gateway'
		| 'ppcp-p24'
		| 'ppcp-pay-upon-invoice-gateway'
		| 'ppcp-trustly';
	
	export type GatewayOptions = {
		fastlaneCardholderName?: boolean;
		fastlaneDisplayWatermark?: boolean;
		paypalShowLogo?: boolean;
		threeDSecure?:
			| 'no-3d-secure'
			| 'only-required-3d-secure'
			| 'always-3d-secure';
	};

	export type Gateway =
		WooCommerce.PaymentGateway &
		GatewayOptions &
		{
			id?: GatewayId;
			shortcut?: string; // data-funding-source - an attribute of  payment method container on frontend pages
			country?: string;
			currency?: string;
			minAmount?: string;
			maxAmount?: string;
		};

	export type Payment = {
		gateway: Gateway;
		payPalAccount?: PayPalAccount;
		card?: WooCommerce.CreditCard;
		isVaulted?: boolean;
		birthDate?: string;
		useNotVaultedAccount?: PayPalAccount;
		isAuthorized?: boolean;
		saveToAccount?: boolean;
	};
	
	export namespace Api {
		export type PaymentMethods =
			GatewayOptions &
			{ [ key in GatewayId ]?: WooCommerce.PaymentGateway };

		export type Settings = {
			authorizeOnly?: boolean;
			brandName?: string;
			buttonLanguage?: 'en_EN' | 'de_DE' | 'es_ES' | 'it_IT' | ( string & {} );
			captureVirtualOrders?: boolean;
			disabledCards?: ( "mastercard" | "visa" | "amex" | "jcb" | "diners-club" )[];
			enableLogging?: boolean;
			enablePayNow?: boolean;
			invoicePrefix?: string;
			landingPage?: string;
			saveCardDetails?: boolean;
			savePaypalAndVenmo?: boolean;
			softDescriptor?: string;
			subtotalAdjustment?: string;
		}
	}

	export namespace Admin {
		export namespace Onboarding {
			export type StepTitle =
				| 'PayPal Payments'
				| 'Set up store type'
				| 'Select product types'
				| 'Choose checkout options'
				| 'Connect your PayPal account';

			export type AdvancedOptions = {
				enableSandboxMode: boolean;
				enableManualConnection: boolean;
				merchant: Pcp.Merchant;
			};

			export type ProductTypes = {
				enableVirtual: boolean;
				enablePhysicalGoods: boolean;
			};

			export type CheckoutOptions = {
				enableOptionalPaymentMethods: boolean;
			};
		}

		export namespace PaymentMethods {
			export type ThreeDSecure =
				| 'No 3D Secure'
				| 'Only when required'
				| 'Always require 3D Secure';
		}

		export type Settings = {
			invoicePrefix: string;
			// TODO: add other settings
		};

		export namespace Styling {
			export type Location =
				| 'Cart'
				| 'Classic Checkout'
				| 'Express Checkout'
				| 'Mini Cart'
				| 'Product Page';

			export type PaymentMethods =
				| 'PayPal'
				| 'Venmo'
				| 'Pay Later'
				| 'Google Pay'
				| 'Apple Pay';

			export type ButtonLayout = 'Vertical' | 'Horizontal';

			export type ButtonShape = 'Rectangle' | 'Pill';

			export type ButtonLabel =
				| 'Paypal'
				| 'Checkout'
				| 'PayPal Buy Now'
				| 'Pay with PayPal';

			export type ButtonColor =
				| 'Gold (recommended)'
				| 'Blue'
				| 'Silver'
				| 'Black'
				| 'White';

			export type Config = {
				location?: Location;
				enablePaymentMethodsInLocation?: boolean;
				paymentMethods?: PaymentMethods;
				buttonLayout?: ButtonLayout;
				enableTagline?: boolean;
				buttonShape?: ButtonShape;
				buttonLabel?: ButtonLabel;
				buttonColor?: ButtonColor;
			};
		}

		export namespace Plm {
			export type Location =
				| 'Product page'
				| 'Cart'
				| 'Checkout'
				| 'Home'
				| 'Shop';

			export type LogoType =
				| 'Full Logo'
				| 'Monogram'
				| 'Inline'
				| 'Message only';

			export type TextColor =
				| 'Black / Blue logo'
				| 'White / White logo'
				| 'Monochrome'
				| 'Black / Gray logo';

			export type LogoPosition = 'Left' | 'Right' | 'Top';

			export type TextSize = 'Small' | 'Medium' | 'Large';

			export type BannerColor =
				| 'Blue'
				| 'Black'
				| 'White'
				| 'White (no border)';

			export type BannerSize = '20 x 1' | '8 x 1';

			export type PreviewLayout = 'Text' | 'Desktop' | 'Mobile';

			// Checkout locations - pages where checkout can be done with paypal (Products, Carts, Checkouts)
			export type CheckoutLocation = {
				enabled?: boolean;
				logoType?: LogoType;
				logoPosition?: LogoPosition;
				textColor?: TextColor;
				textSize?: TextSize;
			};

			// Banner locations - pages where only banner is displayed (Home, Shop)
			export type BannerLocation = {
				enabled?: boolean;
				bannerColor?: BannerColor;
				bannerSize?: BannerSize;
			};

			export type Config = {
				enabledDarkMode?: boolean;
				product?: CheckoutLocation;
				cart?: CheckoutLocation;
				checkout?: CheckoutLocation;
				home?: BannerLocation;
				shop?: BannerLocation;
				enabledWooCommerceBlock?: boolean; // TODO: check for deprecation
			};
		}

		export type Config = {
			merchant?: Merchant;
			paymentMethods?: Gateway[];
			settings?: Settings;
			styling?: Styling.Config;
			payLaterMessaging?: Plm.Config;
		};
	}
}
