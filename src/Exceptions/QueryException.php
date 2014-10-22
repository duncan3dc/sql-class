<?php

namespace duncan3dc\SqlClass\Exceptions;

class QueryException extends \Exception
{
    const UNKNOWN_ERROR = -999;

    public function __construct($message, $code = self::UNKNOWN_ERROR, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }


    public function __toString()
    {
        $message = $this->message;
        if ($this->code !== QueryException::UNKNOWN_ERROR) {
            $message .= " (Error Code: " . $this->code . ")";
        }

        return __CLASS__ . ": " . $message . "\n" . $this->getTraceAsString();
    }
}
