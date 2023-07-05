/**
 * @param selectorOrElement
 * @returns {Element}
 */
const getElement = (selectorOrElement) => {
    if (typeof selectorOrElement === 'string') {
        return document.querySelector(selectorOrElement);
    }
    return selectorOrElement;
}

export const setEnabled = (selectorOrElement, enable, form = null) => {
    const element = getElement(selectorOrElement);

    if (!element) {
        return;
    }

    if (enable) {
        jQuery(element).css({
                'cursor': '',
                '-webkit-filter': '',
                'filter': '',
            } )
            .off('mouseup')
            .find('> *')
            .css('pointer-events', '');
    } else {
        jQuery(element).css({
                'cursor': 'not-allowed',
                '-webkit-filter': 'grayscale(100%)',
                'filter': 'grayscale(100%)',
            })
            .on('mouseup', function(event) {
                event.stopImmediatePropagation();

                if (form) {
                    // Trigger form submit to show the error message
                    let $form = jQuery(form);
                    if ($form.find('.single_add_to_cart_button').hasClass('disabled')) {
                        $form.find(':submit').trigger('click');
                    }
                }
            })
            .find('> *')
            .css('pointer-events', 'none');
    }
};

export const disable = (selectorOrElement, form = null) => {
    setEnabled(selectorOrElement, false, form);
};

export const enable = (selectorOrElement) => {
    setEnabled(selectorOrElement, true);
};
