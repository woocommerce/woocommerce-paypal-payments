<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class ErrorResponseCollection
{

    private $errors;

    public function __construct(ErrorResponse ...$errors)
    {
        $this->errors = $errors;
    }

    /**
     * @return ErrorResponse[]
     */
    public function errors() : array
    {
        return $this->errors;
    }

    public function hasErrorCode(string $code) : bool
    {
        foreach ($this->errors() as $error) {
            if ($error->is($code)) {
                return true;
            }
        }
        return false;
    }
}
