<?php

namespace GenAI\SqlMapper\Attribute;

use GenAI\SqlMapper\Processor\Statement;

/**
 * #[Update('UPDATE users SET name = #{name} WHERE id = #{id}')] on a #[Mapper]
 * method. The compiled method returns the affected-row count (PDOStatement::
 * rowCount()) after executing.
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Update extends Statement
{
}
