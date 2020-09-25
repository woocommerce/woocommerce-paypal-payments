const groupToggle = (selector, group) => {
    const toggleElement = document.querySelector(selector);
    if (! toggleElement) {
        return;
    }
    if (! toggleElement.checked) {
        group.forEach( (elementToHide) => {
            document.querySelector(elementToHide).style.display = 'none';
        })
    }
    toggleElement.addEventListener(
        'change',
        (event) => {

            if (! event.target.checked) {
                group.forEach( (elementToHide) => {
                    document.querySelector(elementToHide).style.display = 'none';
                })
                return;
            }

            group.forEach( (elementToShow) => {
                document.querySelector(elementToShow).style.display = 'table-row';
            })
        }
    );

}

const groupToggleSelect = (selector, group) => {
    const toggleElement = document.querySelector(selector);
    if (! toggleElement) {
        return;
    }
    const value = toggleElement.value;
    group.forEach( (elementToToggle) => {
        const domElement = document.querySelector(elementToToggle.selector);
        if (! domElement) {
            return;
        }
        if (value === elementToToggle.value && domElement.style.display !== 'none') {
            domElement.style.display = 'table-row';
            return;
        }
        domElement.style.display = 'none';
    })
    toggleElement.addEventListener(
        'change',
        (event) => {
            const value = event.target.value;
            group.forEach( (elementToToggle) => {
                if (value === elementToToggle.value) {
                    document.querySelector(elementToToggle.selector).style.display = 'table-row';
                    return;
                }
                document.querySelector(elementToToggle.selector).style.display = 'none';
            })
        }
    );
}

(() => {

    groupToggle(
        '#ppcp-button_enabled',
        [
            '#field-button_layout',
            '#field-button_tagline',
            '#field-button_label',
            '#field-button_color',
            '#field-button_shape',
        ]
    );

    groupToggle(
        '#ppcp-message_enabled',
        [
            '#field-message_layout',
            '#field-message_logo',
            '#field-message_position',
            '#field-message_color',
            '#field-message_flex_color',
            '#field-message_flex_ratio',
        ]
    );

    groupToggle(
        '#ppcp-button_product_enabled',
        [
            '#field-button_product_layout',
            '#field-button_product_tagline',
            '#field-button_product_label',
            '#field-button_product_color',
            '#field-button_product_shape',
        ]
    );

    groupToggle(
        '#ppcp-message_product_enabled',
        [
            '#field-message_product_layout',
            '#field-message_product_logo',
            '#field-message_product_position',
            '#field-message_product_color',
            '#field-message_product_flex_color',
            '#field-message_product_flex_ratio',
        ]
    );

    groupToggle(
        '#ppcp-button_mini-cart_enabled',
        [
            '#field-button_mini-cart_layout',
            '#field-button_mini-cart_tagline',
            '#field-button_mini-cart_label',
            '#field-button_mini-cart_color',
            '#field-button_mini-cart_shape',
        ]
    );

    groupToggle(
        '#ppcp-button_cart_enabled',
        [
            '#field-button_cart_layout',
            '#field-button_cart_tagline',
            '#field-button_cart_label',
            '#field-button_cart_color',
            '#field-button_cart_shape',
        ]
    );
    groupToggle(
        '#ppcp-message_cart_enabled',
        [
            '#field-message_cart_layout',
            '#field-message_cart_logo',
            '#field-message_cart_position',
            '#field-message_cart_color',
            '#field-message_cart_flex_color',
            '#field-message_cart_flex_ratio',
        ]
    );

    groupToggleSelect(
        '#ppcp-message_product_layout',
        [
            {
                value:'text',
                selector:'#field-message_product_logo'
            },
            {
                value:'text',
                selector:'#field-message_product_position'
            },
            {
                value:'text',
                selector:'#field-message_product_color'
            },
            {
                value:'flex',
                selector:'#field-message_product_flex_ratio'
            },
            {
                value:'flex',
                selector:'#field-message_product_flex_color'
            }
        ]
    );
    groupToggleSelect(
        '#ppcp-intent',
        [
            {
                value:'authorize',
                selector:'#field-capture_for_virtual_only'
            }
        ]
    );
    groupToggleSelect(
        '#ppcp-message_cart_layout',
        [
            {
                value:'text',
                selector:'#field-message_cart_logo'
            },
            {
                value:'text',
                selector:'#field-message_cart_position'
            },
            {
                value:'text',
                selector:'#field-message_cart_color'
            },
            {
                value:'flex',
                selector:'#field-message_cart_flex_ratio'
            },
            {
                value:'flex',
                selector:'#field-message_cart_flex_color'
            }
        ]
    );
    groupToggleSelect(
        '#ppcp-message_layout',
        [
            {
                value:'text',
                selector:'#field-message_logo'
            },
            {
                value:'text',
                selector:'#field-message_position'
            },
            {
                value:'text',
                selector:'#field-message_color'
            },
            {
                value:'flex',
                selector:'#field-message_flex_ratio'
            },
            {
                value:'flex',
                selector:'#field-message_flex_color'
            }
        ]
    );
})()