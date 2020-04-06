class ErrorHandler {

    constructor()
    {
        this.wrapper = document.querySelector('.woocommerce-notices-wrapper');
    }

    message(text)
    {
        this.wrapper.classList.add('woocommerce-error');
        this.wrapper.innerText = this.sanitize(text);
    }

    sanitize(text)
    {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    clear()
    {
        if (! this.wrapper.classList.contains('woocommerce-error')) {
            return;
        }
        this.wrapper.classList.remove('woocommerce-error');
        this.wrapper.innerText = '';
    }
}

export default ErrorHandler;