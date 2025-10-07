/**
 * Internal dependencies
 */
import { Pcp } from '../../../resources';

const checkoutLocations: Pcp.Admin.Plm.Location[] = [
	'Product page',
	'Cart',
	'Checkout',
];
const logoTypes: Pcp.Admin.Plm.LogoType[] = [
	'Full Logo',
	'Monogram',
	'Inline',
	'Message only',
];
const textColors: Pcp.Admin.Plm.TextColor[] = [
	'Black / Blue logo',
	'White / White logo',
	'Monochrome',
	'Black / Gray logo',
];
const logoPositions: Pcp.Admin.Plm.LogoPosition[] = [ 'Left', 'Right', 'Top' ];
const textSizes: Pcp.Admin.Plm.TextSize[] = [ 'Small', 'Medium', 'Large' ];

const bannerLocations: Pcp.Admin.Plm.Location[] = [ 'Home', 'Shop' ];
const bannerColors: Pcp.Admin.Plm.BannerColor[] = [
	'Blue',
	'Black',
	'White',
	'White (no border)',
];
const bannerSizes: Pcp.Admin.Plm.BannerSize[] = [ '20 x 1', '8 x 1' ];

/**
 * Builds data combinations for pages with payment buttons (Procuct, Cart, Checkout) in the following format:
 * {
 *     "Product page": {
 *         location: "Product page",
 *         settings: {
 *             "logoType": "Full Logo",
 *             "textColor": "Black / Blue logo",
 *             "logoPosition": "Left",
 *             "textSize": "Small",
 *         }
 *     },
 *     ...
 * }
 */
const checkoutLocationTests = Object.fromEntries(
	checkoutLocations.map( ( location ) => [
		location,
		{
			location,
			settings: logoTypes
				.map( ( logoType ) => {
					const settings = {
						logoType,
						textColor: 'Black / Blue logo', // Default, replaced later
						logoPosition: 'Left' as Pcp.Admin.Plm.LogoPosition,
						textSize: 'Medium' as Pcp.Admin.Plm.TextSize,
					};

					if ( logoType === 'Full Logo' ) {
						return textColors.flatMap( ( textColor ) =>
							logoPositions.flatMap( ( logoPosition ) =>
								textSizes.map( ( textSize ) => ( {
									...settings,
									textColor,
									logoPosition,
									textSize,
								} ) )
							)
						);
					}

					return textColors.flatMap( ( textColor ) =>
						textSizes.map( ( textSize ) => ( {
							...settings,
							textColor,
							textSize,
						} ) )
					);
				} )
				.flat(),
		},
	] )
);

/**
 * Builds data combinations for pages with banners (Home, Shop) in the following format:
 * {
 *     "Home": {
 *         location: "Home",
 *         settings: {
 *             "bannerColor": "Blue",
 *             "bannerSize": "20 x 1",
 *         }
 *     },
 *     ...
 * }
 */
const bannerLocationTests = Object.fromEntries(
	bannerLocations.map( ( location ) => [
		location,
		{
			location,
			settings: bannerColors.flatMap( ( bannerColor ) =>
				bannerSizes.map( ( bannerSize ) => ( {
					bannerColor,
					bannerSize,
				} ) )
			),
		},
	] )
);

export const payLaterMessagingData = {
	checkoutLocationSettings: checkoutLocationTests,
	bannerLocationSettings: bannerLocationTests,
};
