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
            await this.config();

            for (const button of this.buttons) {
                button.init(this.googlePayConfig);
            }
        })();
    }

    /**
     * Gets GooglePay configuration of the PayPal merchant.
     * @returns {Promise<null>}
     */
    async config() {
        this.googlePayConfig = await paypal.Googlepay().config();
        return this.googlePayConfig;
    }

}

export default GooglepayManager;
