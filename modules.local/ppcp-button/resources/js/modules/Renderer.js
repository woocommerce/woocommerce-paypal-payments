class Renderer {
    render(wrapper, buttonConfig) {
        if (this.isAlreadyRendered(wrapper)) {
            return;
        }

        paypal.Buttons(
            buttonConfig,
        ).render(wrapper);
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