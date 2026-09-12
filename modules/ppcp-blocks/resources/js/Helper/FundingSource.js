/**
 * Funding sources that expand an inline form inside the Smart Buttons iframe
 * instead of opening a popup or app.
 *
 * Calling WooCommerce Blocks' express `onClick()` marks checkout as processing
 * and applies `pointer-events: none` to the express payment area. For these
 * sources that blocks interaction with the form fields the shopper still needs
 * to complete, so signaling must wait until order creation starts.
 *
 * @param {string|undefined} fundingSource
 * @return {boolean}
 */
export const isInlineExpressFundingSource = ( fundingSource ) =>
	fundingSource === 'card';
