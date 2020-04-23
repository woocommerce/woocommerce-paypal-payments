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

    public function codes() : array
    {
        return array_values(array_map(
            function (ErrorResponse $error) : string {
                return $error->code();
            },
            $this->errors()
        ));
    }

    public function hasErrorCode(string $code) : bool
    {
        return in_array($code, $this->codes(), true);
    }
}
