<?php

namespace PFinal\Database;

class NotFoundException extends Exception
{
    public function __construct($message = "DatabaseNotFoundException", $code = 500, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}