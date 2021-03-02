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
        let messagesList = this.prepareMessagesList();
        if (persist) {
            this.wrapper.classList.add('ppcp-persist');
        } else {
            this.wrapper.classList.remove('ppcp-persist');
        }

        let messageNode = this.prepareMessagesListItem(text);
        messagesList.appendChild(messageNode);

        jQuery.scroll_to_notices(jQuery('.woocommerce-notices-wrapper'))
    }

    prepareMessagesList()
    {
        let messagesList = document.querySelector('ul.woocommerce-error');

        if(messagesList === null){
            messagesList = document.createElement('ul');
            messagesList.setAttribute('class', 'woocommerce-error');
            messagesList.setAttribute('role', 'alert');
            this.wrapper.appendChild(messagesList);
        }

        return messagesList;
    }

    prepareMessagesListItem(message)
    {
        const li = document.createElement('li');
        li.innerHTML = message;

        return li;
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
