class Renderer {

    constructor(config)
    {
        this.config = config;
    }

    render(buttonConfig)
    {

        const script = document.createElement('script');

        if (typeof paypal !== 'object') {
            script.setAttribute('src', this.config.url);
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
        ).render(this.config.wrapper);
    }

    hideButtons()
    {
        document.querySelector(this.config.wrapper).style.display = 'none';
    }

    showButtons()
    {
        document.querySelector(this.config.wrapper).style.display = 'block';
    }
}

export default Renderer;