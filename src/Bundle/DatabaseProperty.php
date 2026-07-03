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
    private $sslCa;
    private $sslVerify;

    public function bindData(Map $data)
    {
        $this->dsn      = $data->get('dsn');
        $this->username = $data->get('username');
        $this->password = $data->get('password');
        // MySQL/TLS options — ignored by SQLite. ssl_ca is a path to the CA cert
        // (a hosted MySQL like Aiven ships a ca.pem and requires TLS); ssl_verify
        // = "0" skips server-cert verification (leave unset/anything else to verify).
        $ca = $data->get('ssl_ca');
        $this->sslCa = ($ca === null) ? '' : $ca;
        $verify = $data->get('ssl_verify');
        $this->sslVerify = !($verify === '0' || $verify === 0 || $verify === false);
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

    /** Path to the TLS CA certificate (MySQL); '' = none. */
    public function getSslCa()
    {
        return $this->sslCa;
    }

    /** Whether to verify the server certificate (MySQL); default true. */
    public function getSslVerify()
    {
        return $this->sslVerify;
    }
}
