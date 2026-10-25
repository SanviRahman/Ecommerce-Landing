<?php

return [
    // P14 LOCAL DEV ONLY. Menu and homepage CRUD still verify actual DB identity.
    // Never set this on production; false unless explicit .env opt-in.
    'p14_local_homepage_builder_enabled' => env('P14_LOCAL_HOMEPAGE_BUILDER_ENABLED', false) === true
        || env('P14_LOCAL_HOMEPAGE_BUILDER_ENABLED', false) === 'true',

    // Separate one-operation consent for the 11 forward P02 schema migrations.
    // The underlying P02 test guard remains unchanged for all other services.
    'p14_local_p02_migration_approval' => env('P14_LOCAL_P02_MIGRATION_APPROVED', ''),

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

    // Explicitly disabled until isolated P08 MySQL and browser regressions pass.
    'p08_cart_test_enabled' => env('P08_CART_TEST_ENABLED', false),
    'p08_checkout_test_enabled' => env('P08_CHECKOUT_TEST_ENABLED', false),
];
