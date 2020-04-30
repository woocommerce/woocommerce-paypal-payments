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
        document.querySelector(element).style.display = 'none';
    }

    showButtons(element) {
        document.querySelector(element).style.display = 'block';
    }
}

export default Renderer;