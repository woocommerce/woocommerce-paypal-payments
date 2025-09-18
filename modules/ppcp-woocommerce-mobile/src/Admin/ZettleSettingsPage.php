<?php
/**
 * Zettle Settings Admin Page
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile\Admin
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile\Admin;

use WooCommerce\PayPalCommerce\WooCommerceMobile\Zettle\ZettleOAuthClient;

/**
 * Class ZettleSettingsPage
 * 
 * Provides admin interface for Zettle OAuth configuration
 */
class ZettleSettingsPage {

    /**
     * The Zettle OAuth client.
     *
     * @var ZettleOAuthClient
     */
    private $zettle_client;

    /**
     * ZettleSettingsPage constructor.
     *
     * @param ZettleOAuthClient $zettle_client The Zettle OAuth client.
     */
    public function __construct( ZettleOAuthClient $zettle_client ) {
        $this->zettle_client = $zettle_client;
    }

    /**
     * Initialize the admin page.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting( 'zettle_settings', 'zettle_oauth_client_id', array(
            'sanitize_callback' => array( $this, 'sanitize_client_id' ),
        ) );
    }

    /**
     * Sanitize client ID input.
     *
     * @param string $value The client ID value to sanitize.
     * @return string Sanitized client ID.
     */
    public function sanitize_client_id( $value ) {
        // Client IDs should be alphanumeric with possible dashes and underscores
        $sanitized = sanitize_text_field( $value );
        
        // Validate format - typical Zettle client IDs are 32-character alphanumeric strings
        if ( ! empty( $sanitized ) && ! preg_match( '/^[a-zA-Z0-9_-]{16,64}$/', $sanitized ) ) {
            add_settings_error(
                'zettle_oauth_client_id',
                'invalid_client_id',
                __( 'Invalid client ID format. Please check your Zettle developer portal for the correct client ID.', 'woocommerce-paypal-payments' )
            );
            // Return empty string if invalid
            return '';
        }
        
        return $sanitized;
    }

