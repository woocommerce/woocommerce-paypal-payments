<?php
/**
 * Minimal stub for the WooCommerce Blueprint Step base class.
 *
 * The Blueprint package ships with WooCommerce core and is not autoloadable in
 * the unit-test environment. This stub lets coverage (processUncoveredFiles)
 * include the ppcp-compat WooCommerceBlueprint classes that extend it.
 */

namespace Automattic\WooCommerce\Blueprint\Steps;

if ( ! class_exists( Step::class ) ) {
	abstract class Step {
	}
}
