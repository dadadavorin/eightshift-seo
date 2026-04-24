# Eightshift SEO — Feature Recommendations (Post-Phase 2)

A shortlist of candidate features for the next phases. Ordered roughly by **impact vs. build cost** and grouped by theme. Each item includes:

- **What** it does
- **Why** it matters for an Eightshift-flavoured, developer-first SEO plugin
- **Scope hint** (S/M/L) and key architectural notes

> Guiding constraints (from architecture memory):
> - Target is **fresh Eightshift projects**, not a Yoast replacement.
> - Keep scope minimal — no redirect manager, no Yoast migration, no Classic Editor.
> - Never duplicate what `eightshift-utils` (Schema.org) or `eightshift-ui-kit` (breadcrumb rendering) already provide.
> - Storage pattern: separate `es_seo_*` postmeta keys, native `register_setting`, JSON blob for settings, Gutenberg-only UI.

---

## 🎯 Tier 1 — High leverage, low cost (recommended next)

These are small, high-value additions that slot cleanly into the existing architecture.

### 1.1 Webmaster / search-console verification tags
**What:** A "Webmaster tools" section in the General tab with fields for Google Search Console, Bing Webmaster, Yandex, Pinterest, Baidu verification codes. Each emits a `<meta name="…-site-verification">` tag in `wp_head`.
**Why:** Every real site needs GSC verification. Right now users either paste it into the theme's `header.php` (brittle) or install a second plugin. Free win.
**Scope:** **S** — one new settings group + one `wp_head` hook service in `src/Head/`.

### 1.2 Site representation & knowledge-graph defaults
**What:** Settings for "Site represents an Organization / Person", site logo attachment, social profile URLs (LinkedIn, Instagram, YouTube, etc.), and default author/organization for fallback. Emits `Organization` or `Person` JSON-LD with `sameAs` on the homepage (only there, to stay in our lane vs. `eightshift-utils`).
**Why:** Powers Google's knowledge panel, brand SERP cards, and proper `publisher` attribution in other schema types. Currently there's no signal tying the site to an entity.
**Scope:** **M** — new settings tab/section + one schema service + fallback hooks (`es_seo_organization_schema`, `es_seo_person_schema` filters).

### 1.3 Post-list admin columns
**What:** Add sortable columns to `edit.php` / taxonomy admin tables for: SEO title (length indicator), meta description (length indicator), noindex badge, focus keyphrase.
**Why:** Editors need an at-a-glance view of which posts lack SEO metadata. Saves clicking into every post.
**Scope:** **S** — `manage_{post_type}_posts_columns` + `manage_{post_type}_posts_custom_column` hooks. Reuse existing meta keys.

### 1.4 Quick-edit / bulk-edit support
**What:** Add SEO title, description and noindex to WP Quick Edit + Bulk Edit in post lists.
**Why:** Pairs with 1.3 above. Lets editors fix 30 missing descriptions in one pass without opening each post.
**Scope:** **M** — `quick_edit_custom_box` + `bulk_edit_custom_box` + save handler. Only place in the plugin that needs non-Gutenberg UI, but it's a list-table concern, not editor.

### 1.5 Expanded template tokens
**What:** Add tokens: `%primary_category%`, `%category%`, `%tag%`, `%page%` (pagination), `%pagenumber%`, `%pagetotal%`, `%search_phrase%`, `%id%`, `%modified_date%`, `%parent_title%`. All resolve via the existing `TemplateResolver`.
**Why:** Current tokens cover basics but fall short on common cases like category-aware titles or paginated archives. Cheap and purely additive.
**Scope:** **S** — extend `TemplateResolver::buildTokens()` only.

### 1.6 Primary category picker (Gutenberg sidebar)
**What:** When a post has multiple categories, let the editor choose a "primary" one. Exposed via a `es_seo_primary_category` postmeta, used by the new `%primary_category%` token and breadcrumbs.
**Why:** Standard feature Yoast ships — with multiple categories, both breadcrumbs and URL construction (if project uses it) need a deterministic "primary" one. Very small but high quality-of-life.
**Scope:** **S** — one meta field + one `<SelectControl>` in the SEO sidebar panel.

