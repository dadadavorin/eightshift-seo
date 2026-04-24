# Eightshift SEO — Implementation Plan (Phase 3 → Phase 5)

This plan covers **Tier 1** (all 9 items) and **Tier 2** (6 of 7 items — the Schema.org block pack is deferred), bundled into three shippable phases.

> **Architectural reminders** (from memory — re-verify before implementing):
> - All classes implement `ServiceInterface` with a `register()` method, auto-discovered via the DI container.
> - Settings are stored as a single JSON blob in `wp_options` (`es-seo-settings`); read via `Options::getOption([...])`.
> - All postmeta lives in individual `ServiceInterface` classes under `src/CustomMeta/` (or `src/TermMeta/`), each calling `register_meta` on `init` with a REST-enabled JSON schema. Meta key prefix: `es_seo_*`.
> - Gutenberg-only editor UI — extend the existing `SeoPanelPlugin.js` / sidebar panels.
> - Manifest (`src/Blocks/manifest.json`) is the source of truth for caps, meta keys, filters, and settings defaults.
> - Target: **PHP 8.4+**, **WordPress 6.8+**.

---

# Phase 3 — "Everyday essentials"

**Goal:** Ship the low-risk, high-polish additions that make a fresh Eightshift install feel SEO-complete out of the box.

**Features:** 1.1 Webmaster verification · 1.2 Site representation / Org+Person schema · 1.3 Post-list admin columns · 1.5 Expanded template tokens · 1.7 Noindex defaults · 1.8 Attachment page redirect

---

## 3.1 Webmaster / search-console verification tags

**Outcome:** A "Webmaster tools" section in the General settings tab with fields for Google, Bing, Yandex, Pinterest, Baidu; each emits a `<meta name="…-site-verification">` tag in `wp_head`.

### Tasks

1. **Manifest & defaults**
   - Add to `src/Blocks/manifest.json` under `optionsDefaultValue`:
     ```json
     "webmaster": {
       "google": "",
       "bing": "",
       "yandex": "",
       "pinterest": "",
       "baidu": ""
     }
     ```
   - Add a new `webmaster` item group under `groups.manage.general.items`.

2. **Settings UI** — extend `GeneralTab.js`:
   - Add a "Webmaster verification" collapsible section with one `TextControl` per engine.
   - Include `help` text with link to each vendor's verification page (docs link, *not* deep-linked with IDs).
   - Add light validation — warn if the user pastes the entire `<meta>` tag instead of just the code.

3. **Head output service**
   - Create `src/Head/WebmasterVerification.php` (`ServiceInterface`).
   - Hook `wp_head` at priority 1 (before most other output).
   - Loop the 5 keys; skip empties; emit `<meta name="…" content="…">`.
   - Engine → meta name map:
     - `google` → `google-site-verification`
     - `bing` → `msvalidate.01`
     - `yandex` → `yandex-verification`
     - `pinterest` → `p:domain_verify`
     - `baidu` → `baidu-site-verification`
   - Escape with `esc_attr()`.

4. **Testing**
   - Paste a dummy code per engine, view-source the homepage, confirm 5 tags present.
   - Empty all fields, confirm zero tags emitted.

---

## 3.2 Site representation & Organization/Person schema

**Outcome:** Settings for "Site represents" (Organization | Person), logo attachment, social profile URLs. Plugin emits `Organization` or `Person` JSON-LD on the **homepage only** with `sameAs` pointing at the configured profiles.

### Tasks

1. **Manifest & defaults**
   - Add to `optionsDefaultValue`:
     ```json
     "siteRepresentation": {
       "type": "organization",
       "name": "",
       "logo": 0,
       "personId": 0,
       "social": {
         "facebook": "",
         "instagram": "",
         "linkedin": "",
         "youtube": "",
         "twitter": "",
         "github": "",
         "wikipedia": "",
         "other": []
       }
     }
     ```
   - Add a new settings group `siteRepresentation` (new tab "Site" or nested under "General" — recommend new tab for clarity).

