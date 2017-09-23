<?php

namespace PFinal\Database;

use Throwable;

class NotFoundException extends Exception
{
    public function __construct($message = "DatabaseNotFoundException", $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}