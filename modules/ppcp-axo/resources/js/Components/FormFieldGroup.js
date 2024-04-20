
class FormFieldGroup {

    constructor(config) {
        this.data = {};

        this.baseSelector = config.baseSelector;
        this.contentSelector = config.contentSelector;
        this.fields = config.fields || {};
        this.template = config.template;

        this.active = false;
    }

    setData(data) {
        this.data = data;
        this.refresh();
    }

    dataValue(fieldKey) {
        if (!fieldKey || !this.fields[fieldKey]) {
            return '';
        }

        if (typeof this.fields[fieldKey].valueCallback === 'function') {
            return this.fields[fieldKey].valueCallback(this.data);
        }

        const path = this.fields[fieldKey].valuePath;

        if (!path) {
            return '';
        }

        const value = path.split('.').reduce((acc, key) => (acc && acc[key] !== undefined) ? acc[key] : undefined, this.data);
        return value ? value : '';
    }

    activate() {
        this.active = true;
        this.refresh();
    }

    deactivate() {
        this.active = false;
        this.refresh();
    }

    toggle() {
        this.active ? this.deactivate() : this.activate();
    }

    refresh() {
        let content = document.querySelector(this.contentSelector);

        if (!content) {
            return;
        }

        content.innerHTML = '';

        if (!this.active) {
            this.hideField(this.contentSelector);
        } else {
            this.showField(this.contentSelector);
        }

        Object.keys(this.fields).forEach((key) => {
            const field = this.fields[key];

            if (this.active && !field.showInput) {
                this.hideField(field.selector);
            } else {
                this.showField(field.selector);
            }
        });

        if (typeof this.template === 'function') {
            content.innerHTML = this.template({
                value: (fieldKey) => {
                    return this.dataValue(fieldKey);
                },
                isEmpty: () => {
                    let isEmpty = true;
                    Object.keys(this.fields).forEach((fieldKey) => {
                        if (this.dataValue(fieldKey)) {
                            isEmpty = false;
                            return false;
                        }
                    });
                    return isEmpty;
                }
            });
        }

    }

    showField(selector) {
        const field = document.querySelector(this.baseSelector + ' ' + selector);
        if (field) {
            field.classList.remove('ppcp-axo-field-hidden');
        }
    }

    hideField(selector) {
        const field = document.querySelector(this.baseSelector + ' ' + selector);
        if (field) {
            field.classList.add('ppcp-axo-field-hidden');
        }
    }

    inputElement(name) {
        const baseSelector = this.fields[name].selector;

        const select = document.querySelector(baseSelector + ' select');
        if (select) {
            return select;
        }

        const input = document.querySelector(baseSelector + ' input');
        if (input) {
            return input;
        }

        return null;
    }

    inputValue(name) {
        const el = this.inputElement(name);
        return el ? el.value : '';
    }

    toSubmitData(data) {
        Object.keys(this.fields).forEach((fieldKey) => {
            const field = this.fields[fieldKey];

            if (!field.valuePath || !field.selector) {
                return true;
            }

            const inputElement = this.inputElement(fieldKey);

            if (!inputElement) {
                return true;
            }

            data[inputElement.name] = this.dataValue(fieldKey);
        });
    }

}

export default FormFieldGroup;
