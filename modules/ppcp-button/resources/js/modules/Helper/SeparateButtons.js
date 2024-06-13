import widgetBuilder from "./../Renderer/WidgetBuilder";

class SeparateButtons {

    constructor(buttons) {
        this.buttons = buttons;
    }

    init() {
        this.refresh();
    }

    refresh() {
        for (let fundingSource in this.buttons) {
            const button = this.buttons[fundingSource];
            const isFundingEnabled = widgetBuilder.paypal.isFundingEligible(fundingSource);

            const $gatewayBox = jQuery('.payment_method_' + button.id);

            if (isFundingEnabled) {
                $gatewayBox.show();
            } else {
                $gatewayBox.hide();
            }
        }
    }
}

export default SeparateButtons;
