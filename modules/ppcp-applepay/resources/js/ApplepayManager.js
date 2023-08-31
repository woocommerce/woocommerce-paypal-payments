import buttonModuleWatcher from "../../../ppcp-button/resources/js/modules/ButtonModuleWatcher";
import ApplepayButton from "./ApplepayButton";

class ApplepayManager {

    constructor(buttonConfig, ppcpConfig) {

        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;
        this.ApplePayConfig = null;

        this.buttons = [];

        buttonModuleWatcher.watchContextBootstrap((bootstrap) => {
            console.log('ApplepayManager.js: buttonModuleWatcher.watchContextBootstrap', bootstrap)
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
            console.log('ApplepayManager.js: init', this.buttons)
            for (const button of this.buttons) {
                console.log('ApplepayManager.js: init', button)
                button.init(this.ApplePayConfig);
            }
        })();
    }

    /**
     * Gets ApplePay configuration of the PayPal merchant.
     * @returns {Promise<null>}
     */
    async config() {
        this.ApplePayConfig = await paypal.Applepay().config();
        console.log('ApplepayManager.js: config', this.ApplePayConfig)
        return this.ApplePayConfig;
    }

}

export default ApplepayManager;
