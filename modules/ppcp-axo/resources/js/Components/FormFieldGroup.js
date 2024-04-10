
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

    getDataValue(path) {
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

            if (this.active) {
                this.hideField(field.selector);
            } else {
                this.showField(field.selector);
            }
        });

        if (typeof this.template === 'function') {
            content.innerHTML = this.template({
                value: (valueKey) => {
                    return this.getDataValue(this.fields[valueKey].valuePath);
                },
                isEmpty: () => {
                    let isEmpty = true;
                    Object.values(this.fields).forEach((valueField) => {
                        if (this.getDataValue(valueField.valuePath)) {
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

    inputValue(name) {
        const baseSelector = this.fields[name].selector;

        const select = document.querySelector(baseSelector + ' input');
        if (select) {
            return select.value;
        }

        const input = document.querySelector(baseSelector + ' input');
        if (input) {
            return input.value;
        }

        return '';
    }

}

export default FormFieldGroup;
