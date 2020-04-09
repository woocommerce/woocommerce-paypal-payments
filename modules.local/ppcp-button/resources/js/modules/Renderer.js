class Renderer {
    render(wrapper, buttonConfig) {
        paypal.Buttons(
            buttonConfig,
        ).render(wrapper);
    }

    hideButtons(element) {
        document.querySelector(element).style.display = 'none';
    }

    showButtons(element) {
        document.querySelector(element).style.display = 'block';
    }
}

export default Renderer;