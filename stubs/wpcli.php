<?php

/**
 * Various utilities for WP-CLI commands.
 */
class WP_CLI {
	/**
	 * Set the logger instance.
	 *
	 * @param object $logger Logger instance to set.
	 */
	public static function set_logger( $logger ) {
	}

	/**
	 * Get the logger instance.
	 *
	 * @return object $logger Logger instance.
	 */
	public static function get_logger() {
	}

	/**
	 * Get the Configurator instance
	 *
	 * @return Configurator
	 */
	public static function get_configurator() {
	}

	public static function get_root_command() {
	}

	public static function get_runner() {
	}

	/**
	 * @return FileCache
	 */
	public static function get_cache() {
	}

	/**
	 * Set the context in which WP-CLI should be run
	 */
	public static function set_url( $url ) {
	}

	/**
	 * @return WpHttpCacheManager
	 */
	public static function get_http_cache_manager() {
	}

	/**
	 * Colorize a string for output.
	 *
	 * Yes, you can change the color of command line text too. For instance,
	 * here's how `WP_CLI::success()` colorizes "Success: "
	 *
	 * ```
	 * WP_CLI::colorize( "%GSuccess:%n " )
	 * ```
	 *
	 * Uses `\cli\Colors::colorize()` to transform color tokens to display
	 * settings. Choose from the following tokens (and note 'reset'):
	 *
	 * * %y => ['color' => 'yellow'],
	 * * %g => ['color' => 'green'],
	 * * %b => ['color' => 'blue'],
	 * * %r => ['color' => 'red'],
	 * * %p => ['color' => 'magenta'],
	 * * %m => ['color' => 'magenta'],
	 * * %c => ['color' => 'cyan'],
	 * * %w => ['color' => 'grey'],
	 * * %k => ['color' => 'black'],
	 * * %n => ['color' => 'reset'],
	 * * %Y => ['color' => 'yellow', 'style' => 'bright'],
	 * * %G => ['color' => 'green', 'style' => 'bright'],
	 * * %B => ['color' => 'blue', 'style' => 'bright'],
	 * * %R => ['color' => 'red', 'style' => 'bright'],
	 * * %P => ['color' => 'magenta', 'style' => 'bright'],
	 * * %M => ['color' => 'magenta', 'style' => 'bright'],
	 * * %C => ['color' => 'cyan', 'style' => 'bright'],
	 * * %W => ['color' => 'grey', 'style' => 'bright'],
	 * * %K => ['color' => 'black', 'style' => 'bright'],
	 * * %N => ['color' => 'reset', 'style' => 'bright'],
	 * * %3 => ['background' => 'yellow'],
	 * * %2 => ['background' => 'green'],
	 * * %4 => ['background' => 'blue'],
	 * * %1 => ['background' => 'red'],
	 * * %5 => ['background' => 'magenta'],
	 * * %6 => ['background' => 'cyan'],
	 * * %7 => ['background' => 'grey'],
	 * * %0 => ['background' => 'black'],
	 * * %F => ['style' => 'blink'],
	 * * %U => ['style' => 'underline'],
	 * * %8 => ['style' => 'inverse'],
	 * * %9 => ['style' => 'bright'],
	 * * %_ => ['style' => 'bright']
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $string String to colorize for output, with color tokens.
	 * @return string Colorized string.
	 */
	public static function colorize( $string ) {
	}

