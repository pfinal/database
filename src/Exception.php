<?php

namespace PFinal\Database;

/**
 * Database Exception
 * @since   1.0
 */
class Exception extends \LogicException
{
    public function __construct($message = "Database Exception", $code = 500, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}