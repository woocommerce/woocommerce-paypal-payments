<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\ErrorResponse;
use Inpsyde\PayPalCommerce\ApiClient\Entity\ErrorResponseCollection;

class ErrorResponseCollectionFactory
{

    public function fromPayPalResponse(
        \stdClass $response,
        int $statusCode,
        string $url,
        array $args
    ) : ErrorResponseCollection {

        if (isset($response->error)) {
            return new ErrorResponseCollection(
                new ErrorResponse(
                    (string) $response->error,
                    (isset($response->error->description)) ? (string) $response->error->description : '',
                    $statusCode,
                    $url,
                    $args
                )
            );
        }
        if (! isset($response->details) || ! is_array($response->details)) {
            return new ErrorResponseCollection();
        }

        $errors = [];
        foreach ($response->details as $detail) {
            $errors[] = new ErrorResponse(
                (string) $detail->issue,
                (string) $detail->description,
                $statusCode,
                $url,
                $args
            );
        }

        return new ErrorResponseCollection(... $errors);
    }

    public function unknownError(string $url, array $args) : ErrorResponseCollection
    {
        return new ErrorResponseCollection(
            new ErrorResponse(
                ErrorResponse::UNKNOWN,
                'unknown',
                0,
                $url,
                $args
            )
        );
    }
}
