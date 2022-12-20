<?php
namespace Automattic\WooCommerce\Blocks\Integrations {
	/**
	 * Integration.Interface
	 *
	 * Integrations must use this interface when registering themselves with blocks,
	 */
	interface IntegrationInterface
	{
		/**
		 * The name of the integration.
		 *
		 * @return string
		 */
		public function get_name();

		/**
		 * When called invokes any initialization/setup for the integration.
		 */
		public function initialize();

		/**
		 * Returns an array of script handles to enqueue in the frontend context.
		 *
		 * @return string[]
		 */
		public function get_script_handles();

		/**
		 * Returns an array of script handles to enqueue in the editor context.
		 *
		 * @return string[]
		 */
		public function get_editor_script_handles();

		/**
		 * An array of key, value pairs of data made available to the block on the client side.
		 *
		 * @return array
		 */
		public function get_script_data();
	}
}

namespace Automattic\WooCommerce\Blocks\Payments {
	use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

	interface PaymentMethodTypeInterface extends IntegrationInterface
	{
		/**
		 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
		 *
		 * @return boolean
		 */
		public function is_active();

		/**
		 * Returns an array of script handles to enqueue for this payment method in
		 * the frontend context
		 *
		 * @return string[]
		 */
		public function get_payment_method_script_handles();

		/**
		 * Returns an array of script handles to enqueue for this payment method in
		 * the admin context
		 *
		 * @return string[]
		 */
		public function get_payment_method_script_handles_for_admin();

		/**
		 * An array of key, value pairs of data made available to payment methods
		 * client side.
		 *
		 * @return array
		 */
		public function get_payment_method_data();

		/**
		 * Get array of supported features.
		 *
		 * @return string[]
		 */
		public function get_supported_features();
	}
}

namespace Automattic\WooCommerce\Blocks\Payments\Integrations
{
	use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;

	/**
	 * AbstractPaymentMethodType class.
	 *
	 * @since 2.6.0
	 */
	abstract class AbstractPaymentMethodType implements PaymentMethodTypeInterface
	{
		/**
		 * Payment method name defined by payment methods extending this class.
		 *
		 * @var string
		 */
		protected $name = '';

		/**
		 * Settings from the WP options table
		 *
		 * @var array
		 */
		protected $settings = [];

		/**
		 * Get a setting from the settings array if set.
		 *
		 * @param string $name Setting name.
		 * @param mixed $default Value that is returned if the setting does not exist.
		 * @return mixed
		 */
		protected function get_setting($name, $default = '')
		{
		}

		/**
		 * Returns the name of the payment method.
		 */
		public function get_name()
		{
		}

		/**
		 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
		 *
		 * @return boolean
		 */
		public function is_active()
		{
		}

		/**
		 * Returns an array of script handles to enqueue for this payment method in
		 * the frontend context
		 *
		 * @return string[]
		 */
		public function get_payment_method_script_handles()
		{
		}

		/**
		 * Returns an array of script handles to enqueue for this payment method in
		 * the admin context
		 *
		 * @return string[]
		 */
		public function get_payment_method_script_handles_for_admin()
		{
		}

		/**
		 * Returns an array of supported features.
		 *
		 * @return string[]
		 */
		public function get_supported_features()
		{
		}

		/**
		 * An array of key, value pairs of data made available to payment methods
		 * client side.
		 *
		 * @return array
		 */
		public function get_payment_method_data()
		{
		}

		/**
		 * Returns an array of script handles to enqueue in the frontend context.
		 *
		 * Alias of get_payment_method_script_handles. Defined by IntegrationInterface.
		 *
		 * @return string[]
		 */
		public function get_script_handles()
		{
		}

		/**
		 * Returns an array of script handles to enqueue in the admin context.
		 *
		 * Alias of get_payment_method_script_handles_for_admin. Defined by IntegrationInterface.
		 *
		 * @return string[]
		 */
		public function get_editor_script_handles()
		{
		}

		/**
		 * An array of key, value pairs of data made available to the block on the client side.
		 *
		 * Alias of get_payment_method_data. Defined by IntegrationInterface.
		 *
		 * @return array
		 */
		public function get_script_data()
		{
		}
	}
}

namespace Automattic\WooCommerce\Blocks\Integrations {
	/**
	 * Class used for tracking registered integrations with various Block types.
	 */
	class IntegrationRegistry
	{
		/**
		 * Integration identifier is used to construct hook names and is given when the integration registry is initialized.
		 *
		 * @var string
		 */
		protected $registry_identifier = '';

		/**
		 * Registered integrations, as `$name => $instance` pairs.
		 *
		 * @var IntegrationInterface[]
		 */
		protected $registered_integrations = [];

		/**
		 * Initializes all registered integrations.
		 *
		 * Integration identifier is used to construct hook names and is given when the integration registry is initialized.
		 *
		 * @param string $registry_identifier Identifier for this registry.
		 */
		public function initialize($registry_identifier = '')
		{
		}

		/**
		 * Registers an integration.
		 *
		 * @param IntegrationInterface $integration An instance of IntegrationInterface.
		 *
		 * @return boolean True means registered successfully.
		 */
		public function register(IntegrationInterface $integration)
		{
		}

		/**
		 * Checks if an integration is already registered.
		 *
		 * @param string $name Integration name.
		 * @return bool True if the integration is registered, false otherwise.
		 */
		public function is_registered($name)
		{
		}

		/**
		 * Un-register an integration.
		 *
		 * @param string|IntegrationInterface $name Integration name, or alternatively a IntegrationInterface instance.
		 * @return boolean|IntegrationInterface Returns the unregistered integration instance if unregistered successfully.
		 */
		public function unregister($name)
		{
		}

		/**
		 * Retrieves a registered Integration by name.
		 *
		 * @param string $name Integration name.
		 * @return IntegrationInterface|null The registered integration, or null if it is not registered.
		 */
		public function get_registered($name)
		{
		}

		/**
		 * Retrieves all registered integrations.
		 *
		 * @return IntegrationInterface[]
		 */
		public function get_all_registered()
		{
		}

		/**
		 * Gets an array of all registered integration's script handles for the editor.
		 *
		 * @return string[]
		 */
		public function get_all_registered_editor_script_handles()
		{
		}

		/**
		 * Gets an array of all registered integration's script handles.
		 *
		 * @return string[]
		 */
		public function get_all_registered_script_handles()
		{
		}

		/**
		 * Gets an array of all registered integration's script data.
		 *
		 * @return array
		 */
		public function get_all_registered_script_data()
		{
		}
	}
}

namespace Automattic\WooCommerce\Blocks\Payments {
	use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;

	/**
	 * Class used for interacting with payment method types.
	 *
	 * @since 2.6.0
	 */
	final class PaymentMethodRegistry extends IntegrationRegistry
	{
		/**
		 * Integration identifier is used to construct hook names and is given when the integration registry is initialized.
		 *
		 * @var string
		 */
		protected $registry_identifier = 'payment_method_type';

		/**
		 * Retrieves all registered payment methods that are also active.
		 *
		 * @return PaymentMethodTypeInterface[]
		 */
		public function get_all_active_registered()
		{
		}

		/**
		 * Gets an array of all registered payment method script handles, but only for active payment methods.
		 *
		 * @return string[]
		 */
		public function get_all_active_payment_method_script_dependencies()
		{
		}

		/**
		 * Gets an array of all registered payment method script data, but only for active payment methods.
		 *
		 * @return array
		 */
		public function get_all_registered_script_data()
		{
		}
	}
}
