<?php

declare (strict_types=1);
namespace WpOop\WordPress\Gutenberg;

/**
 * The interface for Gutenberg block data.
 */
interface BlockInterface
{
    /**
     * Returns name of the block, such as 'core/paragraph'.
     */
    public function getBlockName(): string;
    /**
     * Returns block attributes.
     * @return array<string, mixed>
     */
    public function getAttributes(): array;
    /**
     * Returns inner blocks (for example, used in the Columns block).
     * @return BlockInterface[]
     */
    public function getInnerBlocks(): array;
    /**
     * Returns resultant HTML.
     */
    public function getInnerHtml(): string;
    /**
     * Returns list of string fragments and null markers where inner blocks were found.
     * @return array<?string>
     */
    public function getInnerContent(): array;
}
