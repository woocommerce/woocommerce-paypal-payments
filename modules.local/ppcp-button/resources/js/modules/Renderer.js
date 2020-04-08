class Renderer {

    constructor(wrapper)
    {
        this.wrapper = wrapper;
    }

    render(buttonConfig)
    {

        paypal.Buttons(
            buttonConfig
        ).render(this.wrapper);
    }

    hideButtons()
    {
        document.querySelector(this.wrapper).style.display = 'none';
    }

    showButtons()
    {
        document.querySelector(this.wrapper).style.display = 'block';
    }
}

export default Renderer;