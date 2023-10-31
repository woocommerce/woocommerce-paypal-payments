document.addEventListener(
    'DOMContentLoaded',
    () => {
        const payLaterMessagingSelectableLocations = ['product', 'cart', 'checkout', 'shop', 'home'];
        const payLaterMessagingAllLocations = payLaterMessagingSelectableLocations.concat('general');
        const payLaterMessagingLocationsSelector = '#field-pay_later_messaging_locations';
        const payLaterMessagingLocationsSelect = payLaterMessagingLocationsSelector + ' select';
        const payLaterMessagingEnabledSelector = '#ppcp-pay_later_messaging_enabled';

        const smartButtonLocationsSelector = '#field-smart_button_locations';
        const smartButtonLocationsSelect = smartButtonLocationsSelector + ' select';
        const smartButtonSelectableLocations = ['product', 'cart', 'checkout', 'mini-cart'];

        const groupToggle = (selector, group) => {
            const toggleElement = document.querySelector(selector);
            if (! toggleElement) {
                return;
            }

            if (! toggleElement.checked) {
                group.forEach( (elementToHide) => {
                    const element = document.querySelector(elementToHide);
                    if (element) {
                        element.style.display = 'none';
                    }
                })
            }
            toggleElement.addEventListener(
                'change',
                (event) => {
                    if (! event.target.checked) {
                        group.forEach( (elementToHide) => {
                            const element = document.querySelector(elementToHide);
                            if (element) {
                                element.style.display = 'none';
                            }
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

        const toggleInputsBySelectedLocations = (
            stylingPerSelector,
            locationsSelector,
            groupToShowOnChecked,
            groupToHideOnChecked,
            inputType
        ) => {

            const payLaterMessagingEnabled = document.querySelector(payLaterMessagingEnabledSelector);
            const stylingPerElement = document.querySelector(stylingPerSelector);
            const locationsElement = document.querySelector(locationsSelector);
            const stylingPerElementWrapper = stylingPerElement?.closest('tr');
            const stylingPerElementWrapperSelector = '#'+ stylingPerElementWrapper?.getAttribute('id');

            if (! stylingPerElement) {
                return;
            }

            const toggleElementsBySelectedLocations = () => {
                stylingPerElementWrapper.style.display = '';
                let selectedLocations = getSelectedLocations(locationsSelector);
                let emptySmartButtonLocationMessage = jQuery('.ppcp-empty-smart-button-location');

                if(selectedLocations.length === 0) {
                    hideElements(groupToHideOnChecked.concat(stylingPerElementWrapperSelector));
                    if (emptySmartButtonLocationMessage.length === 0) {
                        jQuery(PayPalCommerceSettings.empty_smart_button_location_message).insertAfter(jQuery(smartButtonLocationsSelector).find('.description'));
                    }
                }

                if (! stylingPerElement.checked) {
                    return;
                }

                if (inputType === 'messages' && ! payLaterMessagingEnabled.checked) {
                    return;
                }

                const inputSelectors = inputSelectorsByLocations(selectedLocations, inputType);

                groupToShowOnChecked.forEach( (element) => {
                    if ( inputSelectors.includes(element) ) {
                        document.querySelector(element).style.display = '';
                        return;
                    }
                    document.querySelector(element).style.display = 'none';
                })

                if (inputType === 'messages') {
                    togglePayLaterMessageFields();
                }
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

            groupToggle(stylingPerSelector, groupToShowOnChecked);
            toggleElementsBySelectedLocations();

            if (stylingPerElement.checked) {
                hideElements(groupToHideOnChecked);
            }

            stylingPerElement.addEventListener(
                'change',
                (event) => {
                    toggleElementsBySelectedLocations();

                    if (event.target.checked) {
                        hideElements(groupToHideOnChecked);
                        return;
                    }

                    let selectedLocations = getSelectedLocations(locationsSelector);
                    if(selectedLocations.length > 0) {
                        showElements(groupToHideOnChecked);
                    }

                    if (inputType === 'messages') {
                        togglePayLaterMessageFields();
                    }
                }
            );

            // We need to use jQuery here as the select might be a select2 element, which doesn't use native events.
            jQuery(locationsElement).on('change', function (){
                let emptySmartButtonLocationMessage = jQuery('.ppcp-empty-smart-button-location');
                emptySmartButtonLocationMessage?.remove();
                toggleElementsBySelectedLocations()
                stylingPerElement.dispatchEvent(new Event('change'))
            });
        }

        const getSelectedLocations = (selector) => {
            let checkedLocations = document.querySelectorAll(selector + ' :checked');
            return [...checkedLocations].map(option => option.value);
        }

        const inputSelectorsByLocations = (locations, inputType = 'messages') => {
            let inputSelectros = [];

            locations.forEach( (location) => {
                inputSelectros = inputType === 'messages'
                    ? inputSelectros.concat(payLaterMessagingInputSelectorByLocation(location))
                    : inputSelectros.concat(butttonInputSelectorByLocation(location));
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

        const butttonInputSelectorByLocation = (location) => {
            const locationPrefix = location === 'checkout' ? '' : '_' + location;
            const inputSelectors = [
                '#field-button' + locationPrefix + '_layout',
                '#field-button' + locationPrefix + '_tagline',
                '#field-button' + locationPrefix + '_label',
                '#field-button' + locationPrefix + '_color',
                '#field-button' + locationPrefix + '_shape',
                '#field-button' + locationPrefix + '_preview',
            ]

            if (location !== 'general') {
                inputSelectors.push('#field-button_' + location + '_heading');
            }

            if (location === 'mini-cart') {
                inputSelectors.push('#field-button' + locationPrefix + '_height');
            }

            return inputSelectors
        }

        const allPayLaterMessaginginputSelectors = () => {
            let stylingInputSelectors = inputSelectorsByLocations(payLaterMessagingAllLocations);

            return stylingInputSelectors.concat(payLaterMessagingLocationsSelector, '#field-pay_later_enable_styling_per_messaging_location');
        }

        const toggleMessagingEnabled = () => {
            const payLaterMessagingEnabled = document.querySelector(payLaterMessagingEnabledSelector);
            const stylingPerMessagingElement = document.querySelector('#ppcp-pay_later_enable_styling_per_messaging_location');

            groupToggle(
                payLaterMessagingEnabledSelector,
                allPayLaterMessaginginputSelectors()
            );

            if (! payLaterMessagingEnabled) {
                return;

            }

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
                '#ppcp-pay_later_button_enabled',
                ['#field-pay_later_button_locations']
            );

            toggleInputsBySelectedLocations(
                '#ppcp-pay_later_enable_styling_per_messaging_location',
                payLaterMessagingLocationsSelect,
                inputSelectorsByLocations(payLaterMessagingSelectableLocations),
                inputSelectorsByLocations(['general']),
                'messages'
            );

            toggleInputsBySelectedLocations(
                '#ppcp-smart_button_enable_styling_per_location',
                smartButtonLocationsSelect,
                inputSelectorsByLocations(smartButtonSelectableLocations, 'buttons'),
                inputSelectorsByLocations(['general'], 'buttons'),
                'buttons'
            );

            toggleMessagingEnabled();


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
                    },
                    {
                        value:'authorize',
                        selector:'#field-capture_on_status_change'
                    }
                ]
            );

            togglePayLaterMessageFields();
        })();
    }
)
