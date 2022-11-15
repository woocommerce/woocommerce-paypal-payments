import { loadScript } from "@paypal/paypal-js";
import {debounce} from "./helper/debounce";
import Renderer from '../../../ppcp-button/resources/js/modules/Renderer/Renderer'
import MessageRenderer from "../../../ppcp-button/resources/js/modules/Renderer/MessageRenderer";

;document.addEventListener(
    'DOMContentLoaded',
    () => {
        function disableAll(nodeList){
            nodeList.forEach(node => node.setAttribute('disabled', 'true'))
        }

        const disabledCheckboxes = document.querySelectorAll(
            '.ppcp-disabled-checkbox'
        )

        disableAll( disabledCheckboxes )

        const form = jQuery('#mainform');

        const payLaterButtonInput = document.querySelector('#ppcp-pay_later_button_enabled');

        if (payLaterButtonInput) {
            const payLaterButtonPreview = document.querySelector('.ppcp-button-preview.pay-later');

            if (!payLaterButtonInput.checked) {
                payLaterButtonPreview.classList.add('disabled')
            }

            if (payLaterButtonInput.classList.contains('ppcp-disabled-checkbox')) {
                payLaterButtonPreview.style.display = 'none';
            }

            payLaterButtonInput.addEventListener('click', () => {
                payLaterButtonPreview.classList.remove('disabled')

                if (!payLaterButtonInput.checked) {
                    payLaterButtonPreview.classList.add('disabled')
                }
            });
        }

        function createButtonPreview(settingsCallback) {
            const render = (settings) => {
                const wrapper = document.querySelector(settings.button.wrapper);
                if (!wrapper) {
                    return;
                }
                wrapper.innerHTML = '';

                const renderer = new Renderer(null, settings, (data, actions) => actions.reject(), null);

                try {
                    renderer.render({});
                } catch (err) {
                    console.error(err);
                }
            };

            renderPreview(settingsCallback, render);
        }

        function getPaypalScriptSettings() {
            const disableFundingInput = jQuery('[name="ppcp[disable_funding][]"]');
            let disabledSources = disableFundingInput.length > 0 ? disableFundingInput.val() : PayPalCommerceGatewaySettings.disabled_sources;
            const isPayLaterButtonEnabled = payLaterButtonInput ? payLaterButtonInput.checked : PayPalCommerceGatewaySettings.is_pay_later_button_enabled
            const settings = {
                'client-id': PayPalCommerceGatewaySettings.client_id,
                'currency': PayPalCommerceGatewaySettings.currency,
                'integration-date': PayPalCommerceGatewaySettings.integration_date,
                'components': ['buttons', 'funding-eligibility', 'messages'],
                'enable-funding': ['venmo', 'paylater'],
                'buyer-country': PayPalCommerceGatewaySettings.country,
            };

            if (!isPayLaterButtonEnabled) {
                disabledSources = disabledSources.concat('credit')
            }

            if (disabledSources?.length) {
                settings['disable-funding'] = disabledSources;
            }

            return settings;
        }

        function loadPaypalScript(settings, onLoaded = () => {}) {
            loadScript(JSON.parse(JSON.stringify(settings))) // clone the object to prevent modification
                .then(paypal => {
                    document.dispatchEvent(new CustomEvent('ppcp_paypal_script_loaded'));

                    onLoaded(paypal);
                })
                .catch((error) => console.error('failed to load the PayPal JS SDK script', error));
        }

        function getButtonSettings(wrapperSelector, fields) {
            const layout = jQuery(fields['layout']).val();
            const style = {
                'color': jQuery(fields['color']).val(),
                'shape': jQuery(fields['shape']).val(),
                'label': jQuery(fields['label']).val(),
                'tagline': layout === 'horizontal' && jQuery(fields['tagline']).is(':checked'),
                'layout': layout,
            };
            if ('height' in fields) {
                style['height'] = parseInt(jQuery(fields['height']).val());
            }
            return {
                'button': {
                    'wrapper': wrapperSelector,
                    'style': style,
                },
                'separate_buttons': {},
            };
        }

        function createMessagesPreview(settingsCallback) {
            const render = (settings) => {
                const wrapper = document.querySelector(settings.wrapper);
                if (!wrapper) {
                    return;
                }
                wrapper.innerHTML = '';

                const messageRenderer = new MessageRenderer(settings);

                try {
                    messageRenderer.renderWithAmount(settings.amount);
                } catch (err) {
                    console.error(err);
                }
            };

            renderPreview(settingsCallback, render);
        }

        function getMessageSettings(wrapperSelector, fields) {
            const layout = jQuery(fields['layout']).val();
            const style = {
                'layout': layout,
                'logo': {
                    'type': jQuery(fields['logo_type']).val(),
                    'position': jQuery(fields['logo_position']).val()
                },
                'text': {
                    'color': jQuery(fields['text_color']).val()
                },
                'color': jQuery(fields['flex_color']).val(),
                'ratio': jQuery(fields['flex_ratio']).val(),
            };

            return {
                'wrapper': wrapperSelector,
                'style': style,
                'amount': 30,
                'placement': 'product',
            };
        }

        function renderPreview(settingsCallback, render) {
            let oldSettings = settingsCallback();

            form.on('change', ':input', debounce(() => {
                const newSettings = settingsCallback();
                if (JSON.stringify(oldSettings) === JSON.stringify(newSettings)) {
                    return;
                }

                render(newSettings);

                oldSettings = newSettings;
            }, 300));

            jQuery(document).on('ppcp_paypal_script_loaded', () => {
                oldSettings = settingsCallback();

                render(oldSettings);
            });

            render(oldSettings);
        }

        function getButtonDefaultSettings(wrapperSelector) {
            const style = {
                'color': 'gold',
                'shape': 'pill',
                'label': 'paypal',
                'tagline': false,
                'layout': 'vertical',
            };
            return {
                'button': {
                    'wrapper': wrapperSelector,
                    'style': style,
                },
                'separate_buttons': {},
            };
        }

        const payLaterMessagingLocations = ['product', 'cart', 'checkout', 'general'];

        const previewElements = document.querySelectorAll('.ppcp-preview');
        if (previewElements.length) {
            let oldScriptSettings = getPaypalScriptSettings();

            form.on('change', ':input', debounce(() => {
                const newSettings = getPaypalScriptSettings();
                if (JSON.stringify(oldScriptSettings) === JSON.stringify(newSettings)) {
                    return;
                }

                loadPaypalScript(newSettings);

                oldScriptSettings = newSettings;
            }, 1000));

            loadPaypalScript(oldScriptSettings, () => {
                createButtonPreview(() => getButtonSettings('#ppcpCheckoutButtonPreview', {
                    'color': '#ppcp-button_color',
                    'shape': '#ppcp-button_shape',
                    'label': '#ppcp-button_label',
                    'tagline': '#ppcp-button_tagline',
                    'layout': '#ppcp-button_layout',
                }));
                createButtonPreview(() => getButtonSettings('#ppcpProductButtonPreview', {
                    'color': '#ppcp-button_product_color',
                    'shape': '#ppcp-button_product_shape',
                    'label': '#ppcp-button_product_label',
                    'tagline': '#ppcp-button_product_tagline',
                    'layout': '#ppcp-button_product_layout',
                }));
                createButtonPreview(() => getButtonSettings('#ppcpCartButtonPreview', {
                    'color': '#ppcp-button_cart_color',
                    'shape': '#ppcp-button_cart_shape',
                    'label': '#ppcp-button_cart_label',
                    'tagline': '#ppcp-button_cart_tagline',
                    'layout': '#ppcp-button_cart_layout',
                }));
                createButtonPreview(() => getButtonSettings('#ppcpMiniCartButtonPreview', {
                    'color': '#ppcp-button_mini-cart_color',
                    'shape': '#ppcp-button_mini-cart_shape',
                    'label': '#ppcp-button_mini-cart_label',
                    'tagline': '#ppcp-button_mini-cart_tagline',
                    'layout': '#ppcp-button_mini-cart_layout',
                    'height': '#ppcp-button_mini-cart_height',
                }));

                payLaterMessagingLocations.forEach((location) => {
                    const inputNamePrefix = '#ppcp-pay_later_' + location + '_message';
                    const wrapperName = location.charAt(0).toUpperCase() + location.slice(1);
                    createMessagesPreview(() => getMessageSettings('#ppcp' + wrapperName + 'MessagePreview', {
                        'layout': inputNamePrefix + '_layout',
                        'logo_type': inputNamePrefix + '_logo',
                        'logo_position': inputNamePrefix + '_position',
                        'text_color': inputNamePrefix + '_color',
                        'flex_color': inputNamePrefix + '_flex_color',
                        'flex_ratio': inputNamePrefix + '_flex_ratio',
                    }));
                });

                createButtonPreview(() => getButtonDefaultSettings('#ppcpPayLaterButtonPreview'));
            });
        }
    }
);
