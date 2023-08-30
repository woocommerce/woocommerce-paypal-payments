
class SubElementsHandler {
    constructor(element, options) {
        const fieldSelector = 'input, select, textarea';

        this.element = element;
        this.values = options.values;
        this.elements = options.elements;

        this.elementsSelector = this.elements.join(',');

        this.input = jQuery(this.element).is(fieldSelector)
            ? this.element
            : jQuery(this.element).find(fieldSelector).get(0);

        this.updateElementsVisibility();

        jQuery(this.input).change(() => {
            this.updateElementsVisibility();
        });
    }

    updateElementsVisibility() {
        const $elements = jQuery(this.elementsSelector);

        let value = this.getValue(this.input);
        value = (value !== null ? value.toString() : value);

        if (this.values.indexOf(value) !== -1) {
            $elements.show();
        } else {
            $elements.hide();
        }
    }

    getValue(element) {
        const $el = jQuery(element);

        if ($el.is(':checkbox') || $el.is(':radio')) {
            if ($el.is(':checked')) {
                return $el.val();
            } else {
                return null;
            }
        } else {
            return $el.val();
        }
    }
}

export default SubElementsHandler;
