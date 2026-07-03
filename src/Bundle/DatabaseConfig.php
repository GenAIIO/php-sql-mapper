<?php

namespace GenAI\SqlMapper\Bundle;

use GenAI\Di\Bean;
use GenAI\Di\Configuration;

/**
 * Registers the \PDO the compiled mappers run on as the 'PDO' container bean
 * (\PDO::class), built from DatabaseProperty (the [database] group of your
 * app.ini). It sets the connection to throw on error and to fetch ASSOCIATIVE
 * rows — the row shape the mapper hydration relies on — so you never wire any of
 * this by hand.
 *
 * Auto-discovered: genai/sql-mapper declares extra.genai.scan for this namespace,
 * so the Kernel registers it automatically; you only add a [database] group.
 *
 * Runtime class (PHP 5.3-safe); the #[...] lines are comments on 5.3. A #[Bean]
 * method runs on the runtime, so it stays 5.3-safe (object type hints are fine,
 * and the config arrives as the injected DatabaseProperty object).
 */
#[Configuration]
class DatabaseConfig
{
    #[Bean(\PDO::class)]
    public function pdo(DatabaseProperty $config)
    {
        $dsn     = $config->getDsn();
        $options = array(
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        );
        // TLS for a hosted MySQL (e.g. Aiven requires an encrypted connection).
        // Only for a mysql DSN with a CA path set — the MYSQL_ATTR_* constants exist
        // only when pdo_mysql is loaded, and this branch is reached only for mysql.
        // (charset belongs in the DSN, e.g. ;charset=utf8mb4.)
        if (strpos($dsn, 'mysql:') === 0 && $config->getSslCa() !== '') {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $config->getSslCa();
            if (!$config->getSslVerify()) {
                $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }

        return new \PDO($dsn, $config->getUsername(), $config->getPassword(), $options);
    }
}