	/**
	 * Schedule a callback to be executed at a certain point.
	 *
	 * Hooks conceptually are very similar to WordPress actions. WP-CLI hooks
	 * are typically called before WordPress is loaded.
	 *
	 * WP-CLI hooks include:
	 *
	 * * `before_add_command:<command>` - Before the command is added.
	 * * `after_add_command:<command>` - After the command was added.
	 * * `before_invoke:<command>` (1) - Just before a command is invoked.
	 * * `after_invoke:<command>` (1) - Just after a command is invoked.
	 * * `find_command_to_run_pre` - Just before WP-CLI finds the command to run.
	 * * `before_registering_contexts` (1) - Before the contexts are registered.
	 * * `before_wp_load` - Just before the WP load process begins.
	 * * `before_wp_config_load` - After wp-config.php has been located.
	 * * `after_wp_config_load` - After wp-config.php has been loaded into scope.
	 * * `after_wp_load` - Just after the WP load process has completed.
	 * * `before_run_command` (3) - Just before the command is executed.
	 *
	 * The parentheses behind the hook name denote the number of arguments
	 * being passed into the hook. For such hooks, the callback should return
	 * the first argument again, making them work like a WP filter.
	 *
	 * WP-CLI commands can create their own hooks with `WP_CLI::do_hook()`.
	 *
	 * If additional arguments are passed through the `WP_CLI::do_hook()` call,
	 * these will be passed on to the callback provided by `WP_CLI::add_hook()`.
	 *
	 * ```
	 * # `wp network meta` confirms command is executing in multisite context.
	 * WP_CLI::add_command( 'network meta', 'Network_Meta_Command', array(
	 *    'before_invoke' => function ( $name ) {
	 *        if ( !is_multisite() ) {
	 *            WP_CLI::error( 'This is not a multisite installation.' );
	 *        }
	 *    }
	 * ) );
	 * ```
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $when Identifier for the hook.
	 * @param mixed $callback Callback to execute when hook is called.
	 * @return null
	 */
	public static function add_hook( $when, $callback ) {
	}

	/**
	 * Execute callbacks registered to a given hook.
	 *
	 * See `WP_CLI::add_hook()` for details on WP-CLI's internal hook system.
	 * Commands can provide and call their own hooks.
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $when    Identifier for the hook.
	 * @param mixed  ...$args Optional. Arguments that will be passed onto the
	 *                        callback provided by `WP_CLI::add_hook()`.
	 * @return null|mixed Returns the first optional argument if optional
	 *                    arguments were passed, otherwise returns null.
	 */
	public static function do_hook( $when, ...$args ) {
	}

	/**
	 * Add a callback to a WordPress action or filter.
	 *
	 * `add_action()` without needing access to `add_action()`. If WordPress is
	 * already loaded though, you should use `add_action()` (and `add_filter()`)
	 * instead.
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $tag Named WordPress action or filter.
	 * @param mixed $function_to_add Callable to execute when the action or filter is evaluated.
	 * @param integer $priority Priority to add the callback as.
	 * @param integer $accepted_args Number of arguments to pass to callback.
	 * @return true
	 */
	public static function add_wp_hook( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	}

	/**
	 * Register a command to WP-CLI.
	 *
	 * WP-CLI supports using any callable class, function, or closure as a
	 * command. `WP_CLI::add_command()` is used for both internal and
	 * third-party command registration.
	 *
	 * Command arguments are parsed from PHPDoc by default, but also can be
	 * supplied as an optional third argument during registration.
	 *
	 * ```
	 * # Register a custom 'foo' command to output a supplied positional param.
	 * #
	 * # $ wp foo bar --append=qux
	 * # Success: bar qux
	 *
	 * /**
	 *  * My awesome closure command
	 *  *
	 *  * <message>
	 *  * : An awesome message to display
	 *  *
	 *  * --append=<message>
	 *  * : An awesome message to append to the original message.
	 *  *
	 *  * @when before_wp_load
	 *  *\/
	 * $foo = function( $args, $assoc_args ) {
	 *     WP_CLI::success( $args[0] . ' ' . $assoc_args['append'] );
	 * };
	 * WP_CLI::add_command( 'foo', $foo );
	 * ```
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string   $name Name for the command (e.g. "post list" or "site empty").
	 * @param callable $callable Command implementation as a class, function or closure.
	 * @param array    $args {
	 *    Optional. An associative array with additional registration parameters.
	 *
	 *    @type callable $before_invoke Callback to execute before invoking the command.
	 *    @type callable $after_invoke  Callback to execute after invoking the command.
	 *    @type string   $shortdesc     Short description (80 char or less) for the command.
	 *    @type string   $longdesc      Description of arbitrary length for examples, etc.
	 *    @type string   $synopsis      The synopsis for the command (string or array).
	 *    @type string   $when          Execute callback on a named WP-CLI hook (e.g. before_wp_load).
	 *    @type bool     $is_deferred   Whether the command addition had already been deferred.
	 * }
	 * @return bool True on success, false if deferred, hard error if registration failed.
	 */
	public static function add_command( $name, $callable, $args = [] ) {
	}

