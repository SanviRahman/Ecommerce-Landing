# Structured Drag-and-Drop Homepage Builder

Related: R04/R05/R08/R09/R10/R13/R15. This is not a freeform HTML/CSS editor.

## Administrator workflow

Menu: Website / Homepage Builder. Use existing AdminLTE shell. Three regions: component palette, sortable page preview, selected-section settings. Toolbar: desktop/mobile preview, draft status, Save Draft, Preview, Publish, revision history/restore. Display server validation errors next to fields and preserve unsaved edits.

Initial authorization: active admin can view/edit/publish. Employees denied unless an explicitly approved policy grants a named capability; do not infer permissions from menu visibility. Empty initial state offers valid sections rather than fake products.

## Allowed component registry

Define in config/storefront.php a code-owned mapping from type to Blade view, settings rules and permitted item types. A request cannot choose arbitrary view paths.

| Type | Data | Admin settings |
|---|---|---|
| hero_slider | ordered slide items with approved media | eyebrow/title/description, CTA labels/URLs, autoplay, interval, image fit, colors |
| category_grid | curated category/subcategory selections | heading, columns, item order, enabled state |
| product_list | manual/category/subcategory source | category, include-descendants, manual/automatic sort, limit, grid/carousel |
| campaign_cards | ordered active campaign references | heading, campaign order, card layout |
| image_text | approved image + plain text items | alignment, CTA, text and section colors |
| reviews | ordered published Review references | title, display count, carousel toggle; no invented review content |
| faq | allowed active global FAQ query or selected references configured safely | title, item limit, accordion behavior |

FAQ is optional content reuse; do not expose campaign-private content on a global page. Typed section settings may store selected FAQ IDs with ownership validation; a future fully relational FAQ pivot can be added only if needed. Mandatory section types for v1: hero_slider, category_grid, product_list, image_text, reviews. Campaign_cards/faq may be disabled until real content is selected.

Header/footer remain stable site scaffolding. Navigation items and floating widgets have dedicated configuration in revision settings, not arbitrary movable HTML nodes.

## Sorting semantics

- Page: reorder section_keys/IDs of one draft, all siblings once, no duplicates/missing/foreign IDs.
- Category tiles: reorder selected category/subcategory IDs only; taxonomy parent_id is unchanged.
- Product selection: manual order belongs to the section, not the product globally. A category source in manual mode is a curated eligible subset. A manual source can select any allowed active catalog products.
- Automatic product modes use stable tie-break `id` and ignore manual order. Re-enabling manual restores valid curated choices; clearly label mode in UI.
- Slides/reviews/cards: reorder their own items. An item from another section/page must not be moved through a forged request.
- Reordering contact channels/nav links changes their array order in settings. Reordering must not bypass required links or channel validation.

Normalize positions to 0..n-1 server-side within the page transaction. UI optimistic rearrangement must roll back/show errors if the server rejects. No competing parallel autosave that overwrites a more recent drag result.

## Versioned editing protocol

All mutations include expected_version (page.lock_version). The service locks the page row, verifies version and draft ownership, applies one mutation, increments the version and returns a fresh snapshot/version. A stale version returns HTTP 409 with current_version; do not overwrite newer edits. The client serializes saves and offers reload/reapply after conflict.

Starting a draft: clone the current published revision, sections, selected-product/category pivots and content items, using new physical DB IDs and preserved logical section/item keys. New page with no publication starts an empty valid draft. If a draft already exists, return it; do not create competing drafts for every browser tab.

## Save, preview and publish

Save Draft never changes storefront_pages.published_revision_id. Preview requires admin authorization and uses the same component renderer as live with a different selected revision. Preview responses are no-store and noindex. Do not issue public unprotected preview URLs. Client sharing of drafts is out of v1 scope.

Publish transaction:

1. Lock page; verify expected_version and that revision belongs to page and is mutable draft.
2. Validate schema, component allowlist, layout/styles, resource references, public visibility, safe URLs and required content.
3. Block broken required images/missing selected resources and invalid contrast; provide actionable field errors. Disabled sections can remain incomplete without rendering.
4. Mark former publication archived; mark the draft published with actor/time; set published_revision_id and clear draft_revision_id.
5. Increment lock_version, record audit event and commit.
6. Invalidate layout cache by published revision identity after commit. Never clear all customer sessions/cart data.

After publication, settings/sections/items of that revision are immutable. Later editing starts a clone. A restore action creates a draft from a retained revision and is separately previewed/published. Do not roll back inventory/prices/orders with a homepage restore.

## Deletion and live catalog changes

Removing a section deletes draft child rows only in safe FK order. Published history is retained. Products/categories/reviews may become inactive after publication: public renderer skips them safely and editor reports stale references. Do not resurrect a soft-deleted product to satisfy a layout. Missing entire published configuration triggers logged safe fallback, never draft rendering.

## Accessibility and performance

SortableJS is an implementation candidate; pin the reviewed version in package-lock.json and bundle via Vite. Drag handles must not capture typing/clicking in fields. Provide Move Up/Down and Move To Position buttons using the same mutation endpoint. Announce reorder outcomes through aria-live, preserve focus, use touch handles and reduced-motion settings.

Proposed caps: 30 sections/page, 20 hero slides, 50 products/section, 24 category tiles, 30 review cards. Validate these on server, not only UI. Lazy-load non-hero images, load bounded queries with media/variants eagerly, and avoid loading the entire catalog into every select. Use paginated authenticated selectors.

