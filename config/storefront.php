<?php

return [
    // Deliberately disabled. Turning this on alone still cannot bypass the
    // allocator's independent P02 isolated-test-DB identity guard.
    // Production release needs reconciled stock and a separately reviewed cutover.
    'p03_core_test_enabled' => false,

    // OFF by default. Test-only bridge across campaign, manual and bulk order
    // creation. Still denied on all databases except p01_campaign_test.
    'p03_order_writer_test_enabled' => false,

    // P07 read-only browsing; default OFF, independently restricted to the local
    // p01_campaign_test DB by PublicCatalog::assertAvailable().
    'p07_catalog_test_enabled' => env('P07_CATALOG_TEST_ENABLED', false),
];
