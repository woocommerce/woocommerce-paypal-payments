/**
 * When you can't add something to the cart, the PayPal buttons should not show.
 * Therefore we listen for changes on the add to cart button and show/hide the buttons accordingly.
 */

class ButtonsToggleListener {
    constructor(element, showCallback, hideCallback)
    {
        this.element = element;
        this.showCallback = showCallback;
        this.hideCallback = hideCallback;
        this.observer = null;
    }

    init()
    {
        const config = { attributes : true };
        const callback = () => {
            if (this.element.classList.contains('disabled')) {
                this.hideCallback();
                return;
            }
            this.showCallback();
        }
        this.observer = new MutationObserver(callback);
        this.observer.observe(this.element, config);
        callback();
    }

    disconnect()
    {
        this.observer.disconnect();
    }
}

export default ButtonsToggleListener;