## Meeting-specific UI acceptance (M F-001/F-002/F-003/F-011)

Client report requests category-product sections with adjustable top-to-bottom serial, **4 desktop/2 mobile cards**, preservation of existing Shop by Category appearance and a global green-like theme. Direct user adds drag-and-drop and per-section background/button controls. Existing structured builder architecture meets ordering and styling, but requires an explicit responsive/layout contract and tests in docs/22 and docs/25; do not treat the old optional column recommendations as permission to deviate. Exact default green HEX is **not** specified in the meeting report.

## P41 owner amendment (2026-10-28): strictly builder-controlled live homepage

The editor's **enabled, typed sections** are the entire editable homepage body. Do not implicitly append category shelves, category grids, customer reviews, statistics, tracking forms or promotional strips outside the published revision. Render each eligible section once and only once in stored `sort_order`; no special review relocation. Preview uses the same content renderer and eligible public catalog/review/media criteria as live (the private preview's navigation and administrative notice may differ). Header/footer and existing floating cart/contact controls are page chrome, not homepage sections.

**Admin-only live reorder:** The P41 editor opts into `publish_after_sort=1` for mouse/touch drag, keyboard arrow ordering and fallback Up/Down PATCH forms. The reorder is saved, validated and automatically published atomically, archiving the old immutable publication and cloning the new publication as a fresh draft. Other draft save actions still remain private. A reorder without this flag (e.g. existing integration clients) is still draft-only. On invalid incomplete draft sections, keep the new private order but refuse public publication and provide an explicit message; stale version or invalid ordering rejects the entire operation. The editor must clearly warn that successful live reorder publishes **all previously saved draft changes**, not merely section positions. A success reload refreshes the revision number/history.


## P42 mobile hero and global scroll compatibility note (2026-10-28)

The published and private preview homepage share the standalone `resources/views/storefront/pages/home.blade.php` template; it now loads the same accessible `back-to-top.css/js` as the catalog/campaign/admin layouts. The arrow appears only after page scrolling, is fixed left on storefront routes to avoid the cart/contact docks and respects reduced-motion preferences.

For mobile devices <=760px, text-bearing hero slides stack their un-cropped image and natural-height text panel to prevent the Featured Collection eyebrow and heading from clipping. Image-only slides and desktop overlay remain unchanged. `storefront-p36.js` measures the active image plus text on image load, slide change, font readiness and resize, keeping preview and public behavior consistent. No changes to revision storage, order, permissions or publication isolation.

## P43 mobile Hero overlay supersedes the P42 stacked presentation (2026-10-28)

Following the owner's newer screenshots and explicit instruction, text-bearing `hero_slider` slides on narrow devices must use the same **image-behind-copy** composition as desktop, not P42's image-above-copy stack. This is a visual-only change: the image now uses cover cropping within the responsive overlay (intentional tradeoff); the Featured Collection eyebrow, title, description and CTA remain legible in front of a scoped dark gradient. Text reserves space for fixed mobile mini-cart and carousel controls.

`public/frontend/css/storefront-p43.css` overrides P42 **only for** `.p34-storefront .p36-hero-with-copy` at `max-width:760px`. `storefront-p36.js` calculates the active slide height as `max(image intrinsic-ratio height, visible text + gaps + padding)` rather than the sum of image and copy heights. It resyncs after image load, slider activation, window resize and font readiness. No homepage content schema, controller, route, campaign, publishing, product, checkout, customer data or image-only/desktop style change is involved. Both preview and published pages share the same Blade and assets.

See `P43-MOBILE-HERO-OVERLAY-BN.md` and `tools/qa/p43-overlay-browser-smoke.py` for installation and synthetic visual acceptance. Real deployment browser/MySQL acceptance remains pending.

### P44 Image/Text as an image-backed hero

Without changing the saved `image_text` schema, the storefront homepage and its draft preview now render each approved enabled Image/Text item in a full-width image-backed banner. Section subtitle/title/description appear inside the first banner; item-specific title, description and CTA appear in the matching banner. Existing asset selection, item enable flags, CTA URL validation, section order/visibility and preview's non-clickable CTA remain authoritative. The text participates in document flow so exceptionally long content increases banner height; the background image is absolutely positioned with `object-fit:cover`. P44 CSS is deliberately storefront-only; no campaign/admin rules are changed. See `P44-START-HERE.md` and QA checks.

### 2026-10-28 — P46 Product Collection card cap and Image/Text intro contract

- A published or preview `product_list` homepage section renders the first **8 unique, eligible** products in its saved selection order. This is a **display cap** only: no selected product IDs or other catalog records are removed or altered. Desktop is 4 columns × 2 rows when eight eligible products exist; mobile/tablet up to 900px is 2 columns. The CTA below the grid links to `storefront.shop` in public mode, while private preview displays an inert CTA.
- An `image_text` section now renders its section-level subtitle, heading and description **outside/above** media using standard theme-aware heading components. Only each selected item's title, text and approved CTA remain inside the image overlay. Multiple selected media items retain their order and individual overlays. Section visibility, theme inheritance, revision and publication contracts remain unchanged.
- CSS lives in `public/frontend/css/storefront-p46.css`, loaded only from the homepage Blade. No new migrations, routes or database fields.
