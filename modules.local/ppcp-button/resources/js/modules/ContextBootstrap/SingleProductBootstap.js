import ErrorHandler from '../ErrorHandler';
import UpdateCart from "../Helper/UpdateCart";
import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";

class SingleProductBootstap {
    constructor(gateway, renderer) {
        this.gateway = gateway;
        this.renderer = renderer;
    }

    init() {
        if (!this.shouldRender()) {
           this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            return;
        }

        this.render();
    }

    shouldRender() {
        if (document.querySelector('form.cart') === null) {
            return false;
        }

        return true;
    }

    render() {
        const actionHandler = new SingleProductActionHandler(
            this.gateway,
            new UpdateCart(
                this.gateway.ajax.change_cart.endpoint,
                this.gateway.ajax.change_cart.nonce,
            ),
            () => {
                this.renderer.showButtons(this.gateway.button.wrapper);
                this.renderer.showButtons(this.gateway.hosted_fields.wrapper);
            },
            () => {
                this.renderer.hideButtons(this.gateway.button.wrapper);
                this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            },
            document.querySelector('form.cart'),
            new ErrorHandler(),
        );

        this.renderer.render(
            this.gateway.button.wrapper,
            this.gateway.hosted_fields.wrapper,
            actionHandler.configuration(),
        );
    }
}

export default SingleProductBootstap;