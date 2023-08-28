import SingleProductHandler from "./SingleProductHandler";
import CartHandler from "./CartHandler";
import CheckoutHandler from "./CheckoutHandler";
import CartBlockHandler from "./CartBlockHandler";
import CheckoutBlockHandler from "./CheckoutBlockHandler";

class ContextHandlerFactory {

    static create(context, buttonConfig, ppcpConfig) {
        switch (context) {
            case 'product':
                return new SingleProductHandler(buttonConfig, ppcpConfig);
            case 'cart':
                return new CartHandler(buttonConfig, ppcpConfig);
            case 'checkout':
                return new CheckoutHandler(buttonConfig, ppcpConfig);
            case 'pay-now':
                // todo
                return null;
            case 'mini-cart':
                // todo
                return null;
            case 'cart-block':
                return new CartBlockHandler(buttonConfig, ppcpConfig);
            case 'checkout-block':
                return new CheckoutBlockHandler(buttonConfig, ppcpConfig);
        }
    }
}

export default ContextHandlerFactory;
