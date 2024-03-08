
class DomElement {

    constructor(config) {
        this.config = config;
        this.selector = this.config.selector;
        this.id = this.config.selector || null;
        this.className = this.config.selector || null;
    }

}

export default DomElement;
