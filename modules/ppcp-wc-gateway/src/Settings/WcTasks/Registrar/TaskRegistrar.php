<?php

/**
 * Registers the tasks inside the "Things to do next" WC section.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Registrar;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use RuntimeException;
use WP_Error;
/**
 * Registers the tasks inside the "Things to do next" WC section.
 */
class TaskRegistrar implements \WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Registrar\TaskRegistrarInterface
{
    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException If problem registering.
     */
    public function register(string $list_id, array $tasks): void
    {
        $task_lists = TaskLists::get_lists();
        if (!isset($task_lists[$list_id])) {
            return;
        }
        foreach ($tasks as $task) {
            $added_task = TaskLists::add_task($list_id, $task);
            if ($added_task instanceof WP_Error) {
                throw new RuntimeException($added_task->get_error_message());
            }
        }
    }
}
