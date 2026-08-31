/**
 * Navigation seam: window.location is not mockable under jsdom, so redirects
 * go through this indirection to stay unit-testable.
 *
 * @package
 */
export const navigation = {
	assign: ( url ) => window.location.assign( url ),
};
