<?php

namespace GenAI\SqlMapper;

/**
 * Runtime base for every compiled Cache\<Name> mapper. It only holds the PDO
 * connection — the generated subclass inlines each statement (prepare / execute
 * / fetch) directly, so there is no runtime reflection and no shared helper
 * method that could clash with a mapper method name (a mapper might legitimately
 * declare insert(), execute(), etc.).
 *
 * The PDO is injected by the compiled Cache\Mappers registrar, which pulls the
 * 'PDO' container bean. genai/sql-mapper ships that bean itself — see
 * Bundle\DatabaseConfig, built from the [database] group of the app's app.ini —
 * so configuring the database is just editing that file, not wiring it here.
 *
 * Compatible with PHP 5.3.29.
 */
class AbstractMapper
{
    /** @var \PDO */
    protected $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}
