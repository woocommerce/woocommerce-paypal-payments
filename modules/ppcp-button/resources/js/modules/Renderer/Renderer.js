class Renderer {
    constructor(creditCardRenderer, defaultConfig, onSmartButtonClick, onSmartButtonsInit) {
        this.defaultConfig = defaultConfig;
        this.creditCardRenderer = creditCardRenderer;
        this.onSmartButtonClick = onSmartButtonClick;
        this.onSmartButtonsInit = onSmartButtonsInit;
    }

    render(wrapper, hostedFieldsWrapper, contextConfig) {

        this.renderButtons(wrapper, contextConfig);
        this.creditCardRenderer.render(hostedFieldsWrapper, contextConfig);
    }

    renderButtons(wrapper, contextConfig) {
        if (! document.querySelector(wrapper) || this.isAlreadyRendered(wrapper) || 'undefined' === typeof paypal.Buttons ) {
            return;
        }

        const style = wrapper === this.defaultConfig.button.wrapper ? this.defaultConfig.button.style : this.defaultConfig.button.mini_cart_style;
        paypal.Buttons({
            style,
            ...contextConfig,
            onClick: this.onSmartButtonClick,
            onInit: this.onSmartButtonsInit,
        }).render(wrapper);
    }

    isAlreadyRendered(wrapper) {
        return document.querySelector(wrapper).hasChildNodes();
    }

    hideButtons(element) {
        const domElement = document.querySelector(element);
        if (! domElement ) {
            return false;
        }
        domElement.style.display = 'none';
        return true;
    }

    showButtons(element) {
        const domElement = document.querySelector(element);
        if (! domElement ) {
            return false;
        }
        domElement.style.display = 'block';
        return true;
    }

    disableCreditCardFields() {
        this.creditCardRenderer.disableFields();
    }

    enableCreditCardFields() {
        this.creditCardRenderer.enableFields();
    }
}

export default Renderer;