2. **Settings UI**
   - New tab component `SiteRepresentationTab.js` under `src/Blocks/components/admin-settings/assets/tabs/`.
   - Controls:
     - `RadioControl` — Organization / Person.
     - If Organization: `TextControl` for name (default: `get_bloginfo('name')`), `MediaUpload` for logo.
     - If Person: `SelectControl` bound to users, resolved to `WP_User`.
     - Social URLs: one `TextControl` per known platform, "+" button to add custom `sameAs` entries.
   - Register new tab in `AdminApp.js`.

3. **Schema service**
   - Create `src/Schema/SiteRepresentationSchema.php` (`ServiceInterface`).
   - Hook `wp_head` at priority 12 (after `BreadcrumbListSchema`).
   - Guard: only emit on `is_front_page()` or `is_home()`.
   - Build graph entry:
     - `Organization`: `name`, `url` (`home_url('/')`), `logo` (ImageObject with dimensions), `sameAs` array.
     - `Person`: `name` (WP_User display name), `url`, `image` (avatar), `sameAs`.
   - Filter: `es_seo_site_representation_schema`.

4. **Meta key exposure for fallback chain**
   - Add `siteRepresentation` filter name to manifest filters array.
   - Document in inline PHPDoc how downstream projects can extend `sameAs`.

