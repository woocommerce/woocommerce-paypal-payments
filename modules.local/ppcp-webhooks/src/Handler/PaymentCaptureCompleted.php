<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;


class PaymentCaptureCompleted implements RequestHandler
{


    public function eventType(): string
    {
        return 'PAYMENT.CAPTURE.COMPLETED';
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return true;
    }

    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response();
    }
}