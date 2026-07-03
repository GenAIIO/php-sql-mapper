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
        $pdo = new \PDO($config->getDsn(), $config->getUsername(), $config->getPassword());
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }
}
