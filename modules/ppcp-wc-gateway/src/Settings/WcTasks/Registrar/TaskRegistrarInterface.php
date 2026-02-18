<?php

/**
 * Responsible for registering the tasks inside the "Things to do next" WC section.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Registrar;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;
use RuntimeException;
interface TaskRegistrarInterface
{
    /**
     * Registers the tasks inside the section with given list ID.
     *
     * @param string $list_id The list ID.
     * @param Task[] $tasks The list of tasks.
     * @return void
     * @throws RuntimeException If problem registering.
     */
    public function register(string $list_id, array $tasks): void;
}
