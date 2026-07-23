<?php

namespace Pdo;

/**
 * Minimal polyfill for PHP < 8.5 where driver-specific PDO subclasses exist.
 * Laravel 12.64+ references Pdo\Mysql in its base database config.
 */
if (! class_exists(Mysql::class, false) && extension_loaded('pdo_mysql')) {
    class Mysql
    {
        public const ATTR_SSL_CA = \PDO::MYSQL_ATTR_SSL_CA;
    }
}
