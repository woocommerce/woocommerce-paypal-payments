class Renderer {
    constructor(creditCardRenderer, defaultConfig) {
        this.defaultConfig = defaultConfig;
        this.creditCardRenderer = creditCardRenderer;
    }

    render(wrapper, hostedFieldsWrapper, contextConfig) {
        if (this.isAlreadyRendered(wrapper)) {
            return;
        }

        const style = this.defaultConfig.button.style;
        paypal.Buttons({
            style,
            ...contextConfig,
        }).render(wrapper);

        this.creditCardRenderer.render(hostedFieldsWrapper, contextConfig);
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
}

export default Renderer;