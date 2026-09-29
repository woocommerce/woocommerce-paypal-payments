<?php

/**
 * Auto-inserts PayPal blocks into block-theme templates via the Block Hooks API.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

/**
 * The foundation for rendering PayPal buttons and Pay Later messaging inside the
 * WordPress Site Editor.
 *
 * The classic `woocommerce_*` action hooks the SDK renders through do not fire
 * inside block templates or template parts, so a block theme's Site Editor - and
 * the templates it produces on the front end - would otherwise show no PayPal
 * surfaces at all. The Block Hooks API (WordPress 6.5+) fills that gap: it
 * auto-inserts a registered block next to an anchor block inside templates,
 * template parts and patterns, in the editor canvas and on the front end alike,
 * while still letting the merchant move or dismiss it.
 *
 * A surface describes one insertion as an entry in the map handed to this
 * registrar - the block to insert, the anchor block, the relative position, and
 * an `enabled` predicate that decides, at render time, whether that insertion
 * applies right now. The registrar wires each entry into the two Block Hooks
 * filters and does nothing on a classic (non-block) theme. Keeping the predicate
 * in the entry rather than here is what lets messaging and, later, button
 * surfaces reuse the same registrar with their own eligibility rules.
 */
class HookedBlocksRegistrar
{
    /**
     * The insertions, keyed by the block type to auto-insert.
     *
     * Each entry is an array with:
     * - `anchor`   string|string[] The block type(s) the insertion is positioned against; the block is inserted at each.
     * - `position` string          Where to insert: before, after, first_child or last_child.
     * - `enabled`  callable        A predicate returning bool; the insertion is skipped when it returns false.
     * - `anchor_filter` callable    Optional. Receives the parsed anchor block and returns bool; the insertion is
     *                               skipped at anchors it rejects, so the same block type used elsewhere in the
     *                               template (e.g. inside a query loop) does not also get the block.
     *
     * @var array<string, array{anchor:string|array<int, string>, position:string, enabled:callable, anchor_filter?:callable}>
     */
    private $insertions;
    /**
     * @param array<string, array{anchor:string|array<int, string>, position:string, enabled:callable, anchor_filter?:callable}> $insertions The insertions to register.
     */
    public function __construct(array $insertions)
    {
        $this->insertions = $insertions;
    }
    /**
     * Attaches the Block Hooks filters.
     *
     * Safe to call at module boot: both callbacks resolve the theme and settings
     * lazily, when the Block Hooks API consults them during template rendering.
     */
    public function register(): void
    {
        add_filter('hooked_block_types', array($this, 'add_hooked_block_types'), 10, 4);
        foreach (array_keys($this->insertions) as $block_type) {
            add_filter("hooked_block_{$block_type}", array($this, 'gate_insertion'), 10, 5);
        }
    }
    /**
     * Declares which of our blocks WordPress should auto-insert at a given anchor.
     *
     * @param mixed $hooked_block_types  The block types already hooked here; expected array, coerced defensively.
     * @param mixed $relative_position   Where the anchor wants children/siblings: before|after|first_child|last_child.
     * @param mixed $anchor_block_type   The block the insertion is positioned against.
     * @param mixed $context             The template, part or pattern being rendered (unused).
     * @return array<int, string> The block types to insert at this position.
     */
    public function add_hooked_block_types($hooked_block_types, $relative_position, $anchor_block_type, $context): array
    {
        // The value travels through a public filter, so a third-party callback
        // earlier in the chain may have replaced it with a non-array.
        $hooked_block_types = is_array($hooked_block_types) ? $hooked_block_types : array();
        if (!$this->is_block_theme()) {
            return $hooked_block_types;
        }
        foreach ($this->insertions as $block_type => $insertion) {
            // `anchor` may be a single block type or a list of them, so the same block
            // can be inserted next to more than one anchor (e.g. both add-to-cart blocks).
            $anchors = is_array($insertion['anchor']) ? $insertion['anchor'] : array($insertion['anchor']);
            if (in_array($anchor_block_type, $anchors, \true) && $insertion['position'] === $relative_position) {
                $hooked_block_types[] = $block_type;
            }
        }
        return $hooked_block_types;
    }
    /**
     * Suppresses one auto-insertion when its surface is not eligible right now.
     *
     * Returning null tells the Block Hooks API to drop the insertion for this
     * render, which is how a location switched off in the settings - or anything
     * else the entry's predicate rejects - keeps the block out of the template.
     * The insertion is also dropped at anchors the entry's `anchor_filter` rejects,
     * and when the template already contains the block, e.g. one saved from the
     * editor while the block was hooked to a different anchor.
     *
     * @param mixed $parsed_hooked_block The parsed block to insert, or null if an earlier callback already dropped it.
     * @param mixed $hooked_block_type   The block type being considered.
     * @param mixed $relative_position   The insertion position (unused).
     * @param mixed $parsed_anchor_block The anchor block the insertion is positioned against.
     * @param mixed $context             The template, part or pattern being rendered.
     * @return array<string, mixed>|null The block to insert, or null to skip it.
     */
    public function gate_insertion($parsed_hooked_block, $hooked_block_type, $relative_position, $parsed_anchor_block, $context)
    {
        // Already dropped upstream, or not one of ours: leave the decision alone.
        if (null === $parsed_hooked_block || !is_string($hooked_block_type) || !isset($this->insertions[$hooked_block_type])) {
            return $parsed_hooked_block;
        }
        $insertion = $this->insertions[$hooked_block_type];
        if (isset($insertion['anchor_filter']) && (!is_array($parsed_anchor_block) || !$insertion['anchor_filter']($parsed_anchor_block))) {
            return null;
        }
        if ($this->context_contains_block($context, $hooked_block_type)) {
            return null;
        }
        return $insertion['enabled']() ? $parsed_hooked_block : null;
    }
    /**
     * Whether the template, part or pattern being rendered already contains the block.
     *
     * @param mixed  $context    A WP_Block_Template, a pattern array, or anything else a third party passed.
     * @param string $block_type The block type to look for.
     */
    private function context_contains_block($context, string $block_type): bool
    {
        if ($context instanceof \WP_Block_Template) {
            $content = $context->content;
        } elseif (is_array($context)) {
            $content = $context['content'] ?? null;
        } else {
            $content = null;
        }
        return is_string($content) && '' !== $content && has_block($block_type, $content);
    }
    /**
     * Whether the active theme is a block theme, the only kind the Site Editor and
     * Block Hooks apply to.
     */
    private function is_block_theme(): bool
    {
        return function_exists('wp_is_block_theme') && wp_is_block_theme();
    }
}
