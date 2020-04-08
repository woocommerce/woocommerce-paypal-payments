class Renderer {

    constructor(url, wrapper)
    {
        this.url = url;
        this.wrapper = wrapper;
    }

    render(buttonConfig)
    {

        const script = document.createElement('script');

        if (typeof paypal !== 'object') {
            script.setAttribute('src', this.url);
            script.addEventListener('load', (event) => {
                this.renderButtons(buttonConfig);
            })
            document.body.append(script);
            return;
        }

        this.renderButtons(buttonConfig);
    }

    renderButtons(buttonConfig)
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