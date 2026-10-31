# Client Meeting PDF + Laravel Project — Integrated Codex Pack (v2)

**Delivered:** 2026-10-24. **Deliverable type:** `.md` project implementation specification + original meeting PDF as appendix + prior screenshots and source evidence. **No Laravel application code was changed**. **No production deployment, migrations or browser/integration tests were performed** by creating this package.

## বাংলা executive summary

তোমার campaign-based Laravel project-এর আগের ৩৩টি `.md` document এবং নতুন ৩২-পৃষ্ঠার meeting PDF মেলানো হয়েছে। PDF-র ভেতরের `F-001` থেকে `F-011` পর্যন্ত ১১টি feature আগের requirement-এ map করা হয়েছে। এই PDF কোনো original video/audio নয়—আগের একটি AI-এর transcript/report; PDF-র পরের অংশ `CF-06`-এর মাঝখানে শেষ হয়েছে। তাই নতুন specification ও pending approval আলাদা করে চিহ্নিত করা হয়েছে।

**Meeting থেকে পাওয়া প্রধান design সিদ্ধান্ত (PDF-reported):**

- Existing header ও Shop by Category section-এর present appearance/size বজায় রাখা;
- All Categories-এর nested subcategory (Electronics→Fan/Light, Fashion→T-shirt/Shirt/Pant);
- Admin-controlled category-wise product sections/reordering;
- Product grid **desktop 4**, **mobile 2**; shop/category cards বড় ও existing filters retained;
- Product cards-এ **২৫% Add to Cart, ৭৫% Buy Now / Order Now**;
- Product details **২ ভাগ**: বামে borderless বড় ছবি, ডানে info/variant/price/actions; description ও Free Shipping-type content buttons-এর পরে;
- Color, Size, **Weight (1 kg/2 kg), Piece/Pack** variant; selected price পরিবর্তন;
- Main CTA scroll offscreen হলে mobile sticky **Order Now**, bottom navigation-এর সঙ্গে overlap নয়;
- Desktop checkout-এ sticky summary; mobile form-এর পরপর **অর্ডার করুন** button, নিচে summary;
- Mobile sticky header, Home/Menu/Cart এবং independent floating mini-cart;
- Default green-like theme, admin color picker।

**Direct user additions:** structured drag-and-drop homepage section/item ordering; **প্রতিটি section-এর background, button এবং other palette override**; product card/cart/details-এ reconciled stock availability; floating `+` → WhatsApp, Messenger, Email। PDF-র optional Call request-ও decision gate হিসেবে রাখা হয়েছে। Existing campaign routes/orders/history ও operational integrations preserve করা হবে।

## কোথায় কী পাবে — প্রথমে এগুলো পড়বে

1. [`START-HERE-BN.md`](START-HERE-BN.md) — বাংলা ব্যবহারবিধি।
2. [`docs/19-meeting-source-and-coverage.md`](docs/19-meeting-source-and-coverage.md) — PDF source, page/time, limits and authenticity.
3. [`docs/20-meeting-traceability.md`](docs/20-meeting-traceability.md) — ১১টি meeting F-ID → R-ID → phases → acceptance mapping।
4. [`docs/21-client-srs-merged.md`](docs/21-client-srs-merged.md) — নতুন consolidated SRS।
5. [`docs/22-ui-workflows.md`](docs/22-ui-workflows.md) — actual UI element order and responsive contracts।
6. [`docs/23-decisions-conflicts.md`](docs/23-decisions-conflicts.md) — ১৮টি clarification/approval gate।
7. [`docs/27-developer-tasks.md`](docs/27-developer-tasks.md) — meeting-specific developer task list with exact impacted file families।
8. [`docs/24-codex-meeting-integration-prompts.md`](docs/24-codex-meeting-integration-prompts.md) — Codex-এ phase অনুযায়ী add-on prompts।
9. [`docs/25-meeting-tests.md`](docs/25-meeting-tests.md) — ৪৫টি meeting-specific QA cases (সব NOT RUN)।
10. [`docs/26-meeting-transcript-and-feedback-index.md`](docs/26-meeting-transcript-and-feedback-index.md) — PDF transcript ও feedback location index।
11. [`sources/client-meeting-provided.pdf`](sources/client-meeting-provided.pdf) — uploaded PDF original bytes preserved as evidence.

**Existing baseline authorities unchanged, but updated:** [`project-overview.md`](project-overview.md), [`ERD.md`](ERD.md), [`database-schema.md`](database-schema.md), [`AGENTS.md`](AGENTS.md), [`docs/01-requirements.md`](docs/01-requirements.md), [`docs/14-implementation-sequence.md`](docs/14-implementation-sequence.md), [`docs/15-codex-prompts.md`](docs/15-codex-prompts.md), bugfix register, media and checkout docs, deployment and testing.

## Codex use order

`Open project root → copy these docs (merging any preexisting AGENTS.md) → P00 snapshot/diff & verify actual repo → P01 security/tests → P02 schema → P03 inventory core → P04 product/category admin → P05 builder backend → P06 drag-and-drop/theme admin → P07 frontend/UI → P08 cart/checkout → P09 QA → P10 release/rollback`. Submit **one phase only** with relevant prompt from docs/15 plus supplement from docs/24. Document actual results in docs/17.

## Decisions that must not be guessed

- PDF calls the demonstrated site **proyojonerbazar.com**; examined user ZIP is campaign-based `website(1).zip`. Do not claim same deployed code without verification.
- 1kg and 2kg were mentioned with different prices, including internally inconsistent follow-up wording. Model explicit admin-configured per-variant prices; no arithmetic inferred. A `2-piece pack` is different from cart quantity 2 and physical stock mapping is not approved.
- Exact brand green HEX, requested admin employee permissions, shipping adjustments, full call-channel availability, variant auto-select and number of products/section remain open.
- Original audio/video was **not** received, and the source PDF lacks later SRS/QA/decision phases. Do not invent missing quotes or signatures.

## Completion status

`DOCUMENTATION MERGED` — NOT `FEATURES IMPLEMENTED`. Product availability and checkout safety require real migrations, integrity checks and production-like DB concurrency tests. Syntax parsing in prior baseline does **not** equal a working application. No claim of zero bugs/security vulnerabilities.
