<?php

/** Offline-only SKU contract test: no Laravel boot or database used. */
namespace Illuminate\Console {
    abstract class Command {}
}
namespace {
    require __DIR__ . '/../../app/Console/Commands/BackfillDefaultProductVariants.php';

    $type = \App\Console\Commands\BackfillDefaultProductVariants::class;
    if ($type::legacySku(42) !== 'LEGACY-42'
        || $type::legacySku(123) !== 'LEGACY-123'
        || $type::legacySku(42) === $type::legacySku(43)) {
        throw new \RuntimeException('Legacy SKU deterministic uniqueness failed');
    }
    try {
        $type::legacySku(0);
        throw new \RuntimeException('Invalid legacy product ID unexpectedly accepted');
    } catch (\RuntimeException $e) {
        if ($e->getMessage() === 'Invalid legacy product ID unexpectedly accepted') {
            throw $e;
        }
    }
    echo "PASS deterministic legacy SKU fallback and invalid-ID rejection.\n";
    echo "NOTE: No actual database writes or Laravel bootstrap.\n";
}
