const groupToggle = (selector, group) => {
    const toggleElement = document.querySelector(selector);
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

            group.forEach( (elementToHide) => {
                document.querySelector(elementToHide).style.display = 'table-row';
            })
        }
    );

}

(() => {
    document.querySelector('#field-toggle_manual_input').addEventListener(
        'click',
        (event) => {
            event.preventDefault();
            document.querySelector('#field-toggle_manual_input').style.display = 'none';
            document.querySelector('#field-client_id').style.display = 'table-row';
            document.querySelector('#field-client_secret').style.display = 'table-row';
        }
    )

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
})()