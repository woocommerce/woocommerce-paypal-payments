<?php
declare(strict_types=1);

define('TESTS_ROOT_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(TESTS_ROOT_DIR));

require_once TESTS_ROOT_DIR . '/inc/wp_functions.php';
require_once ROOT_DIR . '/vendor/autoload.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Payment_Gateway.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Payment_Gateway_CC.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Ajax.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Checkout.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Cart.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Integration.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Session.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Session_Handler.php';
require_once TESTS_ROOT_DIR . '/stubs/Task.php';
require_once TESTS_ROOT_DIR . '/stubs/DefaultPaymentGateways.php';
require_once TESTS_ROOT_DIR . '/stubs/NoteTraits.php';
require_once TESTS_ROOT_DIR . '/stubs/AbstractPaymentMethodType.php';
require_once TESTS_ROOT_DIR . '/stubs/ProductStatus.php';
require_once TESTS_ROOT_DIR . '/stubs/WP_Error.php';
require_once TESTS_ROOT_DIR . '/stubs/WP_REST_Request.php';
require_once TESTS_ROOT_DIR . '/stubs/WP_REST_Response.php';
require_once TESTS_ROOT_DIR . '/stubs/WP_REST_Controller.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_REST_Controller.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Product.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Coupon.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Discounts.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Countries.php';
require_once TESTS_ROOT_DIR . '/stubs/WC_Validation.php';
require_once TESTS_ROOT_DIR . '/stubs/AbilityDefinition.php';
require_once TESTS_ROOT_DIR . '/stubs/StepExporter.php';
require_once TESTS_ROOT_DIR . '/stubs/HasAlias.php';
require_once TESTS_ROOT_DIR . '/stubs/StepProcessor.php';
require_once TESTS_ROOT_DIR . '/stubs/Step.php';

Hamcrest\Util::registerGlobalFunctions();
