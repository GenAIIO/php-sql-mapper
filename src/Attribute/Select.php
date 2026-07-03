<?php

namespace GenAI\SqlMapper\Attribute;

use GenAI\SqlMapper\Processor\Statement;

/**
 * #[Select('SELECT ... WHERE id = #{id}')] on a #[Mapper] method.
 *
 * Returns the result rows (an array of associative arrays). With one: true it
 * returns a single row, or null when nothing matched — use it for by-id lookups.
 *
 * With into: User::class the rows are hydrated into that class instead: the build
 * compiles a reflection-free hydrator (column => setter/public property, matched
 * by name) so each row becomes an object — one object (or null) with one: true,
 * an array of objects otherwise. The target needs a no-argument constructor.
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Select extends Statement
{
    public function __construct(string $sql, public bool $one = false, public ?string $into = null)
    {
        parent::__construct($sql);
    }
}
