document.addEventListener(
    'DOMContentLoaded',
    () => {
        const payLaterMessagingSelectableLocations = ['product', 'cart', 'checkout'];
        const payLaterMessagingAllLocations = payLaterMessagingSelectableLocations.concat('general');
        const payLaterMessagingLocationsSelector = '#field-pay_later_messaging_locations';
        const payLaterMessagingLocationsSelect = payLaterMessagingLocationsSelector + ' select';
        const payLaterMessagingEnabledSelector = '#ppcp-pay_later_messaging_enabled';

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

                    togglePayLaterMessageFields();
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

        const togglePayLaterMessageFields = () => {
            payLaterMessagingAllLocations.forEach( (location) => {
                groupToggleSelect(
                    '#ppcp-pay_later_' + location + '_message_layout',
                    [
                        {
                            value:'text',
                            selector:'#field-pay_later_' + location + '_message_logo'
                        },
                        {
                            value:'text',
                            selector:'#field-pay_later_' + location + '_message_position'
                        },
                        {
                            value:'text',
                            selector:'#field-pay_later_' + location + '_message_color'
                        },
                        {
                            value:'flex',
                            selector:'#field-pay_later_' + location + '_message_flex_ratio'
                        },
                        {
                            value:'flex',
                            selector:'#field-pay_later_' + location + '_message_flex_color'
                        }
                    ]
                );
            })
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

        const togglePayLaterMessagingInputsBySelectedLocations = (
            stylingPerMessagingSelector,
            messagingLocationsSelector,
            groupToShowOnChecked,
            groupToHideOnChecked
        ) => {

            const payLaterMessagingEnabled = document.querySelector(payLaterMessagingEnabledSelector);
            const stylingPerMessagingElement = document.querySelector(stylingPerMessagingSelector);
            const messagingLocationsElement = document.querySelector(messagingLocationsSelector);

            if (! stylingPerMessagingElement) {
                return;
            }

            const toggleElementsBySelectedLocations = () => {
                if (! stylingPerMessagingElement.checked || ! payLaterMessagingEnabled.checked) {
                    return;
                }

                let checkedLocations = document.querySelectorAll(messagingLocationsSelector + ' :checked');
                const selectedLocations = [...checkedLocations].map(option => option.value);

                const messagingInputSelectors = payLaterMessagingInputSelectorsByLocations(selectedLocations);

                groupToShowOnChecked.forEach( (element) => {
                    if ( messagingInputSelectors.includes(element) ) {
                        document.querySelector(element).style.display = '';
                        return;
                    }
                    document.querySelector(element).style.display = 'none';
                })

                togglePayLaterMessageFields();
            }

            const hideElements = (selectroGroup) => {
                selectroGroup.forEach( (elementToHide) => {
                    document.querySelector(elementToHide).style.display = 'none';
                })
            }

            const showElements = (selectroGroup) => {
                selectroGroup.forEach( (elementToShow) => {
                    document.querySelector(elementToShow).style.display = '';
                })
            }

            groupToggle(stylingPerMessagingSelector, groupToShowOnChecked);
            toggleElementsBySelectedLocations();

            if (stylingPerMessagingElement.checked) {
                hideElements(groupToHideOnChecked);
            }

            stylingPerMessagingElement.addEventListener(
                'change',
                (event) => {
                    toggleElementsBySelectedLocations();

                    if (event.target.checked) {
                        hideElements(groupToHideOnChecked);
                        return;
                    }

                    showElements(groupToHideOnChecked);
                    togglePayLaterMessageFields();
                }
            );

            // We need to use jQuery here as the select might be a select2 element, which doesn't use native events.
            jQuery(messagingLocationsElement).on('change', toggleElementsBySelectedLocations);
        }

        const payLaterMessagingInputSelectorsByLocations = (locations) => {
            let inputSelectros = [];

            locations.forEach( (location) => {
                inputSelectros = inputSelectros.concat(payLaterMessagingInputSelectorByLocation(location))
            })

            return inputSelectros
        }

        const payLaterMessagingInputSelectorByLocation = (location) => {
            const inputSelectors = [
                '#field-pay_later_' + location + '_message_layout',
                '#field-pay_later_' + location + '_message_logo',
                '#field-pay_later_' + location + '_message_position',
                '#field-pay_later_' + location + '_message_color',
                '#field-pay_later_' + location + '_message_flex_color',
                '#field-pay_later_' + location + '_message_flex_ratio',
                '#field-pay_later_' + location + '_message_preview',
            ]

            if (location !== 'general') {
                inputSelectors.push('#field-pay_later_' + location + '_messaging_heading');
            }

            return inputSelectors
        }

        const allPayLaterMessaginginputSelectors = () => {
            let stylingInputSelectors = payLaterMessagingInputSelectorsByLocations(payLaterMessagingAllLocations);

            return stylingInputSelectors.concat(payLaterMessagingLocationsSelector, '#field-pay_later_enable_styling_per_messaging_location');
        }

        const toggleMessagingEnabled = () => {
            const payLaterMessagingEnabled = document.querySelector(payLaterMessagingEnabledSelector);
            const stylingPerMessagingElement = document.querySelector('#ppcp-pay_later_enable_styling_per_messaging_location');

            if (! payLaterMessagingEnabled) {
                return;
            }

            groupToggle(
                payLaterMessagingEnabledSelector,
                allPayLaterMessaginginputSelectors()
            );

            payLaterMessagingEnabled.addEventListener(
                'change',
                (event) => {
                    if (! event.target.checked) {
                        return;
                    }
                    stylingPerMessagingElement.dispatchEvent(new Event('change'))
                }
            );
        }

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
                    '#field-button_preview',
                ]
            );

            groupToggle(
                '#ppcp-pay_later_button_enabled',
                ['#field-pay_later_button_locations']
            );

            toggleMessagingEnabled();

            togglePayLaterMessagingInputsBySelectedLocations(
                '#ppcp-pay_later_enable_styling_per_messaging_location',
                payLaterMessagingLocationsSelect,
                payLaterMessagingInputSelectorsByLocations(payLaterMessagingSelectableLocations),
                payLaterMessagingInputSelectorsByLocations(['general']),
            );

            groupToggle(
                '#ppcp-button_product_enabled',
                [
                    '#field-button_product_layout',
                    '#field-button_product_tagline',
                    '#field-button_product_label',
                    '#field-button_product_color',
                    '#field-button_product_shape',
                    '#field-button_product_preview',
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
                    '#field-button_mini-cart_preview',
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
                    '#field-button_cart_preview',
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

            togglePayLaterMessageFields();
        })();
    }
)
