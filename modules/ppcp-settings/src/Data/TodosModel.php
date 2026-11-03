<?php

/**
 * Todos details class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

/**
 * Class TodosModel
 *
 * Handles todos data persistence and state management.
 */
class TodosModel extends \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
{
    /**
     * Option key for WordPress storage.
     */
    protected const OPTION_KEY = 'ppcp-settings';
    /**
     * Returns the default structure for settings data.
     *
     * @return array
     */
    protected function get_defaults(): array
    {
        return array('dismissedTodos' => array(), 'completedOnClickTodos' => array());
    }
    /**
     * Gets the dismissed todos.
     *
     * @return array
     */
    public function get_dismissed_todos(): array
    {
        return $this->data['dismissedTodos'] ?? array();
    }
    /**
     * Gets the completed onclick todos.
     *
     * @return array
     */
    public function get_completed_onclick_todos(): array
    {
        return $this->data['completedOnClickTodos'] ?? array();
    }
    /**
     * Updates dismissed todos.
     *
     * @param array $todo_ids Array of todo IDs to mark as dismissed.
     */
    public function update_dismissed_todos(array $todo_ids): void
    {
        $this->data['dismissedTodos'] = array_unique($todo_ids);
        $this->save();
    }
    /**
     * Updates completed onclick todos.
     *
     * @param array $todo_ids Array of todo IDs to mark as completed.
     */
    public function update_completed_onclick_todos(array $todo_ids): void
    {
        $this->data['completedOnClickTodos'] = array_unique($todo_ids);
        $this->save();
    }
    /**
     * Resets dismissed todos.
     */
    public function reset_dismissed_todos(): void
    {
        $this->data['dismissedTodos'] = array();
        $this->save();
    }
    /**
     * Resets completed onclick todos.
     */
    public function reset_completed_onclick_todos(): void
    {
        $this->data['completedOnClickTodos'] = array();
        $this->save();
    }
    /**
     * Gets current todos data including dismissed and completed states.
     *
     * @return array The todos data array.
     */
    public function get_todos_data(): array
    {
        return array('dismissedTodos' => $this->get_dismissed_todos(), 'completedOnClickTodos' => $this->get_completed_onclick_todos());
    }
}
