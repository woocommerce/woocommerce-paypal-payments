
class FieldDisplayManager {

    constructor() {
        this.rules = [];

        document.ppcpDisplayManagerLog = () => {
            console.log('rules', this.rules);
        }
    }

    addRule(rule) {
        this.rules.push(rule);

        for (const condition of rule.conditions) {
            jQuery(document).on('change', condition.selector, () => {
                this.updateElementsVisibility(condition, rule);
            });

            this.updateElementsVisibility(condition, rule);
        }
    }

    updateElementsVisibility(condition, rule) {
        let value = this.getValue(condition.selector);
        value = (value !== null ? value.toString() : value);

        if (condition.value === value) {
            for (const action of rule.actions) {
                if (action.action === 'visible') {
                    jQuery(action.selector).removeClass('ppcp-field-hidden');
                }
                if (action.action === 'enable') {
                    jQuery(action.selector).removeClass('ppcp-field-disabled')
                        .off('mouseup')
                        .find('> *')
                        .css('pointer-events', '');
                }
            }

        } else {
            for (const action of rule.actions) {
                if (action.action === 'visible') {
                    jQuery(action.selector).addClass('ppcp-field-hidden');
                }
                if (action.action === 'enable') {
                    jQuery(action.selector).addClass('ppcp-field-disabled')
                        .on('mouseup', function(event) {
                            event.stopImmediatePropagation();
                        })
                        .find('> *')
                        .css('pointer-events', 'none');
                }
            }
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

export default FieldDisplayManager;
