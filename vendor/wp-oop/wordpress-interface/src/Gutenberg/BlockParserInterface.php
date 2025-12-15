<?php

declare (strict_types=1);
namespace WpOop\WordPress\Gutenberg;

/**
 * The interface for parsing Gutenberg blocks.
 */
interface BlockParserInterface
{
    /**
     * @param string $postContent Content of a WP post (e.g. WP_Post post_content).
     * @return BlockInterface[]
     */
    public function parseBlocks(string $postContent): array;
}
