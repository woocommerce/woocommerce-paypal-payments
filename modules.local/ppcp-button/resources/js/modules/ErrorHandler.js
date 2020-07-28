class ErrorHandler {

    constructor(genericErrorText)
    {
        this.genericErrorText = genericErrorText;
        this.wrapper = document.querySelector('.woocommerce-notices-wrapper');
    }

    genericError() {
        this.clear();
        this.message(this.genericErrorText)
    }

    message(text)
    {
        this.wrapper.classList.add('woocommerce-error');
        this.wrapper.innerText = this.sanitize(text);
        jQuery.scroll_to_notices(jQuery('.woocommerce-notices-wrapper'))
    }

    sanitize(text)
    {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value.replace('Error: ', '');
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
