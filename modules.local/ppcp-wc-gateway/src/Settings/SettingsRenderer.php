<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\Onboarding\State;
use Psr\Container\ContainerInterface;

class SettingsRenderer
{

    private $settings;
    private $state;
    private $fields;
    public function __construct(
        ContainerInterface $settings,
        State $state,
        array $fields
    ) {

        $this->settings = $settings;
        $this->state = $state;
        $this->fields = $fields;
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
            implode(' ', $config['class']),
            $key . '[]',
            implode('', $options)
        );

        return $html;
    }
    //phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType

    public function render()
    {

        $nonce = wp_create_nonce(SettingsListener::NONCE);
        ?>
        <input type="hidden" name="ppcp-nonce" value="<?php echo esc_attr($nonce); ?>">
        <?php
        foreach ($this->fields as $field => $config) :
            if (! in_array($this->state->currentState(), $config['screens'], true)) {
                continue;
            }
            $value = $this->settings->has($field) ? $this->settings->get($field) : null;
            $id = 'ppcp[' . $field . ']';
            ?>
        <tr valign="top">
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
            <td><?php
                $config['type'] === 'ppcp-text' ?
                    $this->renderText($config)
                    : woocommerce_form_field($id, $config, $value); ?></td>
        </tr>
        <?php endforeach;
    }

    private function renderText(array $config)
    {
        echo wp_kses_post($config['text']);
        if (isset($config['hidden'])) {
            $value = $this->settings->has($config['hidden']) ?
                (string) $this->settings->get($config['hidden'])
                : '';
            echo '<input
             type="hidden"
             name="ppcp[' . esc_attr($config['hidden']) . ']"
             value="' . esc_attr($value) . '"
             >';
        }
    }
}