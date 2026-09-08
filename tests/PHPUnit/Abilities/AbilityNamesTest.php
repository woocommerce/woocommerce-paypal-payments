<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Unit tests for AbilityNames::CATEGORY_SLUG as the single source of truth
 * for the shared ability category.
 *
 * AbilitiesRegistrar::CATEGORY_SLUG and Domain\AbstractPpcpAbility::CATEGORY_SLUG
 * are @deprecated 4.1.0 aliases kept only so external code reading either
 * constant keeps working; both are declared as `= AbilityNames::CATEGORY_SLUG`.
 * This test pins that assignment so a future edit to AbilityNames or to
 * either alias cannot let them drift apart silently.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilityNames
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar
 * @covers \WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility
 */
class AbilityNamesTest extends TestCase
{
	/**
	 * @scenario Plugin ownership of the abilities lives in the ability
	 *           namespace, not in the category, so the category stays the
	 *           shared `woocommerce` bucket Woo Core owns.
	 *
	 * Given AbilityNames::CATEGORY_SLUG is the constant production code reads
	 * When it is inspected
	 * Then it is the literal `woocommerce` slug.
	 */
	public function test_category_slug_is_the_shared_woocommerce_bucket(): void
	{
		// Then.
		$this->assertSame('woocommerce', AbilityNames::CATEGORY_SLUG);
	}

	/**
	 * @scenario Both deprecated aliases must mirror AbilityNames::CATEGORY_SLUG
	 *           exactly, since external code may still read either one.
	 *
	 * Given a deprecated CATEGORY_SLUG alias declared elsewhere in the module
	 * When it is compared against AbilityNames::CATEGORY_SLUG
	 * Then the two are identical, so neither the alias nor AbilityNames can
	 *      drift without this test catching it.
	 *
	 * @dataProvider deprecated_category_slug_alias_provider
	 */
	public function test_deprecated_alias_mirrors_the_shared_constant(string $alias): void
	{
		// Then.
		$this->assertSame(
			AbilityNames::CATEGORY_SLUG,
			$alias,
			'A deprecated CATEGORY_SLUG alias must stay pinned to AbilityNames::CATEGORY_SLUG.'
		);
	}

	public function deprecated_category_slug_alias_provider(): array
	{
		return array(
			'AbilitiesRegistrar::CATEGORY_SLUG'          => array( AbilitiesRegistrar::CATEGORY_SLUG ),
			'Domain\\AbstractPpcpAbility::CATEGORY_SLUG' => array( AbstractPpcpAbility::CATEGORY_SLUG ),
		);
	}
}
