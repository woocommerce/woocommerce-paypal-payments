<?php
if (!class_exists('WP_Error')) {
	/**
	 * Stub for WordPress Error
	 */
	class WP_Error {
		protected $errors = array();
		protected $error_data = array();

		public function __construct($code = '', $message = '', $data = '') {
			if (!empty($code)) {
				$this->errors[$code][] = $message;
			}
			if (!empty($data)) {
				$this->error_data[$code] = $data;
			}
		}

		public function get_error_code() {
			$codes = $this->get_error_codes();
			return empty($codes) ? '' : $codes[0];
		}

		public function get_error_codes() {
			return array_keys($this->errors);
		}

		public function get_error_message($code = '') {
			if (empty($code)) {
				$code = $this->get_error_code();
			}
			$messages = $this->get_error_messages($code);
			return empty($messages) ? '' : $messages[0];
		}

		public function get_error_messages($code = '') {
			if (empty($code)) {
				return array_reduce($this->errors, 'array_merge', array());
			}
			return isset($this->errors[$code]) ? $this->errors[$code] : array();
		}
	}
}

if (!class_exists('WP_REST_Request')) {
	/**
	 * Stub for WordPress REST Request
	 */
	class WP_REST_Request {
		protected $params = array();

		public function get_param($key) {
			return isset($this->params[$key]) ? $this->params[$key] : null;
		}

		public function get_params() {
			return $this->params;
		}

		public function set_param($key, $value) {
			$this->params[$key] = $value;
		}
	}
}

if (!class_exists('WP_REST_Response')) {
	/**
	 * Stub for WordPress REST Response
	 */
	class WP_REST_Response {
		protected $data;
		protected $status;

		public function __construct($data = null, $status = 200) {
			$this->data = $data;
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status() {
			return $this->status;
		}
	}
}

if (!class_exists('WP_REST_Controller')) {
	/**
	 * Stub for WordPress REST Controller
	 */
	abstract class WP_REST_Controller {
		/**
		 * The namespace of this controller's route.
		 *
		 * @var string
		 */
		protected $namespace;

		/**
		 * The base of this controller's route.
		 *
		 * @var string
		 */
		protected $rest_base;

		/**
		 * Registers the routes for the objects of the controller.
		 */
		public function register_routes() {}

		/**
		 * Checks if a given request has access to get items.
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|bool
		 */
		public function get_items_permissions_check($request) {
			return true;
		}

		/**
		 * Retrieves a collection of items.
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_items($request) {
			return new WP_REST_Response(array(), 200);
		}

		/**
		 * Checks if a given request has access to get a specific item.
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|bool
		 */
		public function get_item_permissions_check($request) {
			return true;
		}

		/**
		 * Retrieves one item from the collection.
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_item($request) {
			return new WP_REST_Response(array(), 200);
		}

		/**
		 * Retrieves the item's schema.
		 *
		 * @return array
		 */
		public function get_item_schema() {
			return array();
		}
	}
}

if (!class_exists('WC_REST_Controller')) {
	/**
	 * Stub for WooCommerce REST Controller
	 */
	abstract class WC_REST_Controller extends WP_REST_Controller {
		/**
		 * Endpoint namespace.
		 *
		 * @var string
		 */
		protected $namespace = 'wc/v3';

		/**
		 * Route base.
		 *
		 * @var string
		 */
		protected $rest_base = '';

		/**
		 * Get normalized rest base.
		 *
		 * @return string
		 */
		protected function get_normalized_rest_base() {
			return preg_replace('/\(.*\)\//i', '', $this->rest_base);
		}

		/**
		 * Check batch limit.
		 *
		 * @param array $items Request items.
		 * @return bool|WP_Error
		 */
		protected function check_batch_limit($items) {
			$limit = apply_filters('woocommerce_rest_batch_items_limit', 100, $this->get_normalized_rest_base());
			$total = 0;

			if (!empty($items['create'])) {
				$total += count($items['create']);
			}
			if (!empty($items['update'])) {
				$total += count($items['update']);
			}
			if (!empty($items['delete'])) {
				$total += count($items['delete']);
			}

			if ($total > $limit) {
				return new WP_Error(
					'woocommerce_rest_request_entity_too_large',
					sprintf(__('Unable to accept more than %s items for this request.', 'woocommerce'), $limit),
					array('status' => 413)
				);
			}

			return true;
		}

		/**
		 * Add meta query.
		 *
		 * @param array $args       Query args.
		 * @param array $meta_query Meta query.
		 * @return array
		 */
		protected function add_meta_query($args, $meta_query) {
			if (empty($args['meta_query'])) {
				$args['meta_query'] = array();
			}

			$args['meta_query'][] = $meta_query;

			return $args;
		}

		/**
		 * Get the batch schema.
		 *
		 * @return array
		 */
		public function get_public_batch_schema() {
			return array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'batch',
				'type'       => 'object',
				'properties' => array(
					'create' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'object',
						),
					),
					'update' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'object',
						),
					),
					'delete' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
			);
		}
	}
}




