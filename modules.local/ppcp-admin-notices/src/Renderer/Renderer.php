<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Renderer;


use Inpsyde\PayPalCommerce\AdminNotices\Repository\RepositoryInterface;

class Renderer implements RendererInterface
{
    private $repository;
    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function render(): bool
    {
        $messages = $this->repository->currentMessages();
        foreach ($messages as $message) {
            printf(
                '<div class="notice notice-%s %s"><p>%s</p></div>',
                $message->type(),
                ($message->isDismissable()) ? 'is-dismissible' : '',
                wp_kses_post($message->message())
            );
        }

        return (bool) count($messages);
    }
}