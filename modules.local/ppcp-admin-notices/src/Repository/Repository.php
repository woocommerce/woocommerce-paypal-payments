<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Repository;


use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;

class Repository implements RepositoryInterface
{

    const NOTICES_FILTER = 'ppcp.admin-notices.current-notices';

    public function currentMessages(): array
    {
        return array_filter(
            (array) apply_filters(
                self::NOTICES_FILTER,
                []
            ),
            function($element) : bool {
                return is_a($element, Message::class);
            }
        );
    }
}