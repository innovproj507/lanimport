<?php

declare(strict_types=1);

namespace Courier\Shared\Infrastructure\Logging;

use Courier\Shared\Infrastructure\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class LoggerFactory
{
    private static ?Logger $instance = null;

    public static function getInstance(string $basePath): Logger
    {
        if (self::$instance === null) {
            $channel = Config::get('LOG_CHANNEL', 'app');
            $levelName = Config::get('LOG_LEVEL', 'debug');
            $level = Level::fromName(strtoupper($levelName));

            $logger = new Logger($channel);
            $logger->pushHandler(new StreamHandler($basePath . '/storage/logs/app.log', $level));

            self::$instance = $logger;
        }

        return self::$instance;
    }
}
