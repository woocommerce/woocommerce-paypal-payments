<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\TestAgent;

use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
/**
 * Registers and renders the "Test Agent" tab in WooCommerce Status page.
 * This provides a testing interface for the agentic commerce API.
 */
class TestAgentPage
{
    /**
     * The module URL.
     *
     * @var string
     */
    private string $module_url;
    /**
     * The plugin path.
     *
     * @var string
     */
    private string $plugin_path;
    /**
     * The connection state service.
     *
     * @var ConnectionState
     */
    private ConnectionState $connection_state;
    /**
     * Constructor.
     *
     * @param string          $module_url       The module URL.
     * @param string          $plugin_path      The plugin path.
     * @param ConnectionState $connection_state The connection state service.
     */
    public function __construct(string $module_url, string $plugin_path, ConnectionState $connection_state)
    {
        $this->module_url = $module_url;
        $this->plugin_path = $plugin_path;
        $this->connection_state = $connection_state;
    }
    /**
     * Initialize the Test Agent tab.
     *
     * @return void
     */
    public function init(): void
    {
        // Register the new tab.
        add_filter('woocommerce_admin_status_tabs', fn($tabs) => $this->add_tab($tabs), 100);
        // Render content when tab is active.
        add_action('woocommerce_admin_status_content_test-agent', fn() => $this->render());
    }
    /**
     * Add "Test Agent" tab to WooCommerce Status tabs.
     *
     * @param array $tabs Existing tabs.
     * @return array Modified tabs.
     */
    private function add_tab(array $tabs): array
    {
        $tabs['test-agent'] = __('Test Agent', 'woocommerce-paypal-payments');
        return $tabs;
    }
    /**
     * Render the Test Agent page content.
     *
     * @return void
     */
    public function render(): void
    {
        if (!$this->connection_state->is_sandbox()) {
            $this->render_production_warning();
            return;
        }
        $this->enqueue_assets();
        ?>
		<div class="wrap">
			<h2><?php 
        esc_html_e('PayPal Agentic Commerce - Test Agent', 'woocommerce-paypal-payments');
        ?></h2>
			<p class="description">
				<?php 
        esc_html_e('Test the agentic commerce API by simulating AI agent interactions. This tool uses a browser-based AI model to interact with the real WooCommerce and PayPal APIs.', 'woocommerce-paypal-payments');
        ?>
			</p>
			<div id="ppcp-dummy-agent-root"></div>
		</div>
		<?php 
    }
    /**
     * Render production warning when not connected to sandbox.
     *
     * @return void
     */
    private function render_production_warning(): void
    {
        ?>
		<div class="wrap">
			<h2><?php 
        esc_html_e('PayPal Agentic Commerce - Test Agent', 'woocommerce-paypal-payments');
        ?></h2>
			<div class="notice notice-warning">
				<p><?php 
        esc_html_e('Test Agent is only available when connected to PayPal sandbox. Please connect to sandbox mode to use this testing tool.', 'woocommerce-paypal-payments');
        ?></p>
			</div>
		</div>
		<?php 
    }
    /**
     * Enqueue JavaScript and CSS assets.
     *
     * @return void
     */
    private function enqueue_assets(): void
    {
        $asset_file = $this->plugin_path . 'modules/ppcp-agentic-commerce/assets/dummy-agent.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : array('dependencies' => array(), 'version' => '1.0.0');
        wp_enqueue_script('ppcp-dummy-agent', $this->module_url . 'assets/dummy-agent.js', $asset['dependencies'], $asset['version'], \true);
        wp_enqueue_style('ppcp-dummy-agent', $this->module_url . 'assets/dummy-agent.css', array(), $asset['version']);
        wp_localize_script('ppcp-dummy-agent', 'ppcpDummyAgent', array('agenticUrl' => rest_url('wc/v3/agentic'), 'productsUrl' => rest_url('wc/v3/products'), 'nonce' => wp_create_nonce('wp_rest'), 'currency' => get_woocommerce_currency(), 'isSandbox' => \true, 'testScenarios' => apply_filters('woocommerce_paypal_payments_agentic_commerce_test_scenarios', array())));
    }
}
