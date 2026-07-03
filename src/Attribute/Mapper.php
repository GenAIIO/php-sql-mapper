<?php

namespace GenAI\SqlMapper\Attribute;

/**
 * Marks an interface as a SQL mapper (MyBatis style). Its methods carry the SQL
 * via #[Select]/#[Insert]/#[Update]/#[Delete]; MapperProcessor compiles a
 * reflection-free Cache\<Name> implementation and registers it as a container
 * bean keyed by the interface, so a controller/service can type-hint the
 * interface and get the compiled mapper injected.
 *
 *   #[Mapper]
 *   interface UserMapper
 *   {
 *       #[Select('SELECT * FROM users WHERE id = #{id}', one: true)]
 *       public function findById($id);
 *   }
 *
 * BUILD-TIME ONLY (PHP 8). On the PHP 5.3 runtime the #[Mapper] line is a plain
 * comment; only the interface itself is loaded (so keep it 5.3-safe: no scalar
 * type hints or return types). Requires the genai/attribute scanner (a suggest).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Mapper
{
}
