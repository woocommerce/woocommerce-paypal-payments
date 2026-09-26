/**
 * Prices the product on display for Pay Later messaging, without asking the server.
 *
 * The message's opening amount already arrives priced by the server, so the only
 * thing missing is what happens when the shopper changes the quantity or picks a
 * variation. Reading those from the form answers it instantly and, unlike the
 * cart-simulation endpoint, costs no request.
 *
 * What it gives up: the shop's own display setting decides whether a variation
 * price carries tax, so on a shop that displays prices excluding tax the figure
 * can move once a variation is chosen. A message is an illustration of an
 * instalment, not a quote, and the alternative was a request per keystroke.
 *
 * @package
 */

import { productForm } from "../endpointsAdapter";
import { hasJQuery } from "../utils/api";

/**
 * How long to coalesce form changes before re-pricing.
 *
 * Short enough to read as instant, long enough that holding the quantity
 * stepper re-prices once rather than per repeat.
 */
const REPRICE_DEBOUNCE_MS = 150;

/**
 * The quantity currently entered, as a positive integer.
 *
 * @param {HTMLElement} form - The product form.
 * @return {number} The quantity; 1 when the field is absent or unusable.
 */
function quantityIn(form) {
    const raw = parseInt(form.querySelector('[name="quantity"]')?.value, 10);

    return isNaN(raw) || raw < 1 ? 1 : raw;
}

/**
 * Formats a total the way the message element expects it.
 *
 * @param {number} unit     - The unit price.
 * @param {number} quantity - The quantity.
 * @return {string} The total as a decimal string, or '' when not priceable.
 */
function total(unit, quantity) {
    if (!isFinite(unit) || unit <= 0) {
        return "";
    }

    return (unit * quantity).toFixed(2);
}

/**
 * Re-prices the message as the shopper changes the product form.
 *
 * The variation price is tracked separately from the seed: WooCommerce reports
 * it through `found_variation` and withdraws it through `reset_data`, and until
 * one is chosen the seed is the only price there is.
 *
 * @param {Object}                   config   - The wc_ppcp_sdk_v6 config object.
 * @param {(amount: string) => void} onChange - Called with each new amount.
 * @return {Function} Stops watching.
 */
export function watchProductAmount(config, onChange) {
    const form = productForm();
    if (!form) {
        return () => {};
    }

    const seed = parseFloat(config.messages?.amount);
    let unit = seed;
    let timer = null;
    let last = "";

    const reprice = () => {
        const amount = total(unit, quantityIn(form));

        if (amount && amount !== last) {
            last = amount;
            onChange(amount);
        }
    };

    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(reprice, REPRICE_DEBOUNCE_MS);
    };

    form.addEventListener("change", schedule);
    form.addEventListener("input", schedule);

    if (hasJQuery()) {
        jQuery(form).on("found_variation", (event, variation) => {
            const price = parseFloat(variation?.display_price);
            unit = isNaN(price) ? seed : price;
            schedule();
        });

        jQuery(form).on("reset_data", () => {
            unit = seed;
            schedule();
        });
    }

    return () => {
        clearTimeout(timer);
        form.removeEventListener("change", schedule);
        form.removeEventListener("input", schedule);

        if (hasJQuery()) {
            jQuery(form).off("found_variation reset_data");
        }
    };
}
