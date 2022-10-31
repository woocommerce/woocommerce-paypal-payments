import { loadScript } from "@paypal/paypal-js";
import {debounce} from "./helper/debounce";
import Renderer from '../../../ppcp-button/resources/js/modules/Renderer/Renderer'

;document.addEventListener(
    'DOMContentLoaded',
    () => {
        const payLaterMessagingCheckboxes = document.querySelectorAll(
            "#ppcp-message_enabled, #ppcp-message_cart_enabled, #ppcp-message_product_enabled"
        )

        const vaultingCheckboxes = document.querySelectorAll(
            "#ppcp-vault_enabled"
        )

        const payLaterEnabledLabels = document.querySelectorAll(
            ".ppcp-pay-later-enabled-label"
        )

        const payLaterDisabledLabels = document.querySelectorAll(
            ".ppcp-pay-later-disabled-label"
        )

        const disabledCheckboxes = document.querySelectorAll(
            '.ppcp-disabled-checkbox'
        )

        function atLeastOneChecked(checkboxesNodeList) {
            return Array.prototype.slice.call(checkboxesNodeList).filter(node => !node.disabled && node.checked).length > 0
        }

        function disableAll(nodeList){
            nodeList.forEach(node => node.setAttribute('disabled', 'true'))
        }

        function enableAll(nodeList){
            nodeList.forEach(node => node.removeAttribute('disabled'))
        }

        function hideAll(nodeList) {
            nodeList.forEach(node => node.style.display = 'none')
        }

        function displayAll(nodeList) {
            nodeList.forEach(node => node.style.display = '')
        }

        function uncheckAll(nodeList){
            nodeList.forEach(node => {
                node.checked = false
                node.dispatchEvent(new Event('change'))
            })
        }

        function disablePayLater() {
            uncheckAll(payLaterMessagingCheckboxes)
            disableAll(payLaterMessagingCheckboxes)
            hideAll(payLaterEnabledLabels)
            displayAll(payLaterDisabledLabels)
        }

        function enablePayLater() {
            enableAll(payLaterMessagingCheckboxes)
            displayAll(payLaterEnabledLabels)
            hideAll(payLaterDisabledLabels)
        }

        function togglePayLater() {
            atLeastOneChecked(vaultingCheckboxes) ? disablePayLater() : enablePayLater()
        }

        const form = jQuery('#mainform');

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

        function getPaypalScriptSettings() {
            const disabledSources = jQuery('[name="ppcp[disable_funding][]"]').val();
            const settings = {
                'client-id': PayPalCommerceGatewaySettings.client_id,
                'currency': PayPalCommerceGatewaySettings.currency,
                'integration-date': PayPalCommerceGatewaySettings.integration_date,
                'components': ['buttons', 'funding-eligibility', 'messages'],
                'enable-funding': ['venmo'],
                'buyer-country': PayPalCommerceGatewaySettings.country,
            };
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

        disableAll( disabledCheckboxes )
        togglePayLater()

        vaultingCheckboxes.forEach(node => node.addEventListener('change', togglePayLater));

        if(PayPalCommerceGatewaySettings.is_subscriptions_plugin_active !== '1') {
            document.getElementById('field-subscription_behavior_when_vault_fails').style.display = 'none';
        }

        let oldScriptSettings = getPaypalScriptSettings();

        form.on('change', ':input', debounce(() => {
            const newSettings = getPaypalScriptSettings();
            if (JSON.stringify(oldScriptSettings) === JSON.stringify(newSettings)) {
                return;
            }

            loadPaypalScript(newSettings);

            oldScriptSettings = newSettings;
        }, 1000));

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
        });
    }
);
