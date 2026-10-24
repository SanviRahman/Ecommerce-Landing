# শুরু করার নির্দেশনা — Laravel Storefront + Codex

## এই প্যাকেজে কী আছে

তোমার দেওয়া `website(1).zip`-এর source এবং reference screenshots অনুযায়ী ধাপে ধাপে implementation specification আছে। এটি **feature implement করা application ZIP নয়**। মূল project-এর code বদলানো হয়নি।

**Meeting PDF পাওয়া গেছে এবং integrate করা হয়েছে।** এটি ৩২ পৃষ্ঠার **পূর্ববর্তী AI-produced meeting transcript/report**, মূল audio/video নয়। PDF-তে F-001..F-011 আছে; CF-06-এর মাঝখানে শেষ। [Merge Summary](MEETING-MERGE-SUMMARY.md) → [Meeting Source/Coverage](docs/19-meeting-source-and-coverage.md) → [Traceability](docs/20-meeting-traceability.md) → [Merged SRS](docs/21-client-srs-merged.md) → [Codex Phase Prompts](docs/24-codex-meeting-integration-prompts.md) পড়ো।

## প্রথমে যেভাবে ব্যবহার করবে

১. Project-এর source, database এবং uploaded media backup রাখো। মূল project থেকে আলাদা development branch ও local/staging database ব্যবহার করো।

২. VS Code-এ তোমার Laravel project-এর সেই folder খোলো, যেখানে `artisan`, `composer.json`, `app` এবং `routes` আছে।

৩. এই ZIP extract করে ভেতরের documentation files ও `docs` folder ওই project root-এ রাখো। মূল application-এর `README.md` বদলাতে হবে না। আগে থেকে `AGENTS.md` থাকলে সেটির নিয়ম মুছে না দিয়ে মিলিয়ে merge করো।

৪. `AGENTS.md` সরাসরি project root-এ থাকবে। Codex-এর জন্য এটিই মূল instruction file। তোমার চাওয়া `agnet.md` নামটিও রাখা আছে; সেটি মূল ফাইলের নির্দেশক alias।

৫. Codex-এ প্রথম task দেওয়ার জন্য [FIRST-CODEX-TASK.md](FIRST-CODEX-TASK.md) খোলো এবং ভেতরের prompt ব্যবহার করো। প্রথম task শুধু P00: বর্তমান source ও safe environment যাচাই।

৬. এরপর [docs/15-codex-prompts.md](docs/15-codex-prompts.md) থেকে P01, P02 … P10 এই ক্রমে **একবারে একটি phase** দাও। সব feature এক task-এ implement করতে বলো না।

৭. প্রতিটি phase শেষে কোন file বদলেছে, কেন বদলেছে, কোন command চালানো হয়েছে এবং কোন test সত্যিই pass করেছে—এসব মিলিয়ে দেখো। [Progress file](docs/17-progress.md)-এ actual result থাকবে।

৮. সব test, stock reconciliation, staging verification এবং release approval ছাড়া production homepage চালু করবে না।

## কোন ফাইল কেন পড়বে

| প্রয়োজন | ফাইল |
|---|---|
| পুরো project-এর ধারণা | [project-overview.md](project-overview.md) |
| Database সম্পর্কের diagram | [ERD.md](ERD.md) |
| Table, column, key এবং data rules | [database-schema.md](database-schema.md) |
| কোন code-এ কী issue পাওয়া গেছে | [Bugfix register](docs/03-bugfix-register.md) |
| Size, color এবং stock-এর সঠিক নিয়ম | [Products and inventory](docs/05-products-and-inventory.md) |
| Drag-and-drop editor, draft ও publish | [Homepage builder](docs/06-homepage-builder.md) |
| প্রতিটি section-এর background এবং button colors | [Theme and widgets](docs/07-section-theme-and-widgets.md) |
| Route ও request/response structure | [Routes and contracts](docs/08-routes-and-contracts.md) |
| কোন file কেন update হবে | [File impact map](docs/11-file-impact-map.md) |
| কীভাবে feature test করবে | [Testing checklist](docs/12-testing-and-acceptance.md) |
| কাজের ক্রম | [Implementation sequence](docs/14-implementation-sequence.md) |
| Codex-এ পাঠানোর prompts | [Codex prompts](docs/15-codex-prompts.md) |

`databasechema.md` তোমার চাওয়া বানানে রাখা হয়েছে; সেটি মূল `database-schema.md`-এ নির্দেশ করে। একই schema-এর দুইটি আলাদা version বানানোর প্রয়োজন নেই।

## তোমার features কীভাবে সাজানো হয়েছে

**Homepage admin:** section add, section drag করে reorder, category/subcategory বা product select, product limit, manual/automatic sorting, preview, draft এবং publish।

**প্রতিটি section-এর Design tab:** background, heading, text, accent, button background/text/hover/border এবং card colors। আলাদা section-এর color অন্য section-এ প্রভাব ফেলবে না। Global default ব্যবহার বা reset করার option থাকবে।

**Product:** simple বা size–color variant। প্রতিটি variant-এর SKU, price এবং stock থাকবে। Card-এ মোট available quantity; details/cart-এ নির্বাচিত variant-এর quantity দেখাবে। Browser-এর quantity/price বিশ্বাস না করে checkout-এ server আবার যাচাই করবে।

**Floating controls:** `+` button খুললে WhatsApp, Messenger এবং Email। Floating cart ও mobile navigation-এর জন্য overlap এড়ানোর নিয়ম থাকবে। Customer account তৈরি করা ছাড়া Account button দেখানো হবে না।

**Existing campaigns:** আগের campaign page ও URL থাকবে। নতুন storefront আলাদা view ব্যবহার করবে। একই stock inventory service থেকে campaign এবং storefront দুটোই পরিচালিত হবে।

## তিনটি গুরুত্বপূর্ণ সতর্কতা

১. **Syntax pass মানে application test pass নয়।** এখানে ২৩৪টি non-Blade PHP file PHP 8.4.24 parser দিয়ে check করে pass পাওয়া গেছে। Laravel boot, database migration, browser test, npm build এবং concurrent checkout test চালানো হয়নি।

২. **পুরোনো stock অন্ধভাবে variant-এ কপি করবে না।** একটি product-এর ১০ পিস stock থাকলে প্রতিটি size/color-এ ১০ পিস বসানো যাবে না। পুরোনো order cancel হলেই stock ফেরত দেওয়াও ঠিক নয়, যদি আগে stock কাটা হয়েছিল কি না জানা না থাকে।

৩. **Live project-এ destructive command নয়।** `migrate:fresh`, `db:wipe`, blanket seed বা existing `APP_KEY` নতুন করে generate করবে না। Uploaded `.env`-এর credentials Git/Codex task/document-এ কপি করবে না।

## Document-এর ভাষা

এই guide বাংলায়; technical contracts ও Codex instructions ইংরেজিতে রাখা হয়েছে, যাতে file path, schema, validation এবং implementation নির্দেশনায় অস্পষ্টতা কম থাকে। Codex-এর explanation ও কাজের রিপোর্ট বাংলায় দিতে `AGENTS.md`-এ নির্দেশনা আছে।

## Meeting integration ও client approval

পাওয়া PDF-র ১১টি feature আগের R-IDs-এর সঙ্গে মেলানো হয়েছে; client visual contract এখন স্পষ্ট: desktop ৪ / mobile ২ cards, card action ২৫% Cart + ৭৫% Buy, description নিচে, sticky Order Now, responsive checkout এবং default green-like theme। আরও business approvals বাকি: exact HEX, ১/২ kg বা multi-piece pack-এর stock unit ও pricing, real ZIP বনাম live site identity, courier/shipping ও inventory return policy। বিস্তারিত [Decisions & Conflicts](docs/23-decisions-conflicts.md)।


**গোপনীয়তা:** `sources/client-meeting-provided.pdf` reference হিসেবে দেওয়া হয়েছে। ক্লায়েন্টের অনুমতি ছাড়া public Git repository-তে এই PDF বা meeting-sensitive notes commit করবে না; প্রয়োজনে PDF project-এর বাইরের private folder-এ রাখবে এবং source file-এর স্থানীয় path follow করবে। `AGENTS.md` ও docs Git-এ দেওয়ার আগে secret/customer তথ্য পরীক্ষা করো।
