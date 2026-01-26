<?php

namespace App\Exceptions;

use Exception;

class MissingRequiredFieldsException extends Exception
{
    protected $missingFields;

    public function __construct($message = "", $missingFields = [], $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->missingFields = $missingFields;
    }

    public function getMissingFields()
    {
        return $this->missingFields;
    }
}
