<?php

namespace AlisQI\TwigQI\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;

class TriggerErrorLogger extends AbstractLogger
{
    /**
     * Maps PSR log levels to PHP error constants.
     */
    private const LEVEL_MAP = [
        LogLevel::EMERGENCY => E_USER_ERROR,
        LogLevel::ALERT     => E_USER_ERROR,
        LogLevel::CRITICAL  => E_USER_ERROR,
        LogLevel::ERROR     => E_USER_ERROR,
        LogLevel::WARNING   => E_USER_WARNING,
        LogLevel::NOTICE    => E_USER_NOTICE,
        LogLevel::INFO      => E_USER_NOTICE,
        LogLevel::DEBUG     => E_USER_NOTICE,
    ];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (null === ($psrErrorLevel = self::LEVEL_MAP[$level] ?? null)) {
            throw new InvalidArgumentException(sprintf('Invalid log level: %s', $level));
        }

        if ($message instanceof \Stringable) {
            $message = (string)$message;
        }

        // interpolate context
        $message = preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $matches) use ($context): string {
                if (null !== ($contextVariable = $context[$matches[1]] ?? null)) {
                    if (
                        !is_array($contextVariable) &&
                        (!is_object($contextVariable) || method_exists($contextVariable, '__toString'))
                    ) {
                        return (string)$contextVariable;
                    }
                }

                return $matches[0];
            },
            $message
        );

        trigger_error($message, $psrErrorLevel);
    }
}
