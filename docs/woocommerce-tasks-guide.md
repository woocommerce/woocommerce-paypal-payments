# WooCommerce Tasks and Todos Development Guide

This guide explains how to create and manage task items that appear in WooCommerce's "Things to do next" section, as well as in the "Things to do next" section of the plugin’s overview tab. The PayPal Payments plugin uses two distinct systems to cover different use cases.

## Overview

The plugin uses two separate task systems:

1. **WooCommerce Native Tasks System** - Integrates with WooCommerce's built-in task list
2. **Plugin's React-Based Todos System** - Custom implementation for the PayPal settings interface

## WooCommerce Native Tasks System

### Architecture

Native WooCommerce tasks are managed through the `TaskRegistrar` system and appear in WooCommerce's main admin "Things to do next" section.

### Implementation

Tasks are registered via the `register_wc_tasks` method in `WCGatewayModule.php`, which:

1. Hooks into the `init` action for proper timing
2. Retrieves simple redirect tasks from the container
3. Uses the `TaskRegistrar` to register tasks with the 'extended' list
4. Includes error handling and logging for registration failures

```php
protected function register_wc_tasks( ContainerInterface $container ): void {
    add_action(
        'init',
        static function () use ( $container ): void {
            $logger = $container->get( 'woocommerce.logger.woocommerce' );
            try {
                $simple_redirect_tasks = $container->get( 'wcgateway.settings.wc-tasks.simple-redirect-tasks' );
                if ( empty( $simple_redirect_tasks ) ) {
                    return;
                }

                $task_registrar = $container->get( 'wcgateway.settings.wc-tasks.task-registrar' );
                $task_registrar->register( 'extended', $simple_redirect_tasks );
            } catch ( Exception $exception ) {
                $logger->error( "Failed to create a task in the 'Things to do next' section of WC. " . $exception->getMessage() );
            }
        },
    );
}
```

### Task Configuration

Tasks are defined in `services.php` as service definitions. Each task configuration includes:

- `id`: Unique identifier for the task
- `title`: Display title in the task list
- `description`: Explanatory text for what the task accomplishes
- `redirect_url`: URL where users are taken when they click the task

```php
// Example: Pay Later messaging configuration task
'wcgateway.settings.wc-tasks.pay-later-task-config' => static function( ContainerInterface $container ): array {
    $section_id = Settings::CONNECTION_TAB_ID;
    $pay_later_tab_id = Settings::PAY_LATER_TAB_ID;

    if ( $container->has( 'paylater-configurator.is-available' ) && $container->get( 'paylater-configurator.is-available' ) ) {
        return array(
            array(
                'id'           => 'pay-later-messaging-task',
                'title'        => __( 'Configure PayPal Pay Later messaging', 'woocommerce-paypal-payments' ),
                'description'  => __( 'Decide where you want dynamic Pay Later messaging to show up and how you want it to look on your site.', 'woocommerce-paypal-payments' ),
                'redirect_url' => admin_url( "admin.php?page=wc-settings&tab=checkout&section={$section_id}&ppcp-tab={$pay_later_tab_id}" ),
            ),
        );
    }
    return array();
},
```

### Registration Process

The `TaskRegistrar` class handles task registration through the `register()` method:

```php
public function register( string $list_id, array $tasks ): void {
    $task_lists = TaskLists::get_lists();
    if ( ! isset( $task_lists[ $list_id ] ) ) {
        return;
    }

    foreach ( $tasks as $task ) {
        $added_task = TaskLists::add_task( $list_id, $task );
        if ( $added_task instanceof WP_Error ) {
            throw new RuntimeException( $added_task->get_error_message() );
        }
    }
}
```

The registration process:
- Validates the target task list exists
- Iterates through task definitions
- Uses WooCommerce's `TaskLists::add_task()` API
- Handles registration errors with exceptions

## Plugin's React-Based Todos System

### Architecture

The custom todos system provides more advanced functionality and appears specifically in the PayPal Payments settings Overview tab.

### Components

1. **Backend Definition** - `TodosDefinition.php` contains todo configurations
2. **REST API Endpoint** - `TodosRestEndpoint.php` handles CRUD operations
3. **React Frontend** - `Todos.js` renders the user interface

### Todo Configuration

Each todo item in `TodosDefinition.php` requires the following properties:

