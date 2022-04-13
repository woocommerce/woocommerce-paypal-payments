class ErrorHandler {

    constructor(genericErrorText)
    {
        this.genericErrorText = genericErrorText;
        this.wrapper = document.querySelector('.woocommerce-notices-wrapper');
        this.messagesList = document.querySelector('ul.woocommerce-error');
    }

    genericError() {
        if (this.wrapper.classList.contains('ppcp-persist')) {
            return;
        }
        this.clear();
        this.message(this.genericErrorText)
    }

    appendPreparedErrorMessageElement(errorMessageElement)
    {
        if(this.messagesList === null) {
            this.prepareMessagesList();
        }

        this.messagesList.replaceWith(errorMessageElement);
    }

    message(text, persist = false)
    {
        if(! typeof String || text.length === 0){
            throw new Error('A new message text must be a non-empty string.');
        }

        if(this.messagesList === null){
            this.prepareMessagesList();
        }

        if (persist) {
            this.wrapper.classList.add('ppcp-persist');
        } else {
            this.wrapper.classList.remove('ppcp-persist');
        }

        let messageNode = this.prepareMessagesListItem(text);
        this.messagesList.appendChild(messageNode);

        jQuery.scroll_to_notices(jQuery('.woocommerce-notices-wrapper'))
    }

    prepareMessagesList()
    {
        if(this.messagesList === null){
            this.messagesList = document.createElement('ul');
            this.messagesList.setAttribute('class', 'woocommerce-error');
            this.messagesList.setAttribute('role', 'alert');
            this.wrapper.appendChild(this.messagesList);
        }
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
        if (this.messagesList === null) {
            return;
        }

        this.messagesList.innerHTML = '';
    }
}

export default ErrorHandler;
