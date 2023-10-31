import buttonModuleWatcher from "../../../ppcp-button/resources/js/modules/ButtonModuleWatcher";
import GooglepayButton from "./GooglepayButton";

class GooglepayManager {

    constructor(buttonConfig, ppcpConfig) {

        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;
        this.googlePayConfig = null;

        this.buttons = [];

        buttonModuleWatcher.watchContextBootstrap((bootstrap) => {
            const button = new GooglepayButton(
                bootstrap.context,
                bootstrap.handler,
                buttonConfig,
                ppcpConfig,
            );

            this.buttons.push(button);

            if (this.googlePayConfig) {
                button.init(this.googlePayConfig);
            }
        });
    }

    init() {
        (async () => {
            // Gets GooglePay configuration of the PayPal merchant.
            this.googlePayConfig = await paypal.Googlepay().config();

            for (const button of this.buttons) {
                button.init(this.googlePayConfig);
            }
        })();
    }

    reinit() {
        for (const button of this.buttons) {
            button.reinit();
        }
    }

}

export default GooglepayManager;
