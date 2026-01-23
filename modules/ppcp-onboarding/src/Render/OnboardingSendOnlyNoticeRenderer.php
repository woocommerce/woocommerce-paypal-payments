<?php

/**
 * Creates an admin message that notifies user about send only country while onboarding.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Render
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Onboarding\Render;

/**
 * Class OnboardingRenderer
 */
class OnboardingSendOnlyNoticeRenderer
{
    /**
     * The notice message.
     *
     * @var string
     */
    protected string $message;
    /**
     * AdminNotice constructor.
     *
     * @param string $message The notice message.
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }
    /**
     * Renders the notice.
     *
     * @return string
     */
    public function render(): string
    {
        return '<div class="notice notice-warning ppcp-notice-wrapper inline"><p>' . $this->message . '</p></div>';
    }
}
