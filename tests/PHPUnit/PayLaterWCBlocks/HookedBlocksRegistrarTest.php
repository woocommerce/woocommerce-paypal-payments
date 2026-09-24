<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectAdded;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\PayLaterWCBlocks\HookedBlocksRegistrar
 */
class HookedBlocksRegistrarTest extends TestCase
{
    private const CART_BLOCK = 'woocommerce-paypal-payments/cart-paylater-messages';
    private const CHECKOUT_BLOCK = 'woocommerce-paypal-payments/checkout-paylater-messages';

    /**
     * @return array<string, array{anchor:string, position:string, enabled:callable}>
     */
    private function insertions(): array
    {
        return array(
            self::CART_BLOCK     => array(
                'anchor'   => 'woocommerce/cart-totals-block',
                'position' => 'last_child',
                'enabled'  => fn (): bool => true,
            ),
            self::CHECKOUT_BLOCK => array(
                'anchor'   => 'woocommerce/checkout-totals-block',
                'position' => 'last_child',
                'enabled'  => fn (): bool => false,
            ),
        );
    }

    /**
     * GIVEN a registrar configured with two block insertions
     * WHEN register() is called
     * THEN it wires the shared 'hooked_block_types' filter
     * AND it wires a dedicated 'hooked_block_{type}' filter for every insertion key
     */
    public function testRegisterWiresTheHookedBlockFilters(): void
    {
        $registrar = new HookedBlocksRegistrar($this->insertions());

        expectAdded('hooked_block_types')->once();
        expectAdded('hooked_block_' . self::CART_BLOCK)->once();
        expectAdded('hooked_block_' . self::CHECKOUT_BLOCK)->once();

        $registrar->register();

        // Brain Monkey verifies the expectAdded() expectations above at teardown;
        // this counts the verification as a PHPUnit assertion so the test isn't
        // flagged as risky for asserting nothing.
        $this->addToAssertionCount(3);
    }

    /**
     * GIVEN the active theme is not a block theme
     * WHEN add_hooked_block_types() is called for an anchor/position that matches one of our insertions
     * THEN the previously hooked block types list is returned unchanged, without our block appended
     */
    public function testAddHookedBlockTypesReturnsUnchangedListOnNonBlockTheme(): void
    {
        when('wp_is_block_theme')->justReturn(false);

        $registrar = new HookedBlocksRegistrar($this->insertions());

        $result = $registrar->add_hooked_block_types(
            array('some/existing-block'),
            'last_child',
            'woocommerce/cart-totals-block',
            null
        );

        $this->assertSame(array('some/existing-block'), $result);
    }

    /**
     * GIVEN the active theme is not a block theme
     * WHEN add_hooked_block_types() is called with a non-array list (e.g. because an earlier
     *      filter callback replaced it)
     * THEN the non-array value is coerced to an empty array rather than appended to or left as-is
     *
     * @dataProvider non_array_hooked_block_types_provider
     */
    public function testAddHookedBlockTypesCoercesNonArrayInputToEmptyArray($non_array_value): void
    {
        when('wp_is_block_theme')->justReturn(false);

        $registrar = new HookedBlocksRegistrar($this->insertions());

        $result = $registrar->add_hooked_block_types(
            $non_array_value,
            'last_child',
            'woocommerce/cart-totals-block',
            null
        );

        $this->assertSame(array(), $result);
    }

    public function non_array_hooked_block_types_provider(): array
    {
        return array(
            'null input'  => array(null),
            'false input' => array(false),
            'string input' => array('unexpected'),
        );
    }

    /**
     * GIVEN the active theme is a block theme
     * WHEN add_hooked_block_types() is called for an anchor/position pair that matches exactly
     *      one insertion
     * THEN only that insertion's block type is appended to the list
     * AND any block types already present in the incoming list are preserved
     */
    public function testAddHookedBlockTypesAppendsOnlyMatchingInsertionOnBlockTheme(): void
    {
        when('wp_is_block_theme')->justReturn(true);

        $registrar = new HookedBlocksRegistrar($this->insertions());

        $result = $registrar->add_hooked_block_types(
            array('some/pre-existing-block'),
            'last_child',
            'woocommerce/cart-totals-block',
            null
        );

        $this->assertSame(
            array('some/pre-existing-block', self::CART_BLOCK),
            $result
        );
    }

    /**
     * GIVEN the active theme is a block theme
     * WHEN add_hooked_block_types() is called for an anchor/position pair that matches none of
     *      the configured insertions
     * THEN the list is returned without any of our block types appended
     */
    public function testAddHookedBlockTypesAppendsNothingWhenNoInsertionMatchesOnBlockTheme(): void
    {
        when('wp_is_block_theme')->justReturn(true);

        $registrar = new HookedBlocksRegistrar($this->insertions());

        $result = $registrar->add_hooked_block_types(
            array(),
            'first_child',
            'woocommerce/order-summary-block',
            null
        );

        $this->assertSame(array(), $result);
    }

