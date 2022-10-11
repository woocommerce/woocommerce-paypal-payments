document.addEventListener(
    'DOMContentLoaded',
    () => {
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
                        });
                        return;
                    }

                    group.forEach( (elementToShow) => {
                        document.querySelector(elementToShow).style.display = '';
                    })

                    if('ppcp-message_enabled' === event.target.getAttribute('id')){
                        updateCheckoutMessageFields();
                        return;
                    }

                    if('ppcp-message_product_enabled' === event.target.getAttribute('id')){
                        updateProductMessageFields();
                        return;
                    }

                    if('ppcp-message_cart_enabled' === event.target.getAttribute('id')){
                        updateCartMessageFields();
                    }

                }
            );
        };

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
                    domElement.style.display = '';
                    return;
                }
                domElement.style.display = 'none';
            });

            // We need to use jQuery here as the select might be a select2 element, which doesn't use native events.
            jQuery(toggleElement).on(
                'change',
                (event) => {
                    const value = event.target.value;
                    group.forEach( (elementToToggle) => {
                        if (value === elementToToggle.value) {
                            document.querySelector(elementToToggle.selector).style.display = '';
                            return;
                        }
                        document.querySelector(elementToToggle.selector).style.display = 'none';
                    })
                }
            );
        };

        const updateCheckoutMessageFields = () => {
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
        }

        const updateProductMessageFields = () => {
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
        }

        const updateCartMessageFields = () =>
        {
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
        }

        const removeDisabledCardIcons = (disabledCardsSelectSelector, iconsSelectSelector) => {
            const iconsSelect = document.querySelector(iconsSelectSelector);
            if (! iconsSelect) {
                return;
            }
            const allOptions = Array.from(document.querySelectorAll(disabledCardsSelectSelector + ' option'));
            const iconVersions = {
                'visa': {
                    'light': {'label': 'Visa (light)'},
                    'dark' : {'label': 'Visa (dark)', 'value': 'visa-dark'}
                },
                'mastercard': {
                    'light': {'label': 'Mastercard (light)'},
                    'dark' : {'label': 'Mastercard (dark)', 'value': 'mastercard-dark'}
                }
            }
            const replace = () => {
                const validOptions = allOptions.filter(option => ! option.selected);
                const selectedValidOptions = validOptions.map(
                    (option) => {
                        option = option.cloneNode(true);
                        let value = option.value;
                        option.selected = iconsSelect.querySelector('option[value="' + value + '"]') && iconsSelect.querySelector('option[value="' + value + '"]').selected;
                        if(value === 'visa' || value === 'mastercard') {
                            let darkOption = option.cloneNode(true);
                            let currentVersion = iconVersions[value];
                            let darkValue = iconVersions[value].dark.value;

                            option.text = currentVersion.light.label;
                            darkOption.text = currentVersion.dark.label;
                            darkOption.value = darkValue;
                            darkOption.selected = iconsSelect.querySelector('option[value="' + darkValue + '"]') && iconsSelect.querySelector('option[value="' + darkValue + '"]').selected;

                            return [option, darkOption];
                        }
                        return option;
                    }
                ).flat();

                iconsSelect.innerHTML = '';
                selectedValidOptions.forEach(
                    (option) => {
                        if(Array.isArray(option)){
                            option.forEach(
                                (option) => {
                                    iconsSelect.appendChild(option);
                                }
                            )
                        }

                        iconsSelect.appendChild(option);
                    }
                );
            };

            const disabledCardsSelect = jQuery(disabledCardsSelectSelector);
            disabledCardsSelect.on('change', replace);
            replace();
        };

        (() => {
            removeDisabledCardIcons('select[name="ppcp[disable_cards][]"]', 'select[name="ppcp[card_icons][]"]');
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
                    '#field-button_mini-cart_height',
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

            groupToggle(
                '#ppcp-vault_enabled',
                [
                    '#field-subscription_behavior_when_vault_fails',
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

            updateCheckoutMessageFields();
            updateProductMessageFields();
            updateCartMessageFields();
        })();
    }
)