```php
public function get(): array {
    $eligibility_checks = $this->eligibilities->get_eligibility_checks();

    $todo_items = array(
        'enable_fastlane' => array(
            'title'       => __( 'Enable Fastlane', 'woocommerce-paypal-payments' ),
            'description' => __( 'Accelerate your guest checkout with Fastlane by PayPal', 'woocommerce-paypal-payments' ),
            'isEligible'  => $eligibility_checks['enable_fastlane'],
            'action'      => array(
                'type'      => 'tab',
                'tab'       => 'payment_methods', 
                'section'   => 'ppcp-axo-gateway',
                'highlight' => 'ppcp-axo-gateway',
            ),
            'priority'    => 1,
        ),
        'enable_pay_later_messaging' => array(
            'title'       => __( 'Enable Pay Later messaging', 'woocommerce-paypal-payments' ),
            'description' => __( 'Show Pay Later options to increase conversion.', 'woocommerce-paypal-payments' ),
            'isEligible'  => $eligibility_checks['enable_pay_later_messaging'],
            'action'      => array(
                'type'      => 'tab',
                'tab'       => 'pay_later',
                'section'   => 'pay-later-messaging',
            ),
            'priority'    => 2,
        ),
        // Additional todo items...
    );

    return $todo_items;
}
```

### Advanced Features

The React-based system supports:

- **Eligibility Checks**: Dynamic visibility based on conditions
- **Dismissal**: Users can dismiss individual todos
- **Completion Tracking**: Automatic removal when tasks are completed
- **Priority Ordering**: Control display order with priority values
- **REST API**: Full CRUD operations via dedicated endpoints

### API Integration

The REST API provides endpoints for managing todos:

```php
public function register_routes(): void {
    // GET/POST /todos - Get todos list and update dismissed todos
    register_rest_route(
        static::NAMESPACE,
        '/' . $this->rest_base,
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_todos' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_todos' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        )
    );

    // POST /todos/reset - Reset dismissed todos
    register_rest_route(
        static::NAMESPACE,
        '/' . $this->rest_base . '/reset',
        array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'reset_todos' ),
            'permission_callback' => array( $this, 'check_permission' ),
        )
    );
}
```

The endpoints handle:
- **GET /todos**: Fetching current todo list with eligibility filtering
- **POST /todos**: Updating dismissed todo status
- **POST /todos/reset**: Restoring all dismissed todos

### Frontend Rendering

The React component provides a complete todo management interface:

```jsx
const Todos = ( { todos, resetTodos, dismissTodo } ) => {
    const [ isResetting, setIsResetting ] = useState( false );
    const [ activeModal, setActiveModal ] = useState( null );

    // Reset handler for restoring dismissed todos
    const resetHandler = useCallback( async () => {
        setIsResetting( true );
        try {
            await resetTodos();
        } finally {
            setIsResetting( false );
        }
    }, [ resetTodos ] );

    if ( ! todos?.length ) {
        return null;
    }

    return (
        <SettingsCard
            className="ppcp-r-tab-overview-todo"
            title={ __( 'Things to do next', 'woocommerce-paypal-payments' ) }
            description={
                <>
                    <p>
                        { __(
                            'Complete these tasks to keep your store updated with the latest products and services.',
                            'woocommerce-paypal-payments'
                        ) }
                    </p>
                    <Button
                        variant="tertiary"
                        onClick={ resetHandler }
                        disabled={ isResetting }
                    >
                        <Icon icon={ reusableBlock } size={ 18 } />
                        { isResetting
                            ? __( 'Restoring…', 'woocommerce-paypal-payments' )
                            : __( 'Restore dismissed Things To Do', 'woocommerce-paypal-payments' ) }
                    </Button>
                </>
            }
        >
            <TodoSettingsBlock
                todosData={ todos }
                setActiveModal={ setActiveModal }
                onDismissTodo={ dismissTodo }
            />
        </SettingsCard>
    );
};
```

Key features:
- **Restore Functionality**: Users can restore dismissed todos
- **Modal Integration**: Support for detailed todo actions
- **Dismissal Handling**: Individual todo dismissal with state management
- **Loading States**: Visual feedback during operations

## Best Practices

### For WooCommerce Native Tasks

1. **Keep task definitions simple** - Use basic configuration only
2. **Provide clear redirect URLs** - Direct users to relevant settings
3. **Use descriptive IDs** - Include plugin prefix for uniqueness
4. **Test with WooCommerce updates** - Ensure compatibility with core changes

### For Plugin React Todos

1. **Implement robust eligibility checks** - Prevent showing irrelevant todos
2. **Use appropriate priority values** - Ensure logical ordering
3. **Provide actionable descriptions** - Help users understand next steps
4. **Handle edge cases** - Account for various plugin states
5. **Test dismissal functionality** - Ensure proper state management

## Existing Examples

Both systems have multiple implementations in the codebase:
- Onboarding completion tasks
- Feature enablement todos
- Configuration reminder items
- Migration assistance tasks

These examples demonstrate various conditional logic patterns and user experience flows.
