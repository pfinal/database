<?php

namespace PFinal\Database;

use Throwable;

/**
 * Database Exception
 * @since   1.0
 */
class Exception extends \Exception
{
    public function __construct($message = "Database Exception", $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}