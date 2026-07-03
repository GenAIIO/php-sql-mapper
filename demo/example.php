<?php

/**
 * genai/sql-mapper demo — a MyBatis-style mapper, compiled, with the database
 * configured the way a real app does it: a [database] group in config/app.ini.
 *
 *   composer install
 *   php example.php          (needs ext-pdo_sqlite)
 *
 * BUILD (PHP 8): the scanner compiles, with processors auto-discovered by type:
 *   - Demo\UserMapper            -> cache/Demo_UserMapper.php + cache/Mappers.php  (MapperProcessor)
 *   - the sql-mapper bundle        -> the 'PDO' bean (DatabaseConfig), bound to the
 *                                    [database] group of config/app.ini via DatabaseProperty
 *                                    (ComponentProcessor -> Container.php, PropertyProcessor -> Properties.php)
 *
 * RUNTIME (PHP 5.3-safe): build the container, resolve the mapper by interface,
 * call it. The connection comes from app.ini — nothing is wired by hand here.
 */

use GenAI\Attribute\Context;
use GenAI\Attribute\Scanner;

$loader = require __DIR__ . '/vendor/autoload.php';

@mkdir(__DIR__ . '/cache', 0777, true);

// ----- build -----------------------------------------------------------------
$scanner = new Scanner($loader);
$scanner->scan(array(
    'Demo',                         // the #[Mapper] interface + the Demo\User entity
    'GenAI\\SqlMapper\\Bundle',     // DatabaseProperty + DatabaseConfig (the 'PDO' bean)
    'GenAI\\SqlMapper\\Processor',  // MapperProcessor
    'GenAI\\Di\\Processor',         // ComponentProcessor -> Container.php (incl. the PDO bean)
    'GenAI\\Property\\Attribute',   // PropertyProcessor  -> Properties.php (DatabaseProperty)
));
$scanner->compile(new Context(__DIR__ . '/config', __DIR__ . '/cache'));

echo "===== cache/Demo_UserMapper.php =====\n";
echo file_get_contents(__DIR__ . '/cache/Demo_UserMapper.php') . "\n";

// ----- runtime ---------------------------------------------------------------
// Build the container the way the Kernel does: the compiled container subclass,
// the #[Property] beans, then the mappers.
$container = new \Cache\Container();
\Cache\Properties::loadInto($container);   // binds DatabaseProperty from config/app.ini [database]
\Cache\Mappers::loadInto($container);

// The 'PDO' bean is built by the bundle's DatabaseConfig from app.ini — no manual
// connection here. Grab it once to create the demo schema (in-memory sqlite).
$pdo = $container->get('PDO');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

/** @var Demo\UserMapper $users */
$users = $container->get('Demo\\UserMapper');   // resolved by interface; same 'PDO' singleton injected

echo "===== run =====\n";
$alice = $users->create('Alice');
$bob   = $users->create('Bob');
echo "created ids: $alice, $bob\n";

$users->rename($bob, 'Bobby');         // UPDATE -> affected rows
$users->deleteById($alice);            // DELETE -> affected rows

echo "findById($bob):   " . json_encode($users->findById($bob)) . "\n";
echo "findById($alice):   " . json_encode($users->findById($alice)) . "  (deleted -> null)\n";
echo "findAll:          " . json_encode($users->findAll()) . "\n";

// into: User::class -> hydrated objects (no runtime reflection).
$user = $users->load($bob);
echo "\nload($bob):       " . get_class($user) . " -> getName() = " . $user->getName() . "\n";
$all = $users->loadAll();
echo "loadAll:          " . count($all) . " x " . get_class($all[0]) . "\n";
foreach ($all as $one) {
    echo "  - #" . $one->getId() . " " . $one->getName() . "\n";
}
