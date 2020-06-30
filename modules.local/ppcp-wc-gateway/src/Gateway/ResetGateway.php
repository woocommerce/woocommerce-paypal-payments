<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;


use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;
use Inpsyde\PayPalCommerce\AdminNotices\Repository\Repository;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

class ResetGateway
{

    private const NONCE = 'ppcp-reset';
    private $settings;
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function listen() : bool {
        if (isset($_GET['ppcp-reset'])) {
            return $this->reset();
        }
        if (isset($_GET['ppcp-resetted'])) {
            return $this->resetted();
        }
        return false;
    }
    private function resetted() : bool {
        add_filter(
            Repository::NOTICES_FILTER,
            function(array $notices) : array {
                $notices[] = new Message(
                        __('Your PayPal settings have been resetted.', 'woocommerce-paypal-commerce-gateway'),
                    'success'
                );
                return $notices;
            }
        );

        return true;
    }

    private function reset() : bool {

        if (
            ! isset($_GET['nonce'] )
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), self::NONCE)
            || ! current_user_can('manage_options')
        ) {
            return false;
        }

        $this->settings->reset();
        $url = remove_query_arg([
            'nonce',
            'ppcp-reset',
        ]);
        $url = add_query_arg([
            'ppcp-resetted' => 1,
        ],
            $url
        );

        wp_redirect($url, 302);
        exit;
    }

    public function render() {

        $url = add_query_arg([
            'ppcp-reset' => 1,
            'nonce' => wp_create_nonce(self::NONCE),
        ]);
        ?>

        <tr valign="top">
            <th scope="row" class="titledesc">
            </th>
            <td class="forminp">
                <a
                    class="button"
                    href="<?php echo esc_url($url); ?>"
                ><?php
                    esc_html_e('Reset', 'woocommerce-paypal-commerce-gateway');
                    ?></a>
            </td>
        </tr>
        <?php
    }
}