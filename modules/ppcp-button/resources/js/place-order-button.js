import PlaceOrderButtonBootstrap from "./modules/ContextBootstrap/PlaceOrderButtonBootstrap";

document.addEventListener(
    'DOMContentLoaded',
    () => {
        const placeOrderButtonBootstrap = new PlaceOrderButtonBootstrap(PpcpPlaceOrderButton);
        placeOrderButtonBootstrap.init();
    });
