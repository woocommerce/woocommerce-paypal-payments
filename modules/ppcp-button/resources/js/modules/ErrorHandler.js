class ErrorHandler {

    constructor(genericErrorText)
    {
        this.genericErrorText = genericErrorText;
        this.wrapper = document.querySelector('.woocommerce-notices-wrapper');
    }

    genericError() {
        if (this.wrapper.classList.contains('ppcp-persist')) {
            return;
        }
        this.clear();
        this.message(this.genericErrorText)
    }

    message(text, persist = false)
    {
        this.wrapper.classList.add('woocommerce-error');
        if (persist) {
            this.wrapper.classList.add('ppcp-persist');
        } else {
            this.wrapper.classList.remove('ppcp-persist');
        }
        this.wrapper.innerHTML = this.sanitize(text);
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
