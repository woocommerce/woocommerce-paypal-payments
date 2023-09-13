import SingleProductHandler from "./SingleProductHandler";
import CartHandler from "./CartHandler";
import CheckoutHandler from "./CheckoutHandler";
import CartBlockHandler from "./CartBlockHandler";
import CheckoutBlockHandler from "./CheckoutBlockHandler";
import MiniCartHandler from "./MiniCartHandler";

class ContextHandlerFactory {

    static create(context, buttonConfig, ppcpConfig) {
        switch (context) {
            case 'product':
                return new SingleProductHandler(buttonConfig, ppcpConfig);
            case 'cart':
                return new CartHandler(buttonConfig, ppcpConfig);
            case 'checkout':
            case 'pay-now': // same as checkout
                return new CheckoutHandler(buttonConfig, ppcpConfig);
            case 'mini-cart':
                return new MiniCartHandler(buttonConfig, ppcpConfig);
            case 'cart-block':
                return new CartBlockHandler(buttonConfig, ppcpConfig);
            case 'checkout-block':
                return new CheckoutBlockHandler(buttonConfig, ppcpConfig);
        }
    }
}

export default ContextHandlerFactory;
