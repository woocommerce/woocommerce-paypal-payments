class Spinner {

    block() {

        jQuery( '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table' ).block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    unblock() {

        jQuery( '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table' ).unblock();
    }
}

export default Spinner;