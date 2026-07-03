<?php

namespace Demo;

/**
 * A plain entity hydrated from a result row. Needs a no-argument constructor; the
 * compiled hydrator matches columns to setters (or public properties) by name.
 *
 * Runtime class (PHP 5.3-safe).
 */
class User
{
    private $id;
    private $name;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}
