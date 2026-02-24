/**
 * Internal dependencies
 */
import { Pcp } from '../../../resources';

/**
 * One variant per location (per plan).
 */
export const payLaterMessagingData = {
	checkoutLocationSettings: {
		'Product page': {
			location: 'Product page' as Pcp.Admin.Plm.Location,
			settings: [
				{
					logoType: 'Full logo' as Pcp.Admin.Plm.LogoType,
					textColor: 'Black / Blue logo' as Pcp.Admin.Plm.TextColor,
					logoPosition: 'Left' as Pcp.Admin.Plm.LogoPosition,
					textSize: 'Medium' as Pcp.Admin.Plm.TextSize,
				},
			],
		},
		Cart: {
			location: 'Cart' as Pcp.Admin.Plm.Location,
			settings: [
				{
					logoType: 'Monogram' as Pcp.Admin.Plm.LogoType,
					textColor: 'Black / Blue logo' as Pcp.Admin.Plm.TextColor,
					textSize: 'Medium' as Pcp.Admin.Plm.TextSize,
				},
			],
		},
		Checkout: {
			location: 'Checkout' as Pcp.Admin.Plm.Location,
			settings: [
				{
					logoType: 'Full logo' as Pcp.Admin.Plm.LogoType,
					textColor: 'Black / Blue logo' as Pcp.Admin.Plm.TextColor,
					logoPosition: 'Right' as Pcp.Admin.Plm.LogoPosition,
					textSize: 'Medium' as Pcp.Admin.Plm.TextSize,
				},
			],
		},
	},
	bannerLocationSettings: {
		Home: {
			location: 'Home' as Pcp.Admin.Plm.Location,
			settings: [
				{
					bannerColor: 'Blue' as Pcp.Admin.Plm.BannerColor,
					bannerSize: '20 x 1' as Pcp.Admin.Plm.BannerSize,
				},
			],
		},
		Shop: {
			location: 'Shop' as Pcp.Admin.Plm.Location,
			settings: [
				{
					bannerColor: 'White' as Pcp.Admin.Plm.BannerColor,
					bannerSize: '20 x 1' as Pcp.Admin.Plm.BannerSize,
				},
			],
		},
	},
};