	/**
	 * Get the list of outstanding deferred command additions.
	 *
	 * @return array Array of outstanding command additions.
	 */
	public static function get_deferred_additions() {
	}

	/**
	 * Remove a command addition from the list of outstanding deferred additions.
	 */
	public static function remove_deferred_addition( $name ) {
	}

	/**
	 * Display informational message without prefix, and ignore `--quiet`.
	 *
	 * Message is written to STDOUT. `WP_CLI::log()` is typically recommended;
	 * `WP_CLI::line()` is included for historical compat.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to display to the end user.
	 * @return null
	 */
	public static function line( $message = '' ) {
	}

	/**
	 * Display informational message without prefix.
	 *
	 * Message is written to STDOUT, or discarded when `--quiet` flag is supplied.
	 *
	 * ```
	 * # `wp cli update` lets user know of each step in the update process.
	 * WP_CLI::log( sprintf( 'Downloading from %s...', $download_url ) );
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDOUT.
	 */
	public static function log( $message ) {
	}

	/**
	 * Display success message prefixed with "Success: ".
	 *
	 * Success message is written to STDOUT.
	 *
	 * Typically recommended to inform user of successful script conclusion.
	 *
	 * ```
	 * # wp rewrite flush expects 'rewrite_rules' option to be set after flush.
	 * flush_rewrite_rules( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hard' ) );
	 * if ( ! get_option( 'rewrite_rules' ) ) {
	 *     WP_CLI::warning( "Rewrite rules are empty." );
	 * } else {
	 *     WP_CLI::success( 'Rewrite rules flushed.' );
	 * }
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDOUT.
	 * @return null
	 */
	public static function success( $message ) {
	}

	/**
	 * Display debug message prefixed with "Debug: " when `--debug` is used.
	 *
	 * Debug message is written to STDERR, and includes script execution time.
	 *
	 * Helpful for optionally showing greater detail when needed. Used throughout
	 * WP-CLI bootstrap process for easier debugging and profiling.
	 *
	 * ```
	 * # Called in `WP_CLI\Runner::set_wp_root()`.
	 * private static function set_wp_root( $path ) {
	 *     define( 'ABSPATH', Utils\trailingslashit( $path ) );
	 *     WP_CLI::debug( 'ABSPATH defined: ' . ABSPATH );
	 *     $_SERVER['DOCUMENT_ROOT'] = realpath( $path );
	 * }
	 *
	 * # Debug details only appear when `--debug` is used.
	 * # $ wp --debug
	 * # [...]
	 * # Debug: ABSPATH defined: /srv/www/wordpress-develop.dev/src/ (0.225s)
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string|WP_Error|Exception|Throwable $message Message to write to STDERR.
	 * @param string|bool $group Organize debug message to a specific group.
	 * Use `false` to not group the message.
	 * @return null
	 */
	public static function debug( $message, $group = false ) {
	}

	/**
	 * Display warning message prefixed with "Warning: ".
	 *
	 * Warning message is written to STDERR.
	 *
	 * Use instead of `WP_CLI::debug()` when script execution should be permitted
	 * to continue.
	 *
	 * ```
	 * # `wp plugin activate` skips activation when plugin is network active.
	 * $status = $this->get_status( $plugin->file );
	 * // Network-active is the highest level of activation status
	 * if ( 'active-network' === $status ) {
	 *   WP_CLI::warning( "Plugin '{$plugin->name}' is already network active." );
	 *   continue;
	 * }
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string|WP_Error|Exception|Throwable $message Message to write to STDERR.
	 * @return null
	 */
	public static function warning( $message ) {
	}

	/**
	 * Display error message prefixed with "Error: " and exit script.
	 *
	 * Error message is written to STDERR. Defaults to halting script execution
	 * with return code 1.
	 *
	 * Use `WP_CLI::warning()` instead when script execution should be permitted
	 * to continue.
	 *
	 * ```
	 * # `wp cache flush` considers flush failure to be a fatal error.
	 * if ( false === wp_cache_flush() ) {
	 *     WP_CLI::error( 'The object cache could not be flushed.' );
	 * }
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string|WP_Error|Exception|Throwable $message Message to write to STDERR.
	 * @param boolean|integer            $exit    True defaults to exit(1).
	 * @return null
	 */
	public static function error( $message, $exit = true ) {
	}

	/**
	 * Halt script execution with a specific return code.
	 *
	 * Permits script execution to be overloaded by `WP_CLI::runcommand()`
	 *
	 * @access public
	 * @category Output
	 *
	 * @param integer $return_code
	 * @return never
	 */
	public static function halt( $return_code ) {
	}

