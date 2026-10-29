<?php

return [
    // P58: explicit production commerce cutover, OFF until staging and DB audit pass.
    // Production requires HTTPS APP_URL, exact canonical host and exact actual MySQL schema.
    // Existing local P26/P32 and campaign order writers stay independently guarded.
    'p58_production_commerce_enabled' => env('P58_PRODUCTION_STOREFRONT_ENABLED', false) === true
        || env('P58_PRODUCTION_STOREFRONT_ENABLED', false) === 'true',
    'p58_production_host' => env('P58_PRODUCTION_STOREFRONT_HOST', ''),
    'p58_production_database' => env('P58_PRODUCTION_STOREFRONT_DATABASE', ''),

    // P14 LOCAL DEV ONLY. Menu and homepage CRUD still verify actual DB identity.
    // Never set this on production; false unless explicit .env opt-in.
    'p14_local_homepage_builder_enabled' => env('P14_LOCAL_HOMEPAGE_BUILDER_ENABLED', false) === true
        || env('P14_LOCAL_HOMEPAGE_BUILDER_ENABLED', false) === 'true',

    // Separate one-operation consent for the 11 forward P02 schema migrations.
    // The underlying P02 test guard remains unchanged for all other services.
    'p14_local_p02_migration_approval' => env('P14_LOCAL_P02_MIGRATION_APPROVED', ''),

    // P25 local ADMIN READ-ONLY CATALOG PREVIEW. Requires P14 verified MySQL
    // identity + active admin login; never enables cart, customer auth or orders.
    // This flag has no public/production effect, even if accidentally enabled.
    'p25_local_browse_enabled' => env('P25_LOCAL_STOREFRONT_BROWSE_ENABLED', false) === true
        || env('P25_LOCAL_STOREFRONT_BROWSE_ENABLED', false) === 'true',

    // P26: isolated local functional QA only. The request middleware also
    // requires loopback port 8088, local environment, exact p01_campaign_test
    // schema AND restricted p01_tester account before enabling P03/P07/P08.
    // Never enable it in production or on ecommerce_landing_db.
    'p26_isolated_commerce_qa_enabled' => env('P26_ISOLATED_COMMERCE_QA_ENABLED', false) === true
        || env('P26_ISOLATED_COMMERCE_QA_ENABLED', false) === 'true',

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
