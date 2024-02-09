import AxoManager from "./AxoManager";

const bootstrap = () => {
    const axo = new AxoManager();
}

document.addEventListener(
    'DOMContentLoaded',
    () => {
        if (!typeof (PayPalCommerceGateway)) {
            console.error('PayPal button could not be configured.');
            return;
        }

        bootstrap();
    },
);
