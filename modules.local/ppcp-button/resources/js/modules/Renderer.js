class Renderer {
    constructor(defaultConfig) {
        this.defaultConfig = defaultConfig;
    }

    render(wrapper, contextConfig) {
        if (this.isAlreadyRendered(wrapper)) {
            return;
        }

        const style = this.defaultConfig.button.style;
        paypal.Buttons({
            style,
            ...contextConfig,
        }).render(wrapper);
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