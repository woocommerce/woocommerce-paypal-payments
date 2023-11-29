import {setVisible} from "../Helper/Hiding";

class MessagesBootstrap {
    constructor(gateway, messageRenderer) {
        this.gateway = gateway;
        this.renderer = messageRenderer;
        this.lastAmount = this.gateway.messages.amount;
    }

    init() {
        jQuery(document.body).on('ppcp_cart_rendered ppcp_checkout_rendered', () => {
            this.render();
        });
        jQuery(document.body).on('ppcp_script_data_changed', (e, data) => {
            this.gateway = data;

            this.render();
        });
        jQuery(document.body).on('ppcp_cart_total_updated ppcp_checkout_total_updated ppcp_product_total_updated', (e, amount) => {
            if (this.lastAmount !== amount) {
                this.lastAmount = amount;

                this.render();
            }
        });

        this.render();
    }

    shouldShow() {
        if (this.gateway.messages.is_hidden === true) {
            return false;
        }

        const eventData = {result: true}
        jQuery(document.body).trigger('ppcp_should_show_messages', [eventData]);
        return eventData.result;
    }

    shouldRender() {
        return this.shouldShow() && this.renderer.shouldRender();
    }

    render() {
        setVisible(this.gateway.messages.wrapper, this.shouldShow());

        if (!this.shouldRender()) {
            return;
        }

        this.renderer.renderWithAmount(this.lastAmount);
    }
}

export default MessagesBootstrap;
