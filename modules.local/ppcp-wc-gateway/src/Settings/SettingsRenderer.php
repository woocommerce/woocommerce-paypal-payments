<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\ApiClient\Helper\DccApplies;
use Inpsyde\PayPalCommerce\Button\Helper\MessagesApply;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Psr\Container\ContainerInterface;

class SettingsRenderer
{

    private $settings;
    private $state;
    private $fields;
    private $dccApplies;
    private $messagesApply;
    public function __construct(
        ContainerInterface $settings,
        State $state,
        array $fields,
        DccApplies $dccApplies,
        MessagesApply $messagesApply
    ) {

        $this->settings = $settings;
        $this->state = $state;
        $this->fields = $fields;
        $this->dccApplies = $dccApplies;
        $this->messagesApply = $messagesApply;
    }

    //phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
    public function renderMultiSelect($field, $key, $config, $value): string
    {

        if ($config['type'] !== 'ppcp-multiselect') {
            return $field;
        }

        $options = [];
        foreach ($config['options'] as $optionKey => $optionValue) {
            $selected = (in_array($optionKey, $value, true)) ? 'selected="selected"' : '';

            $options[] = '<option value="' . esc_attr($optionKey) . '" ' . $selected . '>' .
            esc_html($optionValue) .
            '</option>';
        }

        $html = sprintf(
            '<select
                        multiple
                         class="%s"
                         name="%s"
                     >%s</select>',
            esc_attr(implode(' ', $config['class'])),
            esc_attr($key) . '[]',
            implode('', $options)
        );

        return $html;
    }

    public function renderPassword($field, $key, $config, $value): string
    {

        if ($config['type'] !== 'ppcp-password') {
            return $field;
        }

        $html = sprintf(
            '<input
                        type="password"
                        autocomplete="new-password"
                        class="%s"
                        name="%s"
                        value="%s"
                     >',
            esc_attr(implode(' ', $config['class'])),
            esc_attr($key),
            esc_attr($value)
        );

        return $html;
    }

    public function renderTextInput($field, $key, $config, $value): string
    {

        if ($config['type'] !== 'ppcp-text-input') {
            return $field;
        }

        $html = sprintf(
            '<input
                        type="text"
                        autocomplete="off"
                        class="%s"
                        name="%s"
                        value="%s"
                     >',
            esc_attr(implode(' ', $config['class'])),
            esc_attr($key),
            esc_attr($value)
        );

        return $html;
    }

    public function renderHeading($field, $key, $config, $value): string
    {

        if ($config['type'] !== 'ppcp-heading') {
            return $field;
        }

        $html = sprintf(
            '<h3 class="%s">%s</h3>',
            esc_attr(implode(' ', $config['class'])),
            esc_html($config['heading'])
        );

        return $html;
    }
    //phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType

    //phpcs:disable Inpsyde.CodeQuality.NestingLevel.High
    //phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function render(bool $isDcc)
    {

        $nonce = wp_create_nonce(SettingsListener::NONCE);
        ?>
        <input type="hidden" name="ppcp-nonce" value="<?php echo esc_attr($nonce); ?>">
        <?php
        foreach ($this->fields as $field => $config) :
            if (! in_array($this->state->currentState(), $config['screens'], true)) {
                continue;
            }
            if ($isDcc && ! in_array($config['gateway'], ['all', 'dcc'], true)) {
                continue;
            }
            if (! $isDcc && ! in_array($config['gateway'], ['all', 'paypal'], true)) {
                continue;
            }
            if (
                in_array('dcc', $config['requirements'], true)
                && ! $this->dccApplies->forCountryCurrency()
            ) {
                continue;
            }
            if (
                in_array('messages', $config['requirements'], true)
                && ! $this->messagesApply->forCountry()
            ) {
                continue;
            }
            $value = $this->settings->has($field) ? $this->settings->get($field) : null;
            $key = 'ppcp[' . $field . ']';
            $id = 'ppcp-' . $field;
            $config['id'] = $id;
            $thTd = $config['type'] !== 'ppcp-heading' ? 'td' : 'th';
            $colspan = $config['type'] !== 'ppcp-heading' ? 1 : 2;

            ?>
        <tr valign="top" id="<?php echo esc_attr('field-' . $field); ?>">
            <?php if ($config['type'] !== 'ppcp-heading') : ?>
            <th>
                <label
                    for="<?php echo esc_attr($id); ?>"
                ><?php echo esc_html($config['title']); ?></label>
                <?php if (isset($config['desc_tip']) && $config['desc_tip']) : ?>
                <span
                        class="woocommerce-help-tip"
                        data-tip="<?php echo esc_attr($config['description']); ?>"
                ></span>
                    <?php unset($config['description']);
                endif; ?>
            </th>
            <?php endif; ?>
            <<?php echo esc_attr($thTd); ?> colspan="<?php echo (int) $colspan; ?>"><?php
                $config['type'] === 'ppcp-text' ?
                    $this->renderText($config)
                    : woocommerce_form_field($key, $config, $value); ?></<?php echo esc_attr($thTd); ?>>
        </tr>
        <?php endforeach;

        if ($isDcc) :
            ?>
        <tr>
            <th><?php esc_html_e('3D Secure', 'woocommerce-paypal-commerce-gateway'); ?></th>
            <td>
                <p>
                    <?php
                     /**
                      * @todo: Provide link to documentation.
                      */
                     echo wp_kses_post(
                         sprintf(
                             // translators: %1$s and %2$s is a link tag.
                             __(
                                 '3D Secure benefits cardholders and merchants by providing an additional
                                  layer of verification using Verified by Visa, MasterCard SecureCode and
                                  American Express SafeKey. %1$sLearn more about 3D Secure.%2$s',
                                 'woocommerce - paypal - commerce - gateway'
                             ),
                             '<a href = "#">',
                             '</a>'
                         )
                     ); ?>
                </p>
            </td>
        </tr>
            <?php
        endif;
    }
    //phpcs:enable Inpsyde.CodeQuality.NestingLevel.High

    private function renderText(array $config)
    {
        echo wp_kses_post($config['text']);
        if (isset($config['hidden'])) {
            $value = $this->settings->has($config['hidden']) ?
                (string) $this->settings->get($config['hidden'])
                : '';
            echo ' < input
                    type = "hidden"
                    name = "ppcp[' . esc_attr($config['hidden']) . ']"
                    value = "' . esc_attr($value) . '"
                    > ';
        }
    }
}