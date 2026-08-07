<?php
/**
 * Minimal stub for the WooCommerce Blueprint StepProcessor interface.
 *
 * The Blueprint package ships with WooCommerce core and is not autoloadable in
 * the unit-test environment. This stub lets coverage (processUncoveredFiles)
 * include the ppcp-compat WooCommerceBlueprint classes that implement it.
 */

namespace Automattic\WooCommerce\Blueprint;

if ( ! interface_exists( StepProcessor::class ) ) {
	interface StepProcessor {
	}
}