### 1.7 Noindex defaults for low-value archives
**What:** Settings toggles to automatically noindex: search results pages, date archives, author archives (when site has only one author), paginated pages (2+), 404 pages, attachment pages. All default to safer values per Google best practice.
**Why:** Each of these routinely causes thin-content indexing issues. Shipping sensible defaults = fewer surprises for fresh Eightshift sites.
**Scope:** **S** — new service that hooks `wp_robots` with context checks; add settings UI in Advanced tab.

### 1.8 Attachment page redirect
**What:** Option to redirect attachment pages (`?attachment_id=…` or `/attachment/…`) to the attachment file or the parent post.
**Why:** Attachment pages are near-universal SEO waste. Matches Yoast's default behaviour.
**Scope:** **S** — one `template_redirect` hook + one setting.

### 1.9 IndexNow protocol support (Bing/Yandex)
**What:** On publish/update, ping the IndexNow endpoint with the affected URL(s). Setting field for the auto-generated key; plugin hosts `key.txt` at site root via rewrite.
**Why:** Near-real-time indexing signal to Bing and Yandex, with one of the simplest protocols to implement (single HTTP POST). Adds SEO freshness with almost no cost.
**Scope:** **M** — new service + rewrite rule + WP-Cron fallback for batching.

---

## 🧱 Tier 2 — Structural / architectural additions

Larger features that unlock new capability but need more design work.

### 2.1 Schema.org block pack (opt-in Gutenberg blocks)
**What:** Ship a small set of structured-data blocks for editors: **FAQ**, **HowTo**, **Product** (minimal), **Event**, **VideoObject**, **Recipe**. Each renders both visible HTML *and* appends to `@graph` in a single JSON-LD script in `wp_head`.
**Why:** These are the block types that give rich-result eligibility. `eightshift-utils` handles baseline Schema, but these are per-post, editor-authored types — a natural home for a dedicated SEO plugin.
**Scope:** **L** — new `src/Blocks/custom/` entries; graph-merging service; one JSON-LD emitter that aggregates contributions from all sources.
**Note:** Must coordinate with `eightshift-utils` to avoid double-emit. Suggest: provide a filter like `es_seo_schema_graph` that utils can contribute to, and only emit a consolidated `@graph` once.

### 2.2 Hreflang output (integrates with `eightshift-multilang`)
**What:** Emit `<link rel="alternate" hreflang="…">` tags for translated content when a supported multilingual plugin is active. Auto-detect WPML / Polylang / `eightshift-multilang` (visible on the test site). Provide filters for the URL map and a manual override postmeta.
**Why:** The current plugin has zero i18n-SEO handling. Multilingual Eightshift sites are common; hreflang is table-stakes.
**Scope:** **M** — one `wp_head` service + pluggable adapters per multilingual backend.

