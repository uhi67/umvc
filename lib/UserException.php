<?php

namespace uhi67\umvc;

use Exception;

/**
 * An Exception to display to the end-user
 *
 * @package UMVC Simple Application Framework
 */
class UserException extends Exception
{

    /**
     * @param string $message
     * @param int $status
     * @param Exception $previous
     */
    public function __construct($message, $status, $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}
