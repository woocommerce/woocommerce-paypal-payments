<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Entity;


class Message
{

    private $message;
    private $type;
    private $dismissable;
    public function __construct(string $message, string $type, bool $dismissable = true)
    {
        $this->type = $type;
        $this->message = $message;
        $this->dismissable = $dismissable;
    }

    public function message() : string
    {
        return $this->message;
    }

    public function type() : string
    {
        return $this->type;
    }

    public function isDismissable() : bool
    {
        return $this->dismissable;
    }
}