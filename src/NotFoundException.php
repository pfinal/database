<?php

namespace PFinal\Database;

class NotFoundException extends \RuntimeException
{
    public function __construct($message = "Data not found.", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}