	/**
	 * Display a multi-line error message in a red box. Doesn't exit script.
	 *
	 * Error message is written to STDERR.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param array $message_lines Multi-line error message to be displayed.
	 */
	public static function error_multi_line( $message_lines ) {
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 *
	 * If 'y' is provided to the question, the script execution continues. If
	 * 'n' or any other response is provided to the question, script exits.
	 *
	 * ```
	 * # `wp db drop` asks for confirmation before dropping the database.
	 *
	 * WP_CLI::confirm( "Are you sure you want to drop the database?", $assoc_args );
	 * ```
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $question Question to display before the prompt.
	 * @param array $assoc_args Skips prompt if 'yes' is provided.
	 */
	public static function confirm( $question, $assoc_args = [] ) {
	}

	/**
	 * Read value from a positional argument or from STDIN.
	 *
	 * @param array $args The list of positional arguments.
	 * @param int $index At which position to check for the value.
	 *
	 * @return string
	 */
	public static function get_value_from_arg_or_stdin( $args, $index ) {
	}

	/**
	 * Read a value, from various formats.
	 *
	 * @access public
	 * @category Input
	 *
	 * @param mixed $raw_value
	 * @param array $assoc_args
	 */
	public static function read_value( $raw_value, $assoc_args = [] ) {
	}

	/**
	 * Display a value, in various formats
	 *
	 * @param mixed $value Value to display.
	 * @param array $assoc_args Arguments passed to the command, determining format.
	 */
	public static function print_value( $value, $assoc_args = [] ) {
	}

	/**
	 * Convert a WP_Error or Exception into a string
	 *
	 * @param string|WP_Error|Exception|Throwable $errors
	 * @throws InvalidArgumentException
	 *
	 * @return string
	 */
	public static function error_to_string( $errors ) {
	}

	/**
	 * Launch an arbitrary external process that takes over I/O.
	 *
	 * ```
	 * # `wp core download` falls back to the `tar` binary when PharData isn't available
	 * if ( ! class_exists( 'PharData' ) ) {
	 *     $cmd = "tar xz --strip-components=1 --directory=%s -f $tarball";
	 *     WP_CLI::launch( Utils\esc_cmd( $cmd, $dest ) );
	 *     return;
	 * }
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command External process to launch.
	 * @param boolean $exit_on_error Whether to exit if the command returns an elevated return code.
	 * @param boolean $return_detailed Whether to return an exit status (default) or detailed execution results.
	 * @return int|ProcessRun The command exit status, or a ProcessRun object for full details.
	 */
	public static function launch( $command, $exit_on_error = true, $return_detailed = false ) {
	}

	/**
	 * Run a WP-CLI command in a new process reusing the current runtime arguments.
	 *
	 * Use `WP_CLI::runcommand()` instead, which is easier to use and works better.
	 *
	 * Note: While this command does persist a limited set of runtime arguments,
	 * it *does not* persist environment variables. Practically speaking, WP-CLI
	 * packages won't be loaded when using WP_CLI::launch_self() because the
	 * launched process doesn't have access to the current process $HOME.
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command WP-CLI command to call.
	 * @param array $args Positional arguments to include when calling the command.
	 * @param array $assoc_args Associative arguments to include when calling the command.
	 * @param bool $exit_on_error Whether to exit if the command returns an elevated return code.
	 * @param bool $return_detailed Whether to return an exit status (default) or detailed execution results.
	 * @param array $runtime_args Override one or more global args (path,url,user,allow-root)
	 * @return int|ProcessRun The command exit status, or a ProcessRun instance
	 */
	public static function launch_self( $command, $args = [], $assoc_args = [], $exit_on_error = true, $return_detailed = false, $runtime_args = [] ) {
	}

	/**
	 * Get the path to the PHP binary used when executing WP-CLI.
	 *
	 * Environment values permit specific binaries to be indicated.
	 *
	 * Note: moved to Utils, left for BC.
	 *
	 * @access public
	 * @category System
	 *
	 * @return string
	 */
	public static function get_php_binary() {
	}

	/**
	 * Confirm that a global configuration parameter does exist.
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $key Config parameter key to check.
	 *
	 * @return bool
	 */
	public static function has_config( $key ) {
	}

	/**
	 * Get values of global configuration parameters.
	 *
	 * Provides access to `--path=<path>`, `--url=<url>`, and other values of
	 * the [global configuration parameters](https://wp-cli.org/config/).
	 *
	 * ```
	 * WP_CLI::log( 'The --url=<url> value is: ' . WP_CLI::get_config( 'url' ) );
	 * ```
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $key Get value for a specific global configuration parameter.
	 * @return mixed
	 */
	public static function get_config( $key = null ) {
	}

	/**
	 * Run a WP-CLI command.
	 *
	 * Launches a new child process to run a specified WP-CLI command.
	 * Optionally:
	 *
	 * * Run the command in an existing process.
	 * * Prevent halting script execution on error.
	 * * Capture and return STDOUT, or full details about command execution.
	 * * Parse JSON output if the command rendered it.
	 *
	 * ```
	 * $options = array(
	 *   'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
	 *   'parse'      => 'json', // Parse captured STDOUT to JSON array.
	 *   'launch'     => false,  // Reuse the current process.
	 *   'exit_error' => true,   // Halt script execution on error.
	 * );
	 * $plugins = WP_CLI::runcommand( 'plugin list --format=json', $options );
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command WP-CLI command to run, including arguments.
	 * @param array  $options Configuration options for command execution.
	 * @return mixed
	 */
	public static function runcommand( $command, $options = [] ) {
	}

	/**
	 * Run a given command within the current process using the same global
	 * parameters.
	 *
	 * Use `WP_CLI::runcommand()` instead, which is easier to use and works better.
	 *
	 * To run a command using a new process with the same global parameters,
	 * use WP_CLI::launch_self(). To run a command using a new process with
	 * different global parameters, use WP_CLI::launch().
	 *
	 * ```
	 * ob_start();
	 * WP_CLI::run_command( array( 'cli', 'cmd-dump' ) );
	 * $ret = ob_get_clean();
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param array $args Positional arguments including command name.
	 * @param array $assoc_args
	 */
	public static function run_command( $args, $assoc_args = [] ) {
	}
}
