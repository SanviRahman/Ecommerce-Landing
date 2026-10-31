<?php

declare(strict_types=1);

/** Standalone P59 safety smoke: stubs only, NO Laravel DB/HTTP requests. */
namespace Illuminate\Support\Facades {
    final class Schema {
        public static function hasTable(string $table): bool {
            return !isset($GLOBALS['p59_missing_table']) || $GLOBALS['p59_missing_table'] !== $table;
        }
    }
}
namespace App\Support {
    final class P58ProductionStorefrontAccess {
        public function assertApprovedDatabase($request, bool $requireCommerceEnabled = true): void {
            if ($requireCommerceEnabled !== false) throw new \RuntimeException('Expected maintenance to support sales pause');
            if (!$GLOBALS['p59_database_approved']) throw new \RuntimeException('Unapproved database');
        }
    }
    final class P32LocalStorefrontAccess {
        public static function matchesContext(...$args): bool { return false; }
        public function assertActualMainDatabase(): void { throw new \RuntimeException('Unexpected local gate'); }
    }
    final class P02MigrationSafety {
        public static function assertIsolatedTestDatabase(): void { throw new \RuntimeException('Unexpected isolated gate'); }
    }
}
namespace {
    class P59FakeAdmin {
        public bool $is_active = true;
        public function isAdmin(): bool { return $GLOBALS['p59_is_admin']; }
    }
    function auth(): object {
        return new class {
            public function check(): bool { return $GLOBALS['p59_authenticated']; }
            public function user(): P59FakeAdmin { return new P59FakeAdmin(); }
        };
    }
    function config(string $key): mixed {
        return ['app.env'=>'production','app.debug'=>false][$key] ?? null;
    }
    function request(): object { return (object)['host'=>'example.test']; }
    function app(string $class): object { return new $class(); }
    function abort_unless(bool $condition, int $code): void {
        if (!$condition) throw new \RuntimeException('Forbidden', $code);
    }

    require dirname(__DIR__, 2).'/app/Support/P33CatalogAdminAccess.php';
    $GLOBALS['p59_authenticated'] = true;
    $GLOBALS['p59_is_admin'] = true;
    $GLOBALS['p59_database_approved'] = true;
    $cases = [
        'approved active admin + complete schema' => [true, true, true, null, true],
        'anonymous denied' => [false, true, true, null, false],
        'employee denied' => [true, false, true, null, false],
        'wrong actual production DB denied' => [true, true, false, null, false],
        'missing product_variant_values schema denied' => [true, true, true, 'product_variant_values', false],
    ];
    $passed=0;
    foreach ($cases as $label=>$case) {
        [$GLOBALS['p59_authenticated'],$GLOBALS['p59_is_admin'],$GLOBALS['p59_database_approved'],$missing,$expected] = $case;
        $GLOBALS['p59_missing_table']=$missing;
        $actual=true;
        try { \App\Support\P33CatalogAdminAccess::assertAllowed(); }
        catch (\Throwable) { $actual=false; }
        if ($actual!==$expected) { fwrite(STDERR,"FAIL $label\n");exit(1); }
        echo "PASS $label\n"; $passed++;
    }
    echo "P59 ISOLATED AUTHORIZATION: $passed/".count($cases)."\n";
}