### 2.3 Pagination & "rel=next/prev" + paginated canonicals
**What:** Correctly handle canonicals for paginated archives (`/page/2/`), paginated singular posts (`<!--nextpage-->`), and search pagination. Optionally emit `rel=prev/next` (Google doesn't use it anymore but Bing does).
**Why:** Current canonical logic has no pagination awareness — you can end up with the same canonical on `/page/2/` as `/page/1/`. Real bug waiting to happen.
**Scope:** **S/M** — enhance the existing `Canonical` service with pagination context.

### 2.4 Image SEO helpers
**What:**
  - Auto-fill empty `alt` attributes from the attachment title on upload (toggle).
  - Warn in the SEO sidebar when the post's featured image has no alt.
  - Optional image sitemap entries appended to the WP native sitemap (via `wp_sitemaps_posts_entry` filter).
**Why:** Image alt is the most-commonly-missed on-page SEO item. Native WP sitemap omits images entirely; this closes the gap without custom sitemap infrastructure.
**Scope:** **M**.

### 2.5 Robots meta: full directive coverage
**What:** Expand meta robots to include `noarchive`, `nosnippet`, `noimageindex`, `notranslate`, `unavailable_after` alongside the existing `noindex`/`nofollow`/`max-*`. Both per-post (Gutenberg sidebar) and per-taxonomy defaults.
**Why:** The current robots coverage is already good but missing a few directives that matter for regulated or ephemeral content.
**Scope:** **S** — new meta fields + UI additions.

### 2.6 OpenGraph & Twitter card improvements
**What:**
  - Twitter card **type selector** (summary / summary_large_image) per post + site default.
  - `og:locale:alternate` emission when multilingual is detected (depends on 2.2).
  - `article:author`, `article:published_time`, `article:modified_time`, `article:section`, `article:tag` tags for `og:type=article`.
  - `og:image:alt` derived from the attachment alt text.
**Why:** Richer social embeds; these tags are trivial to add and appear in several audit tools' "missing OG tags" lists.
**Scope:** **S** — extend existing `OpenGraph` and `TwitterCards` classes.

### 2.7 Settings import / export + WP-CLI commands
**What:** JSON import/export of the full settings blob through the admin UI, plus WP-CLI commands:
  - `wp es-seo settings export [--file=]`
  - `wp es-seo settings import <file>`
  - `wp es-seo meta bulk-set --post-type=post --field=noindex --value=0`
  - `wp es-seo sitemap ping` (pings search engines)
**Why:** Eightshift audience is developer-heavy. CLI is table-stakes and helps with fresh-project bootstrapping (sharing settings between staging/prod). Slots in alongside `eightshift-libs` Cli pattern.
**Scope:** **M**.

### 2.8 SEO health / setup dashboard
**What:** A small dashboard widget + dismissible onboarding card listing:
  - "Homepage has title template? ✅"
  - "Default OG image set? ❌"
  - "Search engine verification configured? ⚠️"
  - "Sitemap reachable? ✅"
  - "X posts missing meta description"
Links to the relevant settings.
**Why:** Communicates plugin value and surfaces config gaps on fresh installs. Low effort, high perceived polish.
**Scope:** **M** — new admin page/widget + a few small checker classes.

---

## 🔬 Tier 3 — Nice-to-have / specialist

Lower priority or scoped to specific site types.

### 3.1 Internal linking suggestions (lightweight)
**What:** In the SEO sidebar, show up to N existing posts that share the current post's focus keyphrase (or category), with one-click insert. Implementation: basic reverse index of post titles + focus keyphrase, rebuilt on save.
**Why:** Yoast Premium's flagship feature. Doing it *well* is hard (semantic analysis), but a basic title/keyphrase-match version covers 80% of value.
**Scope:** **L** — needs an index, cache invalidation, and a UI surface. Could be its own Phase.

### 3.2 Readability panel
**What:** In-editor readability scoring: Flesch reading ease, sentence length distribution, paragraph length, subheading distribution, passive voice detection (English + selected locales).
**Why:** Pairs with the existing pre-publish keyphrase panel for a more complete on-page experience. All runs client-side, no extra requests.
**Scope:** **L** — substantial JS work, language-specific. Not sure if aligned with "lightweight plugin" positioning — consider carefully.

### 3.3 RSS feed enhancements
**What:** Settings for content to prepend/append to RSS items (e.g. "Read the full article on %sitename%"). Combat scrapers and add canonical backlinks.
**Why:** Small but present in Yoast; helps sites that syndicate.
**Scope:** **S** — `the_excerpt_rss` / `the_content_feed` filters.

### 3.4 News sitemap (Google News)
**What:** Separate `news-sitemap.xml` for sites that publish news content, following Google News sitemap protocol.
**Why:** Needed only for news publishers, but genuinely no alternative on WP today besides Yoast News SEO.
**Scope:** **M** — opt-in, tied to a specific post type selection.

### 3.5 Video sitemap + VideoObject auto-extraction
**What:** Scan posts for embedded videos (YouTube, Vimeo, core/video block) and emit both `VideoObject` schema and a video sitemap.
**Why:** Modest effort, helpful for media-heavy sites.
**Scope:** **M**.

### 3.6 Additional template tokens for descriptions
**What:** `%category_description%`, `%term_description%`, `%tagline%`, `%current_year%`. Many sites want evergreen "2026 edition" style titles.
**Why:** Trivial extension of token system.
**Scope:** **S** — extends 1.5.

### 3.7 Audit log for settings changes
**What:** Record who changed what setting and when; expose in a tab.
**Why:** Agency / team environments appreciate this. Not critical.
**Scope:** **M**. Probably deferred.

### 3.8 Local business schema
**What:** Dedicated UI to configure `LocalBusiness` schema (hours, address, geo). Separate from Organization.
**Why:** Specialist use case. Could be a separate companion plugin.
**Scope:** **M**. Consider deferring or splitting.

### 3.9 Per-role capability management UI
**What:** Let admins grant/revoke the `eightshift_seo_manage` cap to specific roles from the settings screen.
**Why:** The cap exists already but there's no UI. Useful for multi-author sites where editors shouldn't touch site-wide settings.
**Scope:** **S**.

### 3.10 Performance / Core Web Vitals hooks
**What:** Not measurement itself, but **hooks for common wins**: preload hints for OG images, DNS prefetch for GTM/Analytics, `fetchpriority="high"` on LCP image candidate (featured image).
**Why:** SEO ≈ CWV increasingly. These are small, correct defaults Eightshift themes can opt into.
**Scope:** **S/M** — couple of `wp_head` emitters + filters.

---

## 🚫 Explicitly out of scope (reminder)

Per architecture notes, these should stay excluded even if asked:

- Redirect manager (use a dedicated plugin)
- Yoast / RankMath migration
- Classic Editor UI support
- Breadcrumb **rendering** (stays in `eightshift-ui-kit`)
- Baseline Schema.org (`Article`, `WebPage`, etc. — stays in `eightshift-utils`)
- Shortcodes

---

## 📌 Suggested roadmap

A pragmatic bundling into phases that each ship in one release:

### Phase 3 — "Everyday essentials" (Tier 1 bundle)
- 1.1 Webmaster verification tags
- 1.2 Site representation & Organization/Person schema
- 1.3 Post-list admin columns
- 1.5 Expanded template tokens
- 1.7 Noindex defaults for low-value archives
- 1.8 Attachment page redirect

*Rationale:* low risk, pure polish, makes the plugin feel "complete" for fresh installs.

### Phase 4 — "Editor experience"
- 1.4 Quick/Bulk edit
- 1.6 Primary category picker
- 2.6 OG/Twitter improvements
- 2.3 Pagination-aware canonicals
- 2.5 Full robots directive coverage

*Rationale:* all tighten the editor-to-output loop and resolve correctness gaps.

### Phase 5 — "Structured data & syndication"
- 2.1 Schema block pack
- 2.4 Image SEO helpers + image sitemap
- 1.9 IndexNow
- 2.7 Import/export + WP-CLI

*Rationale:* biggest visible functionality jump; unlocks rich results.

### Phase 6 — "Specialist / i18n"
- 2.2 Hreflang / multilingual integration
- 2.8 SEO health dashboard
- 3.10 Core Web Vitals hooks

### Phase 7+ (optional / evaluate)
- 3.1 Internal linking suggestions (decide if this belongs here at all vs. a companion plugin)
- 3.2 Readability panel (same question)
- 3.4 / 3.5 News + Video sitemaps (specialist; possibly companion plugins)

---

## ❓ Open questions for you

Answers will shape the scope of Phase 3:

1. **Scope discipline:** do you want to stay strictly lightweight (deliver only Tier 1 + selected Tier 2), or do you want to cover Yoast-parity features like internal linking and readability?
2. **Schema ownership:** should this plugin own *all* `@graph` emission and `eightshift-utils` contribute via filter, or keep them independent? This affects 1.2 and 2.1.
3. **Multilingual adapter priority:** WPML, Polylang, or `eightshift-multilang` first?
4. **CLI positioning:** should `wp es-seo` be first-class (2.7 in Phase 3) or deferred?
5. **Blocks pack:** ship all six schema blocks together, or start with just FAQ + HowTo?
