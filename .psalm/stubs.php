<?php
if (!defined('EP_PAGES')) {
	define('EP_PAGES', 4096);
}
if (!defined('MONTH_IN_SECONDS')) {
	define('MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS);
}
if (!defined('HOUR_IN_SECONDS')) {
	define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
}

if (!defined('ABSPATH')) {
	define('ABSPATH', '');
}

/**
 * Cancel the next occurrence of a scheduled action.
 *
 * While only the next instance of a recurring or cron action is unscheduled by this method, that will also prevent
 * all future instances of that recurring or cron action from being run. Recurring and cron actions are scheduled in
 * a sequence instead of all being scheduled at once. Each successive occurrence of a recurring action is scheduled
 * only after the former action is run. If the next instance is never run, because it's unscheduled by this function,
 * then the following instance will never be scheduled (or exist), which is effectively the same as being unscheduled
 * by this method also.
 *
 * @param string $hook The hook that the job will trigger.
 * @param array $args Args that would have been passed to the job.
 * @param string $group The group the job is assigned to.
 *
 * @return string|null The scheduled action ID if a scheduled action was found, or null if no matching action found.
 */
function as_unschedule_action($hook, $args = array(), $group = '')
{
}
