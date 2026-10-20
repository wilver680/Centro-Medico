<?php

declare(strict_types=1);

namespace CentroMedico;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $database = Config::get('DB_NAME');
            $username = Config::get('DB_USER');
            $password = Config::get('DB_PASSWORD');
            if ($database === '' || $username === '' || $password === '') {
                throw new \RuntimeException('La configuracion de base de datos esta incompleta.');
            }

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                Config::get('DB_HOST', '127.0.0.1'),
                Config::get('DB_PORT', '5432'),
                $database
            );

            self::$connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $timezone = self::$connection->prepare("SELECT set_config('TimeZone', :timezone, false)");
            $timezone->execute(['timezone' => Config::get('APP_TIMEZONE', 'America/Guatemala')]);
        }

        return self::$connection;
    }
}

