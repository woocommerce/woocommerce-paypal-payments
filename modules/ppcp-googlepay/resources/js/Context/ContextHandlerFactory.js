import SingleProductHandler from "./SingleProductHandler";
import CartHandler from "./CartHandler";
import CheckoutHandler from "./CheckoutHandler";
import CartBlockHandler from "./CartBlockHandler";
import CheckoutBlockHandler from "./CheckoutBlockHandler";
import MiniCartHandler from "./MiniCartHandler";
import PayNowHandler from "./PayNowHandler";
import PreviewHandler from "./PreviewHandler";

class ContextHandlerFactory {

    static create(context, buttonConfig, ppcpConfig, externalActionHandler) {
        switch (context) {
            case 'product':
                return new SingleProductHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'cart':
                return new CartHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'checkout':
                return new CheckoutHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'pay-now':
                return new PayNowHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'mini-cart':
                return new MiniCartHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'cart-block':
                return new CartBlockHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'checkout-block':
                return new CheckoutBlockHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'preview':
                return new PreviewHandler(buttonConfig, ppcpConfig, externalActionHandler);
        }
    }
}

export default ContextHandlerFactory;
