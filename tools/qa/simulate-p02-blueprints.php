<?php

/**
 * Offline, in-memory Laravel Blueprint declaration simulation for P02 ONLY.
 * Uses explicit local stubs; does not bootstrap Laravel or contact a database.
 * PASS here is NOT proof that MariaDB executes generated SQL successfully.
 */

namespace Illuminate\Database\Migrations {
    abstract class Migration {}
}

namespace App\Support {
    final class P02MigrationSafety {
        public static function assertIsolatedTestDatabase(): void {}
    }
}

namespace Illuminate\Database\Schema {
    use RuntimeException;

    final class ColumnDefinition
    {
        public function __construct(private Blueprint $table, private string $name) {}

        public function __call(string $method, array $args): static
        {
            if ($method === 'constrained') {
                $target = $args[0] ?? null;
                if (! is_string($target) || $target === '') {
                    throw new RuntimeException('The QA stub requires an explicit FK table.');
                }
                $this->table->foreignKeys[] = [[$this->name], $target, ['id']];
            }
            return $this;
        }
    }

    final class ForeignDefinition
    {
        private array $references = [];

        public function __construct(private Blueprint $table, private array $columns) {}

        public function references(string|array $columns): static
        {
            $this->references = (array) $columns;
            return $this;
        }

        public function on(string $target): static
        {
            $this->table->foreignKeys[] = [$this->columns, $target, $this->references];
            return $this;
        }

        public function __call(string $name, array $args): static { return $this; }
    }

    final class Blueprint
    {
        public array $columns = [];
        public array $foreignKeys = [];
        public array $keys = [];

        public function __construct(public readonly string $table) {}

        public function __call(string $method, array $args): mixed
        {
            $columnTypes = ['id','foreignId','foreignUuid','uuid','char','string','boolean','unsignedBigInteger','unsignedInteger','unsignedSmallInteger','integer','text','json','decimal','timestamp','softDeletes'];
            if (in_array($method, $columnTypes, true)) {
                $name = $method === 'id' ? ($args[0] ?? 'id') : ($method === 'softDeletes' ? ($args[0] ?? 'deleted_at') : ($args[0] ?? ''));
                if (! is_string($name) || $name === '') throw new RuntimeException('Missing column name for '.$method);
                $this->columns[$name] = $method;
                return new ColumnDefinition($this, $name);
            }
            if ($method === 'timestamps') {
                $this->columns['created_at'] = 'timestamp';
                $this->columns['updated_at'] = 'timestamp';
                return null;
            }
            if ($method === 'foreign') {
                return new ForeignDefinition($this, (array) ($args[0] ?? []));
            }
            if (in_array($method, ['index','unique','primary'], true)) {
                $this->keys[] = [(array) ($args[0] ?? []), $method];
                return null;
            }
            throw new RuntimeException('Unrecognized Blueprint method in simulation: '.$method.' on '.$this->table);
        }
    }
}

namespace Illuminate\Support\Facades {
    use Illuminate\Database\Schema\Blueprint;
    use RuntimeException;

    final class Schema
    {
        /** @var array<string, Blueprint> */
        public static array $tables = [];

        public static function create(string $name, callable $callback): void
        {
            if (isset(self::$tables[$name])) throw new RuntimeException('Duplicate table: '.$name);
            $blueprint = new Blueprint($name);
            self::$tables[$name] = $blueprint;
            $callback($blueprint);
        }

        public static function table(string $name, callable $callback): void
        {
            if (! isset(self::$tables[$name])) throw new RuntimeException('Missing base table: '.$name);
            $callback(self::$tables[$name]);
        }
    }
}

namespace {
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;
    $root = dirname(__DIR__, 2);
    // Represent only baseline table/column facts required by P02; not a real SQL schema.
    foreach ([
        'categories' => ['id','status'],
        'products' => ['id','stock','status','deleted_at','product_code'],
        'orders' => ['id','order_status','sub_total','shipping_charge','cod_charge','total_amount'],
        'order_items' => ['id','order_id','unit_price','total_price','discount_amount'],
        'users' => ['id'], 'campaigns' => ['id'], 'media' => ['id'], 'reviews' => ['id'],
    ] as $table => $columns) {
        $b = new Blueprint($table);
        foreach ($columns as $column) $b->columns[$column] = 'existing';
        Schema::$tables[$table] = $b;
    }

    $files = glob($root.'/database/migrations/2026_10_24_21*_p02_*.php');
    sort($files);
    if (count($files) !== 11) throw new RuntimeException('P02 expected exactly 11 migrations');
    foreach ($files as $file) {
        $migration = require $file;
        $migration->up();
    }

    $foreignCount = 0;
    foreach (Schema::$tables as $name => $table) {
        foreach ($table->foreignKeys as [$local, $targetName, $remote]) {
            if (! isset(Schema::$tables[$targetName])) throw new RuntimeException("$name references missing table $targetName");
            if (count($local) !== count($remote)) throw new RuntimeException("$name composite foreign key width mismatch");
            foreach ($local as $col) {
                if (! isset($table->columns[$col])) throw new RuntimeException("$name FK references missing local column $col");
            }
            foreach ($remote as $col) {
                if (! isset(Schema::$tables[$targetName]->columns[$col])) {
                    throw new RuntimeException("$name FK references missing target column $targetName.$col");
                }
            }
            $foreignCount++;
        }
    }
    echo "PASS: 11 migration up() declaration callbacks executed against in-memory stubs.\n";
    echo 'PASS: '.(count(Schema::$tables) - 8).' new tables, '.$foreignCount." foreign-key references resolve to declared columns.\n";
    echo "NOTE: No Laravel boot, real MySQL SQL, migrations, or database connections were performed.\n";
}
