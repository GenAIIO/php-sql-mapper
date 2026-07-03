<?php

namespace GenAI\SqlMapper\Processor;

use GenAI\Attribute\AttributeProcessor;
use GenAI\Attribute\Context;
use GenAI\SqlMapper\Attribute\Insert;
use GenAI\SqlMapper\Attribute\Mapper;
use GenAI\SqlMapper\Attribute\Select;
use GenAI\SqlMapper\Util\MapperDumper;

/**
 * Compiles #[Mapper] interfaces into reflection-free Cache\<Name> implementations
 * plus a Cache\Mappers registrar that binds each to its interface in the container.
 *
 * For every method it reads the one statement attribute (#[Select]/#[Insert]/
 * #[Update]/#[Delete]), rewrites #{name} placeholders to PDO :name bound params,
 * and validates — at build time — that every #{name} has a same-named method
 * parameter (mirroring genai/routing's path/param check), so a typo fails the
 * compile instead of surfacing as a bind error at runtime.
 *
 * Listens for the #[Mapper] class marker; the per-method statements are read off
 * the interface here. Build-time only (PHP 8); the dumped files are PHP 5.3-safe.
 */
class MapperProcessor implements AttributeProcessor
{
    /** @var array<int, array> one record per #[Mapper] interface */
    private array $mappers = [];

    /** @var array<string, string> flattened cache name => interface, for collision detection */
    private array $cacheNames = [];

    public function getAttributeClass(): string
    {
        return Mapper::class;
    }

    public function process(object $attribute, \Reflector $target): void
    {
        /** @var \ReflectionClass $target */
        if (!$target->isInterface()) {
            throw new \LogicException(
                '#[Mapper] must annotate an interface, but ' . $target->getName() . ' is a class.'
            );
        }

        $interface = $target->getName();

        $hydrators = array(); // hydrator method name => ['class' => fqcn, 'fields' => ...]
        $methods   = array();
        foreach ($target->getMethods() as $method) {
            $methods[] = $this->describeMethod($interface, $method, $hydrators);
        }

        $this->assertNoHydratorClash($methods, $hydrators, $interface);

        // The compiled class flattens the interface's full namespace into one name
        // under Cache\ (\ -> _), so ModuleA\ProductMapper and ModuleB\ProductMapper
        // become Cache\ModuleA_ProductMapper and Cache\ModuleB_ProductMapper — unique
        // and kept in a flat cache/ dir (no subfolders). The bean id stays the
        // interface FQCN.
        $flat = str_replace('\\', '_', $interface);
        if (isset($this->cacheNames[$flat])) {
            throw new \LogicException(
                'Two #[Mapper] interfaces compile to the same name "Cache\\' . $flat . '" ('
                . $this->cacheNames[$flat] . ' and ' . $interface
                . '). Rename one (the \\ -> _ flattening collided).'
            );
        }
        $this->cacheNames[$flat] = $interface;

        $this->mappers[] = array(
            'interface'  => $interface,
            'cacheClass' => 'Cache\\' . $flat,
            'path'       => $flat . '.php',
            'methods'    => $methods,
            'hydrators'  => $hydrators,
        );
    }

    /**
     * A generated hydrator method (hydrate<ShortClass>) must not collide with a
     * mapper method of the same name, or one would silently shadow the other.
     */
    private function assertNoHydratorClash(array $methods, array $hydrators, string $interface): void
    {
        $names = array();
        foreach ($methods as $method) {
            $names[$method['name']] = true;
        }
        foreach (array_keys($hydrators) as $hydrator) {
            if (isset($names[$hydrator])) {
                throw new \LogicException(
                    $interface . ' declares a method "' . $hydrator . '()" that collides with the '
                    . 'generated hydrator for an into: class — rename the mapper method.'
                );
            }
        }
    }

    /**
     * Read and validate one mapper method into a plan the dumper can emit.
     *
     * @return array{name:string,kind:string,sql:string,params:string[],placeholders:string[]}
     */
    private function describeMethod(string $interface, \ReflectionMethod $method, array &$hydrators): array
    {
        $where      = $interface . '::' . $method->getName() . '()';
        $statements = $method->getAttributes(Statement::class, \ReflectionAttribute::IS_INSTANCEOF);

        if (count($statements) === 0) {
            throw new \LogicException(
                $where . ' has no statement — add #[Select], #[Insert], #[Update] or #[Delete].'
            );
        }
        if (count($statements) > 1) {
            throw new \LogicException($where . ' has more than one statement attribute; use exactly one.');
        }

        $statement = $statements[0]->newInstance();
        list($sql, $placeholders) = $this->parseSql($statement->sql, $where);
        $this->assertPlaceholdersBound($placeholders, $method, $where);

        $params = array();
        foreach ($method->getParameters() as $parameter) {
            $params[] = $parameter->getName();
        }

        // A #[Select(into: Foo::class)] hydrates rows into Foo. Register (and dedupe)
        // the hydrator for that class; the dumper emits one hydrate<Foo>() per class.
        $hydrator = null;
        if ($statement instanceof Select && $statement->into !== null) {
            $hydrator = $this->registerHydrator($statement->into, $hydrators, $where);
        }

        return array(
            'name'         => $method->getName(),
            'kind'         => $this->kindOf($statement),
            'sql'          => $sql,
            'params'       => $params,
            'placeholders' => $placeholders,
            'hydrator'     => $hydrator,
        );
    }

    /**
     * Validate the into: class and add its hydrator to the per-mapper set, keyed by
     * the generated method name (hydrate<ShortClass>). Returns that name so the
     * select method can call it.
     */
    private function registerHydrator(string $class, array &$hydrators, string $where): string
    {
        $class = ltrim($class, '\\');

        if (!class_exists($class)) {
            throw new \LogicException($where . ' into: "' . $class . '" — no such class.');
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if (!$reflection->isInstantiable()
            || ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0)) {
            throw new \LogicException(
                $where . ' into: "' . $class . '" must be instantiable with a no-argument constructor '
                . '(rows are hydrated via setters / public properties).'
            );
        }

        $name = 'hydrate' . $reflection->getShortName();
        if (isset($hydrators[$name]) && $hydrators[$name]['class'] !== $class) {
            throw new \LogicException(
                $where . ' into: "' . $class . '" collides with "' . $hydrators[$name]['class']
                . '" — two hydrated classes share the short name "' . $reflection->getShortName() . '".'
            );
        }

        $hydrators[$name] = array('class' => $class, 'fields' => $this->hydrationFields($reflection, $where));

        return $name;
    }

    /**
     * Map a result class's writable members to columns by name: a public setter
     * setXxx($v) (preferred) or a public property, keyed by the field name a row
     * column must match. Each is guarded at runtime, so a partial SELECT is fine.
     *
     * @return array<string, array{kind:string,member:string}>
     */
    private function hydrationFields(\ReflectionClass $reflection, string $where): array
    {
        $fields = array();

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if ($method->isStatic() || strlen($name) <= 3 || substr($name, 0, 3) !== 'set') {
                continue;
            }
            if ($method->getNumberOfParameters() < 1 || $method->getNumberOfRequiredParameters() > 1) {
                continue;
            }
            $fields[lcfirst(substr($name, 3))] = array('kind' => 'setter', 'member' => $name);
        }

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $field = $property->getName();
            if (!isset($fields[$field])) { // a setter for the same field wins
                $fields[$field] = array('kind' => 'property', 'member' => $field);
            }
        }

        if (empty($fields)) {
            throw new \LogicException(
                $where . ' into: "' . $reflection->getName() . '" has no public setters or '
                . 'properties to hydrate into.'
            );
        }

        return $fields;
    }

    /**
     * Map a statement to how the runtime should execute it: a Select returns all
     * rows (or one, with one: true); an Insert returns lastInsertId(); an Update
     * or Delete returns the affected-row count.
     */
    private function kindOf(Statement $statement): string
    {
        if ($statement instanceof Select) {
            return $statement->one ? 'selectOne' : 'selectAll';
        }
        if ($statement instanceof Insert) {
            return 'insert';
        }

        return 'modify'; // Update | Delete
    }

    /**
     * Rewrite #{name} -> :name (PDO named placeholder), returning the converted
     * SQL and the ordered list of placeholder names. ${...} raw interpolation is
     * rejected for now (it is the injection-prone form and needs its own design).
     *
     * @return array{0:string,1:string[]}
     */
    private function parseSql(string $sql, string $where): array
    {
        if (strpos($sql, '${') !== false) {
            throw new \LogicException(
                $where . ' uses ${...} interpolation, which is not supported yet — '
                . 'use #{name} for a bound parameter.'
            );
        }

        $placeholders = array();
        $converted = preg_replace_callback(
            '/#\{(\w+)\}/',
            function ($match) use (&$placeholders) {
                $placeholders[] = $match[1];
                return ':' . $match[1];
            },
            $sql
        );

        return array($converted, $placeholders);
    }

    /**
     * Every #{name} must have a same-named method parameter to bind from.
     */
    private function assertPlaceholdersBound(array $placeholders, \ReflectionMethod $method, string $where): void
    {
        $params = array();
        foreach ($method->getParameters() as $parameter) {
            $params[$parameter->getName()] = true;
        }

        foreach ($placeholders as $name) {
            if (!isset($params[$name])) {
                throw new \LogicException(
                    $where . ' binds #{' . $name . '} but declares no $' . $name . ' parameter.'
                );
            }
        }
    }

    public function compile(Context $context): void
    {
        if (empty($this->mappers)) {
            return; // nothing marked #[Mapper] -> emit no files (System won't list Mappers)
        }

        foreach ($this->mappers as $mapper) {
            $this->write(
                $context->output($mapper['path']),
                MapperDumper::dumpMapper(
                    $mapper['cacheClass'],
                    $mapper['interface'],
                    $mapper['methods'],
                    $mapper['hydrators']
                )
            );
        }

        $this->write($context->output('Mappers.php'), MapperDumper::dumpRegistrar($this->mappers));
    }

    private function write(string $path, string $source): void
    {
        // Flat names keep everything directly in cache/ (no subdirectories).
        $bytes = @file_put_contents($path, $source);
        if ($bytes === false) {
            throw new \RuntimeException('Could not write compiled mapper to "' . $path . '".');
        }
    }
}
