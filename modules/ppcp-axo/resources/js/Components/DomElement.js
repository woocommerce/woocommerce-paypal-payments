import {setVisible} from "../../../../ppcp-button/resources/js/modules/Helper/Hiding";

class DomElement {

    constructor(config) {
        this.$ = jQuery;
        this.config = config;
        this.selector = this.config.selector;
        this.id = this.config.id || null;
        this.className = this.config.className || null;
        this.attributes = this.config.attributes || null;
        this.anchorSelector = this.config.anchorSelector || null;
    }

    trigger(action) {
        this.$(this.selector).trigger(action);
    }

    on(action, callable) {
        this.$(document).on(action, this.selector, callable);
    }

    hide() {
        this.$(this.selector).hide();
    }

    show() {
        this.$(this.selector).show();
    }

    click() {
        document.querySelector(this.selector).click();
    }
}

export default DomElement;
