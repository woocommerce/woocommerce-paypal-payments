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
        if (this.messagesList === null) {
            this._prepareMessagesList();
        }

        this.messagesList.replaceWith(errorMessageElement);
    }

    /**
     * @param {String} text
     * @param {Boolean} persist
     */
    message(text, persist = false)
    {
        this._addMessage(text, persist);

        this._scrollToMessages();
    }

    /**
     * @param {Array} texts
     * @param {Boolean} persist
     */
    messages(texts, persist = false)
    {
        texts.forEach(t => this._addMessage(t, persist));

        this._scrollToMessages();
    }

    /**
     * @private
     * @param {String} text
     * @param {Boolean} persist
     */
    _addMessage(text, persist = false)
    {
        if(! typeof String || text.length === 0) {
            throw new Error('A new message text must be a non-empty string.');
        }

        if (this.messagesList === null){
            this._prepareMessagesList();
        }

        if (persist) {
            this.wrapper.classList.add('ppcp-persist');
        } else {
            this.wrapper.classList.remove('ppcp-persist');
        }

        let messageNode = this._prepareMessagesListItem(text);
        this.messagesList.appendChild(messageNode);
    }

    /**
     * @private
     */
    _scrollToMessages()
    {
        jQuery.scroll_to_notices(jQuery('.woocommerce-notices-wrapper'));
    }

    /**
     * @private
     */
    _prepareMessagesList()
    {
        if (this.messagesList === null) {
            this.messagesList = document.createElement('ul');
            this.messagesList.setAttribute('class', 'woocommerce-error');
            this.messagesList.setAttribute('role', 'alert');
            this.wrapper.appendChild(this.messagesList);
        }
    }

    /**
     * @private
     */
    _prepareMessagesListItem(message)
    {
        const li = document.createElement('li');
        li.innerHTML = message;

        return li;
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
