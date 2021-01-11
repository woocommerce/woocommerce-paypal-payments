class Spinner {

    constructor() {
        this.target = 'form.woocommerce-checkout';
    }

    setTarget(target) {
        this.target = target;
    }

    block() {

        jQuery( this.target ).block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    unblock() {

        jQuery( this.target ).unblock();
    }
}

export default Spinner;
