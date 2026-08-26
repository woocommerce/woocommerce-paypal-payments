<?php
/**
 * Minimal stub for the WooCommerce Blueprint HasAlias interface.
 *
 * The Blueprint package ships with WooCommerce core and is not autoloadable in
 * the unit-test environment. This stub lets coverage (processUncoveredFiles)
 * include the ppcp-compat WooCommerceBlueprint classes that implement it.
 */

namespace Automattic\WooCommerce\Blueprint\Exporters;

if ( ! interface_exists( HasAlias::class ) ) {
	interface HasAlias {
	}
}
