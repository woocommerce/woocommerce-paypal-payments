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

	export type Gateway = {
		enabled?: boolean;
		title?: string;
		dataFundingSource?: string; // data-funding-source - an attribute of  payment method container on frontend pages
		slug?: string;
		description?: string;
		paypalShowLogo?: boolean;
		acdc3ds?:
			| 'no-3d-secure'
			| 'only-required-3d-secure'
			| 'always-3d-secure';
		fastlaneDisplayCardholderName?: boolean;
		fastlaneDisplayFastlaneWatermark?: boolean;
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

			export type Config = {
				location: Location;
				// TODO
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
