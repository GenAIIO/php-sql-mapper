<?php

namespace Demo;

use GenAI\SqlMapper\Attribute\Delete;
use GenAI\SqlMapper\Attribute\Insert;
use GenAI\SqlMapper\Attribute\Mapper;
use GenAI\SqlMapper\Attribute\Select;
use GenAI\SqlMapper\Attribute\Update;

/**
 * A MyBatis-style mapper: just an interface + the SQL. The build compiles a
 * reflection-free Cache\UserMapper and wires it into the container, keyed by this
 * interface — so consumers type-hint Demo\UserMapper and get it injected.
 *
 * Runtime interface (PHP 5.3-safe): no scalar type hints or return types, since
 * the interface itself is loaded at runtime. The #[...] lines are build-only.
 */
#[Mapper]
interface UserMapper
{
    #[Select('SELECT id, name FROM users ORDER BY id')]
    public function findAll();

    #[Select('SELECT id, name FROM users WHERE id = #{id}', one: true)]
    public function findById($id);

    // Same query, hydrated into Demo\User objects (one, or null) instead of a row.
    #[Select('SELECT id, name FROM users WHERE id = #{id}', one: true, into: User::class)]
    public function load($id);

    // A list of Demo\User objects.
    #[Select('SELECT id, name FROM users ORDER BY id', into: User::class)]
    public function loadAll();

    #[Insert('INSERT INTO users (name) VALUES (#{name})')]
    public function create($name);

    #[Update('UPDATE users SET name = #{name} WHERE id = #{id}')]
    public function rename($id, $name);

    #[Delete('DELETE FROM users WHERE id = #{id}')]
    public function deleteById($id);
}
