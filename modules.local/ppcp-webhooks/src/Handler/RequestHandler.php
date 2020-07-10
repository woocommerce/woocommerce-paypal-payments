<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;


interface RequestHandler
{

    public function eventTypes() : array;

    public function responsibleForRequest(\WP_REST_Request $request) : bool;

    public function handleRequest(\WP_REST_Request $request) : \WP_REST_Response;
}