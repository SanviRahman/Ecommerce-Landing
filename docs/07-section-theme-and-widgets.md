# Section Colors, Responsive UI and Floating Widgets

## 1. Exact requested color controls

Every supported section exposes a **Design** tab. This includes product sections, hero, categories, reviews and image/text blocks; styling is not limited to a single global background.

| UI label | Stored styles key | Rule |
|---|---|---|
| Background color | background | #RRGGBB or null=inherit |
| Heading color | heading | same |
| Description/text color | text | same |
| Accent color | accent | same |
| Primary button background | button_background | same |
| Primary button text | button_text | same |
| Primary button hover background | button_hover_background | same |
| Primary button hover text | button_hover_text | same |
| Button border | button_border | same |
| Secondary button background | secondary_button_background | same |
| Secondary button text | secondary_button_text | same |
| Secondary button hover background | secondary_button_hover_background | same |
| Secondary button hover text | secondary_button_hover_text | same |
| Secondary button border | secondary_button_border | same |
| Card background | card_background | same |
| Card text | card_text | same |
| Card border | card_border | same |
| Focus-ring color | focus_ring | same |

Each control has color picker + editable hex value + 'Use global default'. Clear means null, not empty invalid CSS. Canonicalize uppercase #RRGGBB on save. Reject CSS declarations, `url(...)`, `var(...)`, semicolons, HTML and JavaScript. Do not store arbitrary style strings.

## 2. Style resolution

Code-owned safe defaults -> revision.settings.theme -> section.styles non-null overrides. No item-level arbitrary CSS in v1. Product color swatches are separate catalog data, not theme tokens.

Use CSS variables scoped to the rendered section root, for example `data-section-key` plus `--section-bg`, `--section-heading`, `--section-btn-bg`. The server maps whitelisted keys to constant property names after validation. Preview and live use exactly the same resolver and CSS classes.

Do not output global `.btn { ... }` rules from a section. A red button in one category section cannot recolor the hero, campaign order form, admin controls or floating cart. Style inheritance should work without creating a database row per CSS property.

## 3. Example revision settings and section styles

The following are contract examples, not files to paste over the application configuration.

```json
{
  "schema_version": 1,
  "theme": {
    "background": "#FFFFFF",
    "heading": "#111827",
    "text": "#374151",
    "accent": "#5B3DF5",
    "button_background": "#5B3DF5",
    "button_text": "#FFFFFF",
    "button_hover_background": "#4325CA",
    "button_hover_text": "#FFFFFF",
    "button_border": "#5B3DF5",
    "secondary_button_background": "#FFFFFF",
    "secondary_button_text": "#4325CA",
    "secondary_button_hover_background": "#F3F0FF",
    "secondary_button_hover_text": "#4325CA",
    "secondary_button_border": "#4325CA",
    "card_background": "#FFFFFF",
    "card_text": "#111827",
    "card_border": "#D1D5DB",
    "focus_ring": "#111827"
  },
  "widgets": {
    "contact": {
      "enabled": true,
      "side": "right",
      "channels": ["whatsapp", "messenger", "email"],
      "source": "site_setting"
    },
    "floating_cart": {"enabled": true, "side": "right"},
    "mobile_navigation": {
      "enabled": true,
      "items": ["home", "categories", "cart", "search"]
    }
  }
}
```

```json
{
  "background": "#F5F3FF",
  "heading": "#201446",
  "text": null,
  "accent": "#5B3DF5",
  "button_background": "#201446",
  "button_text": "#FFFFFF",
  "button_hover_background": "#352064",
  "button_hover_text": "#FFFFFF"
}
```

These are safe illustrative defaults, not a mandated brand palette copied from HT Bazar. Validate contrast for the actual chosen combination.

## 4. Contrast, usability and motion

Show live contrast feedback while editing. Proposed publish gate: normal text/button-label contrast >=4.5:1; large headings >=3:1; focus/border controls need visible contrast on adjacent surfaces. Enforce the correct text-size classification, not every heading automatically 'large'. For image backgrounds use a controlled overlay or readable solid text panel; a hex-only contrast calculation cannot prove readability over an arbitrary photograph.

Do not rely solely on color for disabled/out-of-stock/error states. Give buttons clear focus styles and accessible names. Respect prefers-reduced-motion. Autoplay has a pause control and is disabled for reduced motion; no forced sound. Editable interval is bounded (proposed 3-15 seconds); do not allow zero-delay looping.

## 5. Floating contact menu

A real button with `aria-expanded`, `aria-controls` and a clear label toggles the menu. `+` changes to a close state. Escape/outside-click closes it, focus returns to the trigger. No extra hand-coded third-party chat SDK is needed merely to open contact links.

Read active SiteSetting.whatsapp_number/messenger_link/email; use current SocialMedia records only as an explicitly verified fallback, not guessed duplicates. Normalize and validate:

- WhatsApp: a valid configured international phone number; construct a safe HTTPS deep link and encode optional text.
- Messenger: allowlisted valid HTTPS destination; do not accept `javascript:` or arbitrary script.
- Email: validated mailbox; construct `mailto:` with safely encoded optional subject, reject CR/LF header injection.

Hide unconfigured channels, preserve configured order. The written requirement includes Email even though the reference screenshot shows a phone icon. Optional phone is not silently substituted.

Contact **layout/order/visibility** is revisioned. Existing global contact records remain live; changing them in Site Settings is a separate, clearly labeled site-wide action, not a draft-only change. This boundary must be clear in the builder.

## 6. Floating cart and mobile navigation

Cart count is total units, not merely distinct lines; subtotal is current server-calculated merchandise total (shipping excluded until checkout). Clicking opens an accessible cart drawer/page. Server responses update every cart badge consistently.

Mobile bottom nav uses real supported destinations: Home, Categories/Menu, Cart, Search. Account/Wishlist are hidden unless separately implemented. The center cart can be emphasized without covering other links. Do not route Account to `/admin/login`.

No arbitrary X/Y dragging on the storefront. Admin can configure side, order and bounded offsets; CSS computes responsive positions. Reserve bottom space for nav + `env(safe-area-inset-bottom)`. Opening the contact menu must not cover checkout buttons, cart drawer close button or navigation. Mobile widgets can collapse when keyboard/checkout UI would be obscured. Test widths 320, 360, 390, 768, 1024 and 1440 CSS pixels plus zoom.

## 7. Responsive section layouts

Product cards: **meeting-reported requirement** is 2 columns mobile and 4 columns on ordinary desktop homepage grids; 3 tablet columns remain a **proposed responsive breakpoint**. For sidebar-constrained Shop views, preserve filter usability and enlarge cards without forcing cramped columns; confirm final breakpoint after browser review. Columns are allowlisted values, not arbitrary CSS. Images have consistent aspect ratio/object-fit, alt text, dimensions to reduce layout shift, and a no-image fallback. Maximum product limits do not silently download the full catalog.

Test section colors at default, hover, focus, disabled and active states; test inherited colors after changing global theme. Reordering cannot detach a section's style settings.

## Meeting-paired sticky elements and default palette

PDF F-007/F-009/F-010: Product detail page exposes a scroll-triggered **Order Now** bar when primary CTA is offscreen; it sits above fixed mobile Home/Menu/Cart and safe area. A separate floating cart opens a slide-out mini-cart. Floating contact + presents U-required WhatsApp/Messenger/Email; optional call only if enabled. Avoid cart drawer and contact menu covering checkout controls. PDF F-011 requests **Desh Bazar-like green** by default, but no approved HEX was supplied; the administrative color picker persists global tokens while each section explicitly overrides or inherits. Keep final brand HEX an open approval item.
