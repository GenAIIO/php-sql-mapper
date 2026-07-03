<?php

namespace GenAI\SqlMapper\Bundle;

use GenAI\Property\AbstractProperty;
use GenAI\Property\Attribute\Property;
use GenAI\Property\Util\Map;

/**
 * The database connection genai/sql-mapper needs, as typed config. The #[Property]
 * line binds it, at build time, to the [database] group of the app's main config
 * file (config/app.ini, the default) — no separate database file to discover:
 *
 *   [database]
 *   dsn      = "mysql:host=localhost;dbname=app;charset=utf8"
 *   username = "app"
 *   password = "secret"
 *
 * DatabaseConfig turns this into the 'PDO' container bean the compiled mappers use,
 * so configuring the database is just adding that group to app.ini.
 *
 * Runtime class (PHP 5.3-safe); the #[Property] line is a comment on 5.3.
 */
#[Property(group: 'database')]
class DatabaseProperty extends AbstractProperty
{
    private $dsn;
    private $username;
    private $password;

    public function bindData(Map $data)
    {
        $this->dsn      = $data->get('dsn');
        $this->username = $data->get('username');
        $this->password = $data->get('password');
    }

    public function getDsn()
    {
        return $this->dsn;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }
}
