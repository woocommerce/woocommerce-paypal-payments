import buttonModuleWatcher from "../../../ppcp-button/resources/js/modules/ButtonModuleWatcher";
import ApplepayButton from "./ApplepayButton";

class ApplepayManager {

    constructor(buttonConfig, ppcpConfig) {

        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;
        this.ApplePayConfig = null;
        this.buttons = [];

        buttonModuleWatcher.watchContextBootstrap((bootstrap) => {
            const button = new ApplepayButton(
                bootstrap.context,
                bootstrap.handler,
                buttonConfig,
                ppcpConfig,
            );

            this.buttons.push(button);

            if (this.ApplePayConfig) {
                button.init(this.ApplePayConfig);
            }
        });
    }

    init() {
        (async () => {
            await this.config();
            for (const button of this.buttons) {
                button.init(this.ApplePayConfig);
            }
        })();
    }

    reinit() {
        for (const button of this.buttons) {
            button.reinit();
        }
    }

    /**
     * Gets ApplePay configuration of the PayPal merchant.
     * @returns {Promise<null>}
     */
    async config() {
        this.ApplePayConfig = await paypal.Applepay().config();
        return this.ApplePayConfig;
    }

}

export default ApplepayManager;
