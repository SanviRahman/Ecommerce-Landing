# P19 — Storefront UI & Safeguarded Integration (Bangla)

**Reviewed source:** `website(20261026-033819).zip`; visual/behavioral reference `shoppilot.zip`, user screenshots of htbazar.com and proyojonerbazar.com, and the user-provided secondary AI transcript (not original meeting audio/video). **No dependency or database state has been copied from the reference.**

## Installation: only changed files

1. Stop the local PHP development server. Back up `D:\Ecommerce-Landing\website`, the local MySQL database, and saved Homepage Builder Revision #1. Keep `.env`, `vendor`, `storage`, public uploaded media and any local code edits intact.
2. Extract `Ecommerce-Landing-P19-Storefront-Patch.zip` into a separate folder. Compare existing project changes and copy/merge patch files using the exact relative paths. Do not blindly overwrite locally modified controllers or routes.
3. Run the source checks from PowerShell:

   ```powershell
   cd D:\Ecommerce-Landing\website
   php artisan optimize:clear
   php tools/qa/check-p17-homepage-reviews.php
   node tools/qa/check-p19-storefront-contract.mjs
   node tools/qa/check-p15-blade-views.mjs
   node tools/qa/p11-offline-gate.mjs
   php artisan migrate:status
   ```

4. The new separate CUSTOMER account schema requires the **only additional migration** `2026_10_26_100001_create_storefront_accounts.php`. **Do not use `migrate:fresh --seed`, `migrate:refresh`, `db:wipe` or broad `db:seed`.** They would erase existing P14 published revision and local orders/products. After backup, MySQL identity verification and independent migration approval, temporarily set `P14_LOCAL_P02_MIGRATION_APPROVED=YES_LOCAL_P02_SCHEMA_ONLY` in the LOCAL `.env`, `php artisan config:clear`, run `php artisan migrate --pretend`, review output, and run **only** `php artisan migrate --path=database/migrations/2026_10_26_100001_create_storefront_accounts.php`. Remove the temporary environment approval immediately and clear configuration again. No authoring-side migration was executed.
5. Login to Admin, go to Homepage Builder, **Create Draft** from published Revision #1; add a Hero section or update the existing one with 3 approved public image Items; select `Category-wise Products` section **per category**, choose its active category/limit, Save/Enable, and Drag & Drop each category shelf under Hero. Then Preview before staging publish. Existing public `/` is intentionally unaffected.
6. To run real browser, cart, checkout, and registration tests, follow existing `docs/12-testing-and-acceptance.md` and approved isolated `p01_campaign_test` / `p01_tester` gate. Do not enable the new `P07_*`, `P08_*`, or `P03_*` flags on your main `ecommerce_landing_db`; the code intentionally blocks them there until a separate reviewed public release.

## Implemented frontend

- Read-only, staged category sections: `Category-wise Products` selected by active parent or subcategory; each shelf may include its immediate active child categories, with sorting and up to 24 products. The existing `Shop by Category` section stays intact.
- Hero image slider: add at least **3 approved public media items** inside a single Hero/Banner section; keyboard/previous/next/dots, pause/resume and reduced-motion support.
- Nested category dropdown and accessible off-canvas mobile menu. Four desktop product cards per row, two mobile. Product-card cart/Order buttons; variant-bearing products force user to select real options before checkout.
- Floating cart drawer and navbar count/subtotal derived from the server-owned guest cart. No fake count. Storefront checkout remains COD-only with four visual states; Payment step accurately shows only COD, and order totals are recalculated on server.
- Separate `storefront` customer auth guard, login, registration, profile, and only owned, newly linked orders. Existing admin/employee `web` login remains separate. Guests may checkout under the existing isolated cart/checkout gates.
- Two-column large borderless PDP images, description below action buttons, mobile sticky **Order Now** linked to the same validated server form, and non-overlapping sticky Home/Menu/Cart/Search/Account nav.
- Configured-only support contact links, ShopPilot-style footer groups; small FAQ/Contact pages based on actual global approved FAQs and verified Site Settings. Reviews show authentic stored stars and accessible horizontal carousel.
- Native scroll reveal, subtle hero/image/product hover and FAQ accordion; disabled with `prefers-reduced-motion` and readable with JavaScript disabled. No new third-party JS dependencies.

## Deliberately not enabled / pending tests

- Existing campaign homepage `/` cutover not performed; shipping/payment providers not added. The `Order Placed` stage is the existing signed success page after a genuine COD order, not a frontend simulation.
- Live P19 route/registration, guest checkout, order ownership, distinct tab idempotency, database migration and mobile browser QA remain **unverified in the authoring container** (no Composer vendor/MySQL). No claim of 100% error-free or production readiness.
- The user-shared FloorExperts Services/About/contact-form animations are only a **pattern reference**. This e-commerce app has no corresponding verified Services/About content/API, so those pages and an unsupported contact submission endpoint are not fabricated.
- Wishlist, bKash/card payment, coupon validation and public search autocomplete are not invented from ShopPilot screenshots; requirements/ownership remain pending approval.

## Source verification

```powershell
node tools/qa/check-p19-storefront-contract.mjs
node tools/qa/check-p07-catalog-contract.mjs
node tools/qa/check-p13-homepage-gate.mjs
node tools/qa/check-p15-blade-views.mjs
node tools/qa/p11-offline-gate.mjs
```

**Reject patch immediately** on route SHA mismatch, P11 fingerprint mismatch, or code changes missing from the source-review manifest. Do not run `node tools/qa/p11-source-manifest.mjs --write` on local code without reviewing every changed file.