    /**
     * GIVEN the active theme is a block theme
     * WHEN add_hooked_block_types() is called with a non-array list
     * THEN the input is coerced to an empty array before our matching block type is appended
     */
    public function testAddHookedBlockTypesCoercesNonArrayInputBeforeAppendingOnBlockTheme(): void
    {
        when('wp_is_block_theme')->justReturn(true);

        $registrar = new HookedBlocksRegistrar($this->insertions());

        $result = $registrar->add_hooked_block_types(
            null,
            'last_child',
            'woocommerce/cart-totals-block',
            null
        );

        $this->assertSame(array(self::CART_BLOCK), $result);
    }

    /**
     * GIVEN an insertion whose 'anchor' is a list of block types rather than a single one
     * WHEN add_hooked_block_types() is called on a block theme for either anchor at the
     *      matching position
     * THEN the insertion's block type is appended for each of them
     * AND it is not appended for an anchor not listed in the insertion
     */
    public function testAddHookedBlockTypesAppendsForAnyAnchorInAnAnchorList(): void
    {
        when('wp_is_block_theme')->justReturn(true);

        $multi_anchor_block = 'woocommerce-paypal-payments/product-smart-buttons';
        $insertions = array(
            $multi_anchor_block => array(
                'anchor'   => array('a/one', 'a/two'),
                'position' => 'after',
                'enabled'  => fn (): bool => true,
            ),
        );
        $registrar = new HookedBlocksRegistrar($insertions);

        $result_for_first_anchor = $registrar->add_hooked_block_types(array(), 'after', 'a/one', null);
        $result_for_second_anchor = $registrar->add_hooked_block_types(array(), 'after', 'a/two', null);
        $result_for_unlisted_anchor = $registrar->add_hooked_block_types(array(), 'after', 'a/three', null);

        $this->assertSame(array($multi_anchor_block), $result_for_first_anchor);
        $this->assertSame(array($multi_anchor_block), $result_for_second_anchor);
        $this->assertSame(array(), $result_for_unlisted_anchor);
    }

    /**
     * GIVEN a registrar configured with our insertions
     * WHEN gate_insertion() is called with a null parsed block (already dropped upstream)
     * THEN the null value is returned unchanged, without consulting any 'enabled' predicate
     */
    public function testGateInsertionReturnsNullUnchangedWhenParsedBlockIsAlreadyNull(): void
    {
        $registrar = new HookedBlocksRegistrar($this->insertions());

        $result = $registrar->gate_insertion(null, self::CART_BLOCK, 'last_child', null, null);

        $this->assertNull($result);
    }

    /**
     * GIVEN a registrar configured with our insertions
     * WHEN gate_insertion() is called for a block type that is not one of ours
     * THEN the parsed block is returned unchanged
     */
    public function testGateInsertionReturnsParsedBlockUnchangedForUnknownBlockType(): void
    {
        $registrar = new HookedBlocksRegistrar($this->insertions());
        $parsed_block = array('blockName' => 'some/other-block');

        $result = $registrar->gate_insertion($parsed_block, 'some/other-block', 'last_child', null, null);

        $this->assertSame($parsed_block, $result);
    }

    /**
     * GIVEN an insertion whose 'enabled' predicate returns true
     * WHEN gate_insertion() is called for that block type
     * THEN the parsed block is returned unchanged, since the surface is eligible
     */
    public function testGateInsertionReturnsParsedBlockWhenEnabledPredicateReturnsTrue(): void
    {
        $registrar = new HookedBlocksRegistrar($this->insertions());
        $parsed_block = array('blockName' => self::CART_BLOCK);

        $result = $registrar->gate_insertion($parsed_block, self::CART_BLOCK, 'last_child', null, null);

        $this->assertSame($parsed_block, $result);
    }

    /**
     * GIVEN an insertion whose 'enabled' predicate returns false
     * WHEN gate_insertion() is called for that block type
     * THEN null is returned, suppressing the auto-insertion for this render
     */
    public function testGateInsertionReturnsNullWhenEnabledPredicateReturnsFalse(): void
    {
        $registrar = new HookedBlocksRegistrar($this->insertions());
        $parsed_block = array('blockName' => self::CHECKOUT_BLOCK);

        $result = $registrar->gate_insertion($parsed_block, self::CHECKOUT_BLOCK, 'last_child', null, null);

        $this->assertNull($result);
    }

    /**
     * GIVEN an insertion whose 'enabled' predicate is a spy that records whether it ran
     * WHEN gate_insertion() is called for that block type with a non-null parsed block
     * THEN the predicate is actually consulted to decide the outcome
     */
    public function testGateInsertionConsultsTheEnabledPredicate(): void
    {
        $consulted = false;
        $insertions = array(
            self::CART_BLOCK => array(
                'anchor'   => 'woocommerce/cart-totals-block',
                'position' => 'last_child',
                'enabled'  => function () use (&$consulted): bool {
                    $consulted = true;
                    return true;
                },
            ),
        );
        $registrar = new HookedBlocksRegistrar($insertions);
        $parsed_block = array('blockName' => self::CART_BLOCK);

        $registrar->gate_insertion($parsed_block, self::CART_BLOCK, 'last_child', null, null);

        $this->assertTrue($consulted);
    }
}
