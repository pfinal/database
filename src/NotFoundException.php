<?php

namespace PFinal\Database;

class NotFoundException extends Exception
{
    public function __construct($message = "Data not found exception", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}