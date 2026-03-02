/**
 * Internal dependencies
 */
import { Pcp } from '../../../resources';

type PlmCheckoutPageData = {
	location: Pcp.Admin.Plm.Location;
	settings: {
		logoType?: Pcp.Admin.Plm.LogoType;
		textColor?: Pcp.Admin.Plm.TextColor;
		logoPosition?: Pcp.Admin.Plm.LogoPosition;
		textSize?: Pcp.Admin.Plm.TextSize;
	}[];
};

type PlmBannerPageData = {
	location: Pcp.Admin.Plm.Location;
	settings: {
		bannerColor?: Pcp.Admin.Plm.BannerColor;
		bannerSize?: Pcp.Admin.Plm.BannerSize;
	}[];
};

/**
 * One variant per location (per plan).
 */
export const payLaterMessagingData: {
	checkoutLocationSettings: Record<string, PlmCheckoutPageData>;
	bannerLocationSettings: Record<string, PlmBannerPageData>;
} = {
	checkoutLocationSettings: {
		'Product page': {
			location: 'Product page',
			settings: [
				{
					logoType: 'Full logo',
					textColor: 'Black / Blue logo',
					logoPosition: 'Left',
					textSize: 'Medium',
				},
			],
		},
		Cart: {
			location: 'Cart',
			settings: [
				{
					logoType: 'Monogram',
					textColor: 'Black / Blue logo',
					textSize: 'Medium',
				},
			],
		},
		Checkout: {
			location: 'Checkout',
			settings: [
				{
					logoType: 'Full logo',
					textColor: 'Black / Blue logo',
					logoPosition: 'Right',
					textSize: 'Medium',
				},
			],
		},
	},
	bannerLocationSettings: {
		Home: {
			location: 'Home',
			settings: [
				{
					bannerColor: 'Blue',
					bannerSize: '20 x 1',
				},
			],
		},
		Shop: {
			location: 'Shop',
			settings: [
				{
					bannerColor: 'White',
					bannerSize: '20 x 1',
				},
			],
		},
	},
};