5. **Coordination with `eightshift-utils`**
   - Verify (read util's Schema emission) that there's no existing Organization/Person emission on the homepage — if there is, decide: (a) suppress via filter, (b) defer to utils, or (c) merge into `@graph`.
   - **Acceptance criterion:** no duplicate `Organization` or `Person` JSON-LD when both plugins are active.

6. **Testing**
   - Test with Google's Rich Results Test on the homepage.
   - Switch representation Org→Person→Org and confirm only one entity type emitted per state.

---

## 3.3 Post-list admin columns

**Outcome:** Every public post type's list table (`edit.php`) gets new columns: SEO title (with length indicator), meta description (with length indicator), noindex badge, focus keyphrase.

### Tasks

1. **Columns service**
   - Create `src/AdminMenus/PostListColumns.php` (`ServiceInterface`).
   - Hook `registered_post_type` late (or `admin_init`) to register columns on all public post types, respecting `es_seo_supported_post_types` filter.
   - Register per post type:
     - `manage_{type}_posts_columns` — merge in `es_seo_title`, `es_seo_description`, `es_seo_noindex`, `es_seo_keyphrase`.
     - `manage_{type}_posts_custom_column` — render each cell.

2. **Cell rendering**
   - **SEO title** — show value; if empty, show muted "—"; show length + visual length class (`ok`, `short`, `long`) based on 30–60 char recommended range.
   - **Meta description** — same treatment, 120–160 char range.
   - **Noindex** — show "Noindex" pill when `true`, empty otherwise.
   - **Keyphrase** — show value or "—".

3. **Styles**
   - Enqueue a small admin CSS file only on `edit.php` screens via `admin_enqueue_scripts`.
   - Use CSS variables for colors: `--es-seo-ok`, `--es-seo-warn`, `--es-seo-bad`.
   - Pill style matches existing admin UI.

4. **Sortable columns** (nice-to-have)
   - Make `es_seo_noindex` sortable by hooking `pre_get_posts` with `meta_query`.

5. **Testing**
   - Create a post with all SEO fields → open list table → confirm values render.
   - Sort by noindex, confirm ordering.

---

## 3.4 Expanded template tokens

**Outcome:** `TemplateResolver` understands a broader vocabulary of tokens used in both `titleTemplates` and `descriptionTemplates`.

### Tasks

1. **Extend `TemplateResolver::buildTokens()`** in `src/Templates/TemplateResolver.php`:
   - `%id%` — post/term ID.
   - `%primary_category%` — use new primary category meta (ties to feature 4.2); falls back to first assigned category.
   - `%category%` — comma-separated category names.
   - `%tag%` — comma-separated tags.
   - `%modified_date%` — `get_the_modified_date()`, format configurable via `es_seo_template_tokens_date_format` filter.
   - `%parent_title%` — parent post title for hierarchical post types.
   - `%page%` / `%pagenumber%` — current page number on paginated content (via `get_query_var('paged')` / `paged_navigation`).
   - `%pagetotal%` — total pages.
   - `%search_phrase%` — `get_search_query()` for search pages.
   - `%current_year%` — `date_i18n('Y')`.
   - `%tagline%` — `get_bloginfo('description')`.

2. **Context-awareness**
   - For search pages, resolve in `document_title_parts` so `%search_phrase%` works.
   - For paginated archives, call resolver with pagination context.

3. **Token documentation in Defaults tab**
   - Update the existing token list in `DefaultsTab.js` to include all new tokens with tooltip descriptions.
   - Consider a "Copy" button next to each token.

4. **Testing**
   - Test each token in isolation (unit test per `resolve()` call).
   - Write fixture posts with parents/categories/tags and validate output.

---

## 3.5 Noindex defaults for low-value archives

**Outcome:** Settings toggles to auto-noindex: search pages, date archives, paginated archives (page 2+), 404, attachment pages, author archives (conditional).

### Tasks

1. **Manifest additions** — add to `optionsDefaultValue.robotsDefaults`:
   ```json
   "archives": {
     "search": true,
     "date": true,
     "paged": true,
     "404": true,
     "attachment": true,
     "author": "auto"
   }
   ```
   `author: "auto"` means noindex when there's only one active author.

2. **Settings UI** — extend `AdvancedTab.js`:
   - New section "Archive defaults" with a `CheckboxControl` per toggle above.
   - Author toggle becomes a `SelectControl`: "Always index" / "Never index" / "Noindex if only one author".

3. **Runtime integration** — extend `src/Head/RobotsDirectives.php`:
   - Before reading post/term meta, short-circuit based on context:
     - `is_search()` → apply search setting
     - `is_date()` → apply date setting
     - `is_paged()` → apply paged setting
     - `is_404()` → apply 404 setting
     - `is_attachment()` → apply attachment setting
     - `is_author()` → check setting + author count via cached `count_users()['avail_roles']`
   - Use `wp_robots` filter (already preferred over meta emission for `noindex`).

4. **Testing**
   - Create a fresh install with defaults → navigate to `/?s=foo` → view source, confirm `noindex`.
   - Toggle off, confirm tag gone.
   - Multi-author scenario: promote second user, reload author archive, confirm indexable.

---

## 3.6 Attachment page redirect

**Outcome:** Option to 301-redirect attachment pages to the attachment file (or the parent post) instead of letting WP render the thin attachment template.

### Tasks

1. **Manifest** — add to `optionsDefaultValue`:
   ```json
   "attachmentRedirect": "file"
   ```
   Values: `"file"` (default), `"parent"`, `"disabled"`.

2. **Settings UI** — in `AdvancedTab.js`, add a `SelectControl`:
   - "Redirect to file URL" (recommended).
   - "Redirect to parent post / disabled if no parent".
   - "Do not redirect".

3. **Redirect service**
   - Create `src/Head/AttachmentRedirect.php` (`ServiceInterface`).
   - Hook `template_redirect` at priority 1.
   - If `is_attachment()`:
     - `file` → `wp_redirect(wp_get_attachment_url($id), 301)` then `exit`.
     - `parent` → if parent exists, redirect to parent permalink, else fall through.
     - `disabled` → noop.

4. **Rewrite considerations**
   - WP adds `/?attachment_id=…` and `/attachment/slug/` URL patterns. Both hit `is_attachment()` — no extra rewrite work needed.

5. **Testing**
   - Upload image → get attachment URL → visit → confirm 301 chain (Chrome DevTools → Network).
   - Toggle modes and re-test.

---

## Phase 3 acceptance checklist

- [ ] All manifest changes reflected and auto-discovered by DI container.
- [ ] No duplicate JSON-LD on homepage when `eightshift-utils` is active.
- [ ] All new settings round-trip through REST (`GET /wp/v2/settings` returns them; `POST` persists).
- [ ] All new `<meta>` tags validated with Google Rich Results Test / URL inspector.
- [ ] Changelog entry added for each feature.

---

# Phase 4 — "Editor experience"

**Goal:** Tighten the editor-to-output loop and resolve correctness gaps in robots / canonical / social emission.

**Features:** 1.4 Quick/Bulk edit · 1.6 Primary category picker · 2.3 Pagination canonicals · 2.5 Full robots directive coverage · 2.6 OG/Twitter improvements

---

## 4.1 Quick-edit / Bulk-edit support

**Outcome:** Editors can set SEO title, meta description, noindex, and focus keyphrase directly from `edit.php` Quick Edit and Bulk Edit.

### Tasks

1. **Quick Edit UI**
   - Create `src/AdminMenus/QuickEdit.php` (`ServiceInterface`).
   - Hook `quick_edit_custom_box` to append the 4 fields (matching the columns from 3.3).
   - Inline JS to hydrate the Quick Edit row from data-* attributes written by the column renderer.
   - Save handler on `save_post` reads `$_POST['es_seo_*']` with nonce + capability checks.

2. **Bulk Edit UI**
   - Hook `bulk_edit_custom_box`.
   - Fields: noindex dropdown (leave / set-true / set-false), "append to description" text area (optional).
   - Handler on `save_post` similar to Quick Edit.

3. **JS enqueue**
   - One small JS file enqueued only on `edit.php`.
   - No React — plain DOM + jQuery (Quick Edit is still jQuery in core).

4. **Security**
   - Nonce per row + `current_user_can('edit_post', $postId)` check.
   - `es_seo_supported_post_types` filter respected.

5. **Testing**
   - Quick-edit a post: change noindex → save → reload list → column reflects change.
   - Bulk-edit 5 posts with noindex=on → confirm all updated.

---

## 4.2 Primary category picker

**Outcome:** Gutenberg sidebar control to pick a "primary" term among the assigned categories (extensible to other hierarchical taxonomies). Meta key `es_seo_primary_category_{taxonomy}` persists the selection.

### Tasks

1. **Meta registration**
   - Create `src/CustomMeta/SeoPrimaryTermMeta.php` (`ServiceInterface`).
   - Iterate public hierarchical taxonomies; register one meta key per, e.g. `es_seo_primary_category` for `category`, `es_seo_primary_product_cat`, etc.
   - REST: `integer`, single, auth = `edit_post`.

2. **Manifest extension** — add to `meta`:
   ```json
   "primaryCategory": "es_seo_primary_category"
   ```
   (Store only the default `category` key here; dynamic ones resolve via `Options::getMetaKey('primaryTerm')` + taxonomy suffix.)

3. **Sidebar integration**
   - New panel section in `SocialSharingPanel.js`… wait, better location: add to `AdvancedPanel.js` or directly below the categories selector. Recommend **extending** the `PluginDocumentSettingPanel` in `SeoPanelPlugin.js` with a new "Primary category" dropdown that only renders when the post has ≥2 categories selected.
   - Use `useSelect` to subscribe to `core/editor` for assigned terms.
   - Use `useEntityProp` to read/write the primary meta.

4. **Token consumption**
   - `%primary_category%` token (from 3.4) reads the meta with fallback to first assigned.

5. **Breadcrumb integration**
   - `BreadcrumbListSchema::buildTrail()` — for posts with a primary category, prepend the category crumb before the post title.

6. **Testing**
   - Post with 3 categories → pick one as primary → verify token + breadcrumb reflect it.

---

## 4.3 Pagination-aware canonicals & robots

**Outcome:** Canonical tag and robots directives behave correctly on:
- Paginated archives (`/page/N/`).
- Paginated singular posts (`<!--nextpage-->`).
- Search pagination.
- Feed URLs.

### Tasks

1. **Audit current behavior** — read `src/Head/Canonical.php` and identify gaps. Likely:
   - Canonical always uses `get_permalink()` → wrong on `/page/2/`.
   - No awareness of `get_query_var('paged')` or `get_query_var('page')`.

2. **Fix canonical**
   - For archives: build canonical as `get_pagenum_link( get_query_var('paged') ?: 1 )`.
   - For singular multipage: append `/N/` when `get_query_var('page') > 1`, using `trailingslashit`.
   - For feeds: emit no canonical (feeds have `self` link already).

3. **Optional `rel=prev/next`**
   - Add setting toggle `pagination.emitPrevNext` (default off — Google deprecated, but Bing/others still use).
   - When enabled, emit `<link rel="prev">` / `<link rel="next">` on paginated archives.

4. **Pagination noindex** (paired with 3.5)
   - When `paged > 1`, emit noindex if the archives setting enables it.

5. **Testing**
   - On a blog with 30 posts/10 per page: visit `/page/2/` → view source → canonical should point at page 2, not page 1.
   - A multipage post: confirm canonicals on `/post/2/`, `/post/3/`.

---

## 4.4 Full robots directive coverage

**Outcome:** `noarchive`, `nosnippet`, `noimageindex`, `notranslate`, `unavailable_after` available per-post and as taxonomy defaults.

### Tasks

1. **New postmeta services** — one file each under `src/CustomMeta/`:
   - `SeoRobotsNoarchiveMeta.php` — `es_seo_noarchive` (boolean).
   - `SeoRobotsNosnippetMeta.php` — `es_seo_nosnippet` (boolean).
   - `SeoRobotsNoimageindexMeta.php` — `es_seo_noimageindex` (boolean).
   - `SeoRobotsNotranslateMeta.php` — `es_seo_notranslate` (boolean).
   - `SeoRobotsUnavailableAfterMeta.php` — `es_seo_unavailable_after` (ISO 8601 string).

2. **Term meta mirrors** — same five fields under `src/TermMeta/`.

3. **Manifest additions** to `meta` + `termMeta` maps.

4. **Runtime emission** — extend `src/Head/RobotsDirectives.php`:
   - Read each meta and append to the `wp_robots` directive array.
   - `unavailable_after` → format as `unavailable_after: YYYY-MM-DDTHH:MM:SSZ`.

5. **Sidebar UI**
   - Extend `AdvancedPanel.js` with collapsible "Advanced robots" section containing:
     - 4 checkboxes
     - 1 date+time picker for `unavailable_after`
   - Use WP's `DateTimePicker` component.

6. **Settings UI (taxonomy defaults)**
   - Extend `AdvancedTab.js` per-taxonomy robots block with the same 5 controls.

7. **Testing**
   - Set `noarchive` on a post → view source → confirm `<meta name="robots" content="…,noarchive">`.
   - Set `unavailable_after` to a past date → confirm Google's URL inspection tool flags it.

---

## 4.5 OG & Twitter Card improvements

**Outcome:** Richer social metadata with per-post card type, article-specific OG tags, and image-alt support.

### Tasks

1. **Twitter card type selector**
   - New postmeta: `es_seo_twitter_card_type` — enum `summary | summary_large_image`.
   - Site-wide default setting in General tab (`twitterCardDefault`).
   - Sidebar `SelectControl` in `SocialSharingPanel.js` with "Site default" / `summary` / `summary_large_image`.

2. **OG article tags** — extend `src/Head/OpenGraph.php`:
   - When `og:type = article`:
     - `article:author` — author display name (or URL if author has website).
     - `article:published_time` — `get_post_time('c')`.
     - `article:modified_time` — `get_post_modified_time('c')`.
     - `article:section` — primary category name (via 4.2) or first category.
     - `article:tag` — one tag per emission for each assigned tag.

3. **og:image:alt**
   - Derive from attachment's `_wp_attachment_image_alt` meta.
   - Add `og:image:alt` and `twitter:image:alt` when an alt exists.

4. **og:locale:alternate**
   - Stub for Phase 5 hreflang — compute alternates from multilingual adapter when available; no-op otherwise.

5. **Testing**
   - Post with tags A, B, C → view source → 3 `article:tag` lines.
   - Facebook debugger (https://developers.facebook.com/tools/debug/) — paste URL, confirm OG tags parse cleanly.
   - Twitter card validator.

---

## Phase 4 acceptance checklist

- [ ] Quick/Bulk edit preserves capability checks (no unprivileged meta updates).
- [ ] Canonical on `/page/2/` points to page 2.
- [ ] All new robots directives appear only when meta is set.
- [ ] Primary category flows end-to-end: picker → meta → token → breadcrumb.
- [ ] No PHP notices / deprecation warnings with `WP_DEBUG=true`.

---

# Phase 5 — "Integrations & tooling"

**Goal:** Larger integration work that touches external protocols, multilingual systems, the media pipeline, and developer tooling.

**Features:** 1.9 IndexNow · 2.2 Hreflang / multilingual · 2.4 Image SEO + image sitemap · 2.7 Import/export + WP-CLI · 2.8 SEO health dashboard

---

## 5.1 IndexNow protocol

**Outcome:** On post publish/update, ping the IndexNow API (Bing + Yandex) with changed URLs. Auto-manage the verification key file at `https://site.tld/{key}.txt`.

### Tasks

1. **Key management**
   - On plugin activation (or first admin visit), generate a 32-char hex key and store in `es-seo-settings.indexNow.key`.
   - Also store in `es-seo-settings.indexNow.enabled` (boolean).

2. **Key file serving**
   - Create `src/Head/IndexNowKey.php` (`ServiceInterface`).
   - Hook `init` to add a rewrite rule: `^([a-f0-9]{8,64})\.txt$` → internal query var `es_seo_indexnow_key`.
   - Hook `template_redirect`: when query var matches the stored key, emit `text/plain` body with the key and `exit`.
   - Flush rewrites on activation (via existing `Activate.php`).

3. **URL submission**
   - Create `src/Head/IndexNowSubmit.php` (`ServiceInterface`).
   - Hook `transition_post_status`:
     - Only act when `$new_status === 'publish'` OR transitioning into publish.
     - Respect noindex — skip submission if post is noindexed.
     - Build payload:
       ```json
       {
         "host": "site.tld",
         "key": "…",
         "keyLocation": "https://site.tld/{key}.txt",
         "urlList": ["https://site.tld/post-slug/"]
       }
       ```
     - POST to `https://api.indexnow.org/IndexNow` using `wp_remote_post` with 5s timeout.
   - Batching: queue submissions with Action Scheduler if available, else WP-Cron every 5 min.
   - Retry: 3 attempts with exponential backoff; log failures via `error_log`.

4. **Settings UI** — in the Advanced tab:
   - Enable/disable toggle.
   - Show the current key (read-only, with "Regenerate" button — warns that existing search engines will need to re-verify).
   - Status indicator: last ping timestamp + success/failure count.

5. **Testing**
   - Publish a test post with a network inspector; confirm POST to `api.indexnow.org`.
   - Visit `/key.txt` → confirm key returned as plain text.
   - Regenerate key → old file stops responding, new one starts.

---

## 5.2 Hreflang / multilingual

**Outcome:** On any singular page or archive, emit `<link rel="alternate" hreflang="…">` tags for translations, when a supported multilingual system is active. Ships with adapters for `eightshift-multilang`, Polylang, and WPML; graceful no-op otherwise.

### Tasks

1. **Adapter interface**
   - Create `src/Multilingual/MultilingualAdapterInterface.php`:
     ```php
     interface MultilingualAdapterInterface
     {
         public static function isActive(): bool;
         public function getAlternates(int|null $postId = null): array; // [ ['locale' => 'en-US', 'url' => '…'], … ]
         public function getDefaultLocale(): string;
     }
     ```

2. **Adapter implementations** under `src/Multilingual/`:
   - `EightshiftMultilangAdapter.php` — detect via class or function exposed by `eightshift-multilang`; build alternates from its API.
   - `PolylangAdapter.php` — use `pll_the_languages(['raw' => 1])` + `pll_get_post()`.
   - `WpmlAdapter.php` — use `wpml_hreflangs` filter output or `icl_get_languages()`.
   - `NullAdapter.php` — returns empty array; used when none detected.

3. **Resolver**
   - `src/Multilingual/AdapterResolver.php` with `static resolve()` returning the first active adapter.
   - Cache per request.

4. **Head emission**
   - Create `src/Head/Hreflang.php` (`ServiceInterface`).
   - Hook `wp_head` at priority 6.
   - Call `AdapterResolver::resolve()->getAlternates()` and emit `<link rel="alternate" hreflang="…" href="…">` for each.
   - Also emit `<link rel="alternate" hreflang="x-default" …>` using the default locale's URL.

5. **OG locale:alternate**
   - Finish the stub from 4.5: consume the same adapter to emit `og:locale:alternate` per translation.

6. **Manual override**
   - New postmeta `es_seo_hreflang_disabled` (boolean) — if set, suppress hreflang emission for that post (escape hatch).

7. **Filter**
   - `es_seo_hreflang_alternates` — final filter before emission.

8. **Testing**
   - Site with `eightshift-multilang` + 2 locales → post translated → confirm 2 alternates + x-default.
   - Disable the multilingual plugin → confirm no emission (NullAdapter).

---

## 5.3 Image SEO helpers + image sitemap

**Outcome:** Auto-fill empty alt text on upload, warn editors about missing alt on the featured image, and append images to the WP native sitemap.

### Tasks

1. **Alt auto-fill**
   - Create `src/Media/AltAutoFill.php` (`ServiceInterface`).
   - Hook `add_attachment`:
     - If attachment is image AND `_wp_attachment_image_alt` is empty:
     - Derive alt from the attachment title (strip extension, replace `-`/`_` with spaces, title-case).
     - Only when `settings.images.autoFillAlt` is true (default: true).

2. **Featured image alt check**
   - In the SEO sidebar's pre-publish check (`PrePublishPanel.js`), add a check:
     - "Featured image has alt text" — resolves via `core/editor` store featuredMedia ID + `core` store media entity.
   - Include in the keyphrase/meta checklist.

3. **Image sitemap contribution**
   - Hook `wp_sitemaps_posts_entry` (WP 6.0+):
     - For each post, collect images: featured image + images in post_content (parse `<img src>`).
     - Append XML fragment via a `wp_sitemaps_posts_entry` filter hook.
   - Note: WP's native sitemap XML is XHTML-escaped. Need to inject raw `<image:image>` nodes via the `sitemaps_stylesheet` / output filters. Verify feasibility; may require custom sitemap provider as fallback.
   - **Fallback plan:** if WP core makes this impractical, ship a secondary `/es-seo-images-sitemap.xml` generated from post content; reference it from the sitemap index.

4. **Settings** — new "Images" section in Advanced tab:
   - Toggle: auto-fill alt (default on).
   - Toggle: include images in sitemap (default on).
   - Toggle: generate image sitemap separately (default off — only if core approach fails).

5. **Testing**
   - Upload image → confirm alt auto-populated.
   - View `/wp-sitemap-posts-post-1.xml` → confirm image entries present.
   - Validate sitemap with an online XML sitemap validator.

---

## 5.4 Settings import / export + WP-CLI

**Outcome:** JSON export/import in the admin UI; WP-CLI commands for settings and bulk meta operations.

### Tasks

1. **Admin import/export**
   - New "Tools" tab in `AdminApp.js` or a section in "Advanced".
   - **Export** button: `GET /wp/v2/settings` → filter to `es-seo-settings` key → download as `es-seo-settings-{site}-{YYYY-MM-DD}.json`.
   - **Import**: file picker → validate JSON shape (ajv-lite or hand-written) → confirm dialog listing settings to overwrite → POST to `/wp/v2/settings`.
   - Validation must reject unknown keys to prevent injection of foreign option data.

2. **WP-CLI command base**
   - Create `src/Cli/SeoCommand.php` extending `EightshiftLibs\Cli\AbstractCli`.
   - Register under `wp es-seo`.

3. **Subcommands**
   - `wp es-seo settings export [--file=<path>]` — writes JSON or stdout.
   - `wp es-seo settings import <file>` — reads, validates, stores.
   - `wp es-seo meta set --post_type=<type> --field=<noindex|title|…> --value=<v> [--overwrite] [--dry-run]` — bulk set meta across posts.
   - `wp es-seo meta clear --post_type=<type> --field=<…> [--dry-run]` — remove meta.
   - `wp es-seo sitemap ping` — ping IndexNow for all published URLs (rate-limited).
   - `wp es-seo indexnow status` — show last ping log.

4. **Progress + logging**
   - Use `\WP_CLI\Utils\make_progress_bar()` for long operations.
   - Colored summary (`WP_CLI::success`, `WP_CLI::warning`).

5. **Testing**
   - Round-trip export → delete settings → import → confirm identical output.
   - Bulk set noindex=1 across 50 posts with `--dry-run` then real run.

---

## 5.5 SEO health dashboard

**Outcome:** A small dashboard widget + dedicated "Health" tab listing configuration gaps and content issues.

### Tasks

1. **Checker architecture**
   - Interface `src/Health/HealthCheckInterface.php` with `getId()`, `getLabel()`, `run()` → returns `['status' => 'ok|warn|fail', 'message' => '…', 'actionUrl' => '…']`.
   - Registry `src/Health/HealthCheckRegistry.php` collects all checks registered via DI.

2. **Initial checks**
   - `HomepageTitleTemplateCheck` — passes if homepage has a non-empty title template OR site tagline.
   - `DefaultOgImageCheck` — fail if `settings.defaultOgImage` is 0.
   - `VerificationConfiguredCheck` — pass if at least one webmaster verification filled.
   - `SitemapReachableCheck` — HEAD request to `/wp-sitemap.xml`, warn if non-200.
   - `MissingMetaDescriptionCheck` — count public posts without `es_seo_description`; warn if >0.
   - `MissingFocusKeyphraseCheck` — count published posts without `es_seo_focus_keyphrase` (opt-in, default off — some sites don't use keyphrases).
   - `AttachmentPagesIndexableCheck` — warn if attachment redirect is disabled.
   - `ExpiredUnavailableAfterCheck` — count posts with `unavailable_after < now`.

3. **Caching**
   - Run checks on a transient (`es_seo_health_status`) with 1h TTL.
   - "Refresh" button forces re-run.

4. **UI: dashboard widget**
   - `wp_add_dashboard_widget` → compact table with status icons + "view full report" link.

5. **UI: dedicated tab**
   - New tab "Health" in `AdminApp.js`.
   - List all checks grouped by status.
   - "Dismiss" per check (stored in user meta, not global).

6. **REST endpoint**
   - Optional: expose `/wp-json/es-seo/v1/health` via an `AbstractRoute` (follows eightshift-meilisearch pattern) for external monitoring.

7. **Testing**
   - Break each check deliberately (clear OG image, set bad sitemap URL, etc.) → confirm status changes.

---

## Phase 5 acceptance checklist

- [ ] IndexNow successfully pings Bing (verify via Bing Webmaster Tools "URL submission" log).
- [ ] hreflang validated by `hreflang.org` or Google Search Console's International Targeting report.
- [ ] Image sitemap includes all featured + inline content images.
- [ ] Round-trip settings export/import preserves all data.
- [ ] WP-CLI commands pass on PHP 8.4 and WP 6.8.
- [ ] Health dashboard refreshes on demand and shows cached status otherwise.

---

# Cross-phase work

## Testing strategy

- **Unit tests** — target `TemplateResolver`, robots directive resolution, primary category fallback, adapter resolver. Use existing phpunit setup in `eightshift-libs`.
- **Integration tests** — smoke tests via `wp-cli eval-file` scripts that publish a post, read HEAD, and assert tags.
- **E2E checks** — Rich Results Test, Facebook Debugger, Twitter Card Validator for a sample of pages per phase.

## Documentation

- Update the plugin README with each phase's feature list.
- Add inline PHPDoc for every new `ServiceInterface` class matching existing style.
- Keep `CLAUDE.md` / architecture memory in sync after each phase if decisions shift.

## Release cadence (suggested)

- Phase 3 → `1.1.0`
- Phase 4 → `1.2.0`
- Phase 5 → `1.3.0`

Each phase ends with a tagged release + changelog entry, not individual feature releases (keeps version churn low for Eightshift projects pinning the plugin).

---

## Ordering flexibility

If resource-constrained, an alternative sequencing:

- **Must-ship:** 3.1, 3.3, 3.5, 3.6, 4.3, 4.4, 4.5 — all SEO correctness.
- **Should-ship:** 3.2, 3.4, 4.1, 4.2, 5.3 — editor quality of life.
- **Nice-to-ship:** 1.9 (5.1), 5.2, 5.4, 5.5 — integrations.

Feel free to reshuffle before kickoff.
