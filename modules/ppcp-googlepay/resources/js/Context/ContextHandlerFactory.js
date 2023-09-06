import SingleProductHandler from "./SingleProductHandler";
import CartHandler from "./CartHandler";
import CheckoutHandler from "./CheckoutHandler";
import CartBlockHandler from "./CartBlockHandler";
import CheckoutBlockHandler from "./CheckoutBlockHandler";
import MiniCartHandler from "./MiniCartHandler";

class ContextHandlerFactory {

    static create(context, buttonConfig, ppcpConfig, externalActionHandler) {
        switch (context) {
            case 'product':
                return new SingleProductHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'cart':
                return new CartHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'checkout':
            case 'pay-now': // same as checkout
                return new CheckoutHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'mini-cart':
                return new MiniCartHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'cart-block':
                return new CartBlockHandler(buttonConfig, ppcpConfig, externalActionHandler);
            case 'checkout-block':
                return new CheckoutBlockHandler(buttonConfig, ppcpConfig, externalActionHandler);
        }
    }
}

export default ContextHandlerFactory;
