import AxoManager from "./AxoManager";
import {loadPaypalScript} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";

const bootstrap = (jQuery) => {
    const axo = new AxoManager(jQuery);
}

(function ({
   ppcpConfig,
   jQuery
}) {

    document.addEventListener(
        'DOMContentLoaded',
        () => {
            if (!typeof (PayPalCommerceGateway)) {
                console.error('AXO could not be configured.');
                return;
            }

            // Load PayPal
            loadPaypalScript(ppcpConfig, () => {
                bootstrap(jQuery);
            });
        },
    );

})({
    ppcpConfig: window.PayPalCommerceGateway,
    jQuery: window.jQuery
});
