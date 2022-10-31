import { loadScript } from "@paypal/paypal-js";
import {debounce} from "./helper/debounce";
import Renderer from '../../../ppcp-button/resources/js/modules/Renderer/Renderer'

;document.addEventListener(
    'DOMContentLoaded',
    () => {
        const disabledCheckboxes = document.querySelectorAll(
            '.ppcp-disabled-checkbox'
        )

        function disableAll(nodeList){
            nodeList.forEach(node => node.setAttribute('disabled', 'true'))
        }

        disableAll( disabledCheckboxes )

        if(PayPalCommerceGatewaySettings.is_subscriptions_plugin_active !== '1') {
            const subscriptionBehaviorWhenVaultFails = document.getElementById('field-subscription_behavior_when_vault_fails');
            if (subscriptionBehaviorWhenVaultFails) {
                subscriptionBehaviorWhenVaultFails.style.display = 'none'
            }
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
