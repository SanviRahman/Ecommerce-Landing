<?php

return [
    // Deliberately disabled. Turning this on alone still cannot bypass the
    // allocator's independent P02 isolated-test-DB identity guard.
    // Production release needs reconciled stock and a separately reviewed cutover.
    'p03_core_test_enabled' => false,

    // OFF by default. Test-only bridge across campaign, manual and bulk order
    // creation. Still denied on all databases except p01_campaign_test.
    'p03_order_writer_test_enabled' => false,
];
