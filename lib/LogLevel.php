<?php

namespace uhi67\umvc;

class LogLevel extends \Psr\Log\LogLevel
{
    /** @var array log levels from the lowest to the highest */
    const LOG_LEVELS = [
        \Psr\Log\LogLevel::EMERGENCY,
        \Psr\Log\LogLevel::ALERT,
        \Psr\Log\LogLevel::CRITICAL,
        \Psr\Log\LogLevel::ERROR,
        \Psr\Log\LogLevel::WARNING,
        \Psr\Log\LogLevel::NOTICE,
        \Psr\Log\LogLevel::INFO,
        \Psr\Log\LogLevel::DEBUG
    ];

    /** Returns true if the first log level is not lower than the second one */
    public static function isHigherOrEqual(string $level, string $logLevel): bool
    {
        return array_search($level, self::LOG_LEVELS) >= array_search($logLevel, self::LOG_LEVELS);
    }
}