<?php

namespace GenAI\SqlMapper\Attribute;

use GenAI\SqlMapper\Processor\Statement;

/**
 * #[Insert('INSERT INTO users (name) VALUES (#{name})')] on a #[Mapper] method.
 * The compiled method returns PDO::lastInsertId() after executing.
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Insert extends Statement
{
}
