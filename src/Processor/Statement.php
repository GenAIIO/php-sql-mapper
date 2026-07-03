<?php

namespace GenAI\SqlMapper\Processor;

/**
 * Base for the SQL statement attributes — #[Select], #[Insert], #[Update],
 * #[Delete]. Holds the raw SQL (with #{name} bound-parameter placeholders); the
 * subclass fixes the execution kind. MapperProcessor matches any of them on a
 * #[Mapper] method via ReflectionAttribute::IS_INSTANCEOF, so it never has to
 * know the concrete statement type.
 *
 * Not an attribute itself (only its subclasses are #[\Attribute]) and never
 * applied directly — it's processor plumbing, so it lives next to the processor
 * that consumes it. BUILD-TIME ONLY (PHP 8); never loaded on the PHP 5.3 runtime.
 */
class Statement
{
    public function __construct(public string $sql)
    {
    }
}