    /**
     * Add admin menu item.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Zettle Settings', 'woocommerce-paypal-payments' ),
            __( 'Zettle', 'woocommerce-paypal-payments' ),
            'manage_woocommerce',
            'wc-zettle-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Handle OAuth callback redirect.
     */
    public function handle_oauth_callback() {
        // Check admin capabilities first
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'woocommerce-paypal-payments' ) );
        }

        // Check if we're on the callback URL
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wc-zettle-settings' ) {
            return;
        }

        if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
            return;
        }

        // Sanitize input parameters
        $code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

        // Verify state for CSRF protection
        $stored_state = get_transient( 'zettle_oauth_state' );
        if ( ! $stored_state || $stored_state !== $state ) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e( 'Invalid OAuth state. Please try authorizing again.', 'woocommerce-paypal-payments' ); ?></p>
                </div>
                <?php
            } );
            return;
        }

        // Exchange authorization code for tokens
        $result = $this->zettle_client->exchange_authorization_code( $code, $state );
        
        if ( is_wp_error( $result ) ) {
            add_action( 'admin_notices', function() use ( $result ) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo sprintf( __( 'Failed to authorize with Zettle: %s', 'woocommerce-paypal-payments' ), esc_html( $result->get_error_message() ) ); ?></p>
                </div>
                <?php
            } );
        } else {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e( 'Successfully connected to Zettle!', 'woocommerce-paypal-payments' ); ?></p>
                </div>
                <?php
            } );
        }

        // Clean up state
        delete_transient( 'zettle_oauth_state' );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        // Check if we have valid tokens
        $has_token = (bool) get_option( 'zettle_access_token' );
        $has_refresh = (bool) get_option( 'zettle_refresh_token' );
        $token_expires = get_option( 'zettle_token_expires', 0 );
        $client_id = get_option( 'zettle_oauth_client_id', '' );
        
        // Ensure token_expires is an integer
        $token_expires = is_numeric( $token_expires ) ? intval( $token_expires ) : 0;
        
        $is_connected = $has_token && $has_refresh;
        $is_expired = $token_expires && $token_expires < time();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php settings_errors(); ?>
            
            <div class="card">
                <h2><?php _e( 'Zettle Configuration', 'woocommerce-paypal-payments' ); ?></h2>
                
                <form method="post" action="options.php">
                    <?php settings_fields( 'zettle_settings' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="zettle_oauth_client_id">
                                    <?php _e( 'Zettle Client ID', 'woocommerce-paypal-payments' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="zettle_oauth_client_id" 
                                       name="zettle_oauth_client_id" 
                                       value="<?php echo esc_attr( $client_id ); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php _e( 'Get your Client ID from the Zettle Developer Portal', 'woocommerce-paypal-payments' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Save Settings', 'woocommerce-paypal-payments' ) ); ?>
                </form>
            </div>
            
            <div class="card">
                <h2><?php _e( 'Zettle Connection Status', 'woocommerce-paypal-payments' ); ?></h2>
                
                <?php if ( $is_connected ) : ?>
                    <p style="color: green;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e( 'Connected to Zettle', 'woocommerce-paypal-payments' ); ?>
                        <?php if ( $is_expired ) : ?>
                            <br><span style="color: orange;"><?php _e( '(Token expired - will refresh automatically)', 'woocommerce-paypal-payments' ); ?></span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ( $token_expires ) : ?>
                        <p><?php echo sprintf( __( 'Token expires: %s', 'woocommerce-paypal-payments' ), 
                            esc_html( gmdate( 'Y-m-d H:i:s', $token_expires ) ) ); ?></p>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field( 'zettle_disconnect', 'zettle_nonce' ); ?>
                        <input type="hidden" name="zettle_action" value="disconnect" />
                        <p class="submit">
                            <button type="submit" class="button button-secondary">
                                <?php _e( 'Disconnect from Zettle', 'woocommerce-paypal-payments' ); ?>
                            </button>
                        </p>
                    </form>
                    
                <?php else : ?>
                    <p style="color: red;">
                        <span class="dashicons dashicons-no-alt"></span>
                        <?php _e( 'Not connected to Zettle', 'woocommerce-paypal-payments' ); ?>
                    </p>
                    
                    <p><?php _e( 'Click the button below to authorize with Zettle and enable card reader support.', 'woocommerce-paypal-payments' ); ?></p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field( 'zettle_authorize', 'zettle_nonce' ); ?>
                        <input type="hidden" name="zettle_action" value="authorize" />
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e( 'Connect to Zettle', 'woocommerce-paypal-payments' ); ?>
                            </button>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2><?php _e( 'Setup Instructions', 'woocommerce-paypal-payments' ); ?></h2>
                <ol>
                    <li><?php _e( 'Ensure you have a Zettle developer account', 'woocommerce-paypal-payments' ); ?></li>
                    <li><?php _e( 'Make sure your Zettle app credentials are configured in the plugin', 'woocommerce-paypal-payments' ); ?></li>
                    <li><?php _e( 'Click "Connect to Zettle" to authorize', 'woocommerce-paypal-payments' ); ?></li>
                    <li><?php _e( 'The mobile app will now be able to use PayPal card readers', 'woocommerce-paypal-payments' ); ?></li>
                </ol>
                
                <h3><?php _e( 'Debug Information', 'woocommerce-paypal-payments' ); ?></h3>
                <ul>
                    <li>Has Access Token: <?php echo $has_token ? 'Yes' : 'No'; ?></li>
                    <li>Has Refresh Token: <?php echo $has_refresh ? 'Yes' : 'No'; ?></li>
                    <li>Token Expires: <?php echo $token_expires ? gmdate( 'Y-m-d H:i:s', $token_expires ) : 'Not set'; ?></li>
                    <li>Current Time: <?php echo gmdate( 'Y-m-d H:i:s', time() ); ?></li>
                </ul>
            </div>
        </div>
        <?php
        
        // Handle form submissions
        if ( isset( $_POST['zettle_action'] ) && isset( $_POST['zettle_nonce'] ) ) {
            if ( $_POST['zettle_action'] === 'authorize' && wp_verify_nonce( $_POST['zettle_nonce'], 'zettle_authorize' ) ) {
                $this->start_authorization();
            } elseif ( $_POST['zettle_action'] === 'disconnect' && wp_verify_nonce( $_POST['zettle_nonce'], 'zettle_disconnect' ) ) {
                $this->disconnect();
            }
        }
    }

    /**
     * Start the authorization process.
     */
    private function start_authorization() {
        $auth_url = $this->zettle_client->get_authorization_url();
        
        if ( is_wp_error( $auth_url ) ) {
            wp_die( 'Failed to generate authorization URL: ' . $auth_url->get_error_message() );
        }
        
        // Store state for CSRF protection
        $state = $auth_url['state'];
        set_transient( 'zettle_oauth_state', $state, 600 ); // 10 minutes
        
        // Redirect to Zettle authorization page
        wp_redirect( $auth_url['url'] );
        exit;
    }

    /**
     * Disconnect from Zettle.
     */
    private function disconnect() {
        $this->zettle_client->clear_tokens();
        
        // Redirect back to settings page with success message
        wp_redirect( add_query_arg( array(
            'page' => 'wc-zettle-settings',
            'disconnected' => '1',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }
}