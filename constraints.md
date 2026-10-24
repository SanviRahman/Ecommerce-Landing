# Hard Constraints

| Constraint | Required handling |
|---|---|
| Existing Laravel project, not a new build | Modify verified current paths; preserve dependencies/structure |
| Meeting PDF supplied but not original recording | Treat 11 feature items as **PDF-reported**, PDF cuts off after CF-06; do not invent absent parts, verify live code/site identity |
| Existing campaigns share homepage Blade | Add separate storefront; keep campaign routes and render |
| Production/customer data | No destructive resets, broad seeds or undocumented transformations |
| Inventory inconsistency | Reconcile; use one variant-backed authority and ledger |
| Legacy order history | No guessed size/color or retroactive double deductions |
| Cart versus campaign | Separate contexts; no Cart::destroy or hidden clearing |
| Page editing | Typed sections, safe settings, no arbitrary code/CSS |
| Colors | Per-section override + global inheritance, no global CSS bleed |
| Dragging | Accessible non-drag controls and server persistence required |
| Publication | Draft isolation, immutable published data, version conflict handling |
| Media | Keep custom storage paths, immutable referenced assets, no unsafe deletion |
| Auth/CSRF | Never disable to fix an error; validate resource ownership |
| Secrets | Do not print/commit .env or copy credentials into artifacts |
| Dependencies | Locked installs; reviewed additions, no unjustified upgrades |
| Unsupported reference UI | No fake customer account/wishlist/payment buttons |
| Testing | Actual results only; syntax checks are not integration tests |
| Production rollout | Explicit approval and schema-compatible rollback |

These constraints are binding implementation boundaries. Any change requires a recorded decision and corresponding schema/contracts/tests update. They are not an installation of a `/constraint` command.
