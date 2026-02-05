<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

class SimpleLogger extends Component implements LoggerInterface
{

    public string $logLevel = LogLevel::INFO;
    public ?string $logFile = null;
    public ?string $logFormat = '{$date} {$level} ({$uid}) [{$sid}] {$message}';

    public function init(): void {
        if (!$this->logFile) {
            $this->logFile = App::$app->runtimePath . '/logs/app.log';
        }
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::ALERT, $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::ERROR, $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::WARNING, $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::NOTICE, $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::INFO, $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        static::log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $sid = session_id();
        $uid = App::$app->getUserId();
        if (!is_string($message) && !is_a($message, Stringable::class)) {
            $message = json_encode($message);
        }
        if ($context) {
            foreach ($context as $k => $v) {
                $message = str_replace("\{$k\}", $v, $message);
            }
        } else {
            $context = [];
        }
        $output = $this->logFormat;
        $context['date'] = date(DATE_ATOM);
        $context['level'] = $level;
        $context['uid'] = $uid;
        $context['sid'] = $sid;
        $context['message'] = $message;
        foreach ($context as $k => $v) {
            $output = str_replace("\{$k\}", $v, $output);
        }
        file_put_contents($this->logFile, $output . PHP_EOL, FILE_APPEND + LOCK_EX);
    }
}