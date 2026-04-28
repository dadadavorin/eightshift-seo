# Eightshift SEO — GEO Implementation Plan (Phase 6 → Phase 9)

This plan covers **Tier 1** (all 10 items) and **Tier 2** (8 items — G2.3 *Wikidata entity linking* dropped per scope decision) bundled into four shippable phases.

> **Architectural reminders** (from memory + verified against current code, 2026-04-27):
> - All classes implement `ServiceInterface` with a `register()` method, auto-discovered via the DI container.
> - Settings are stored as a single JSON blob in `wp_options` (`es-seo-settings`); read via `Options::getOption([...])`.
> - All postmeta lives in individual `ServiceInterface` classes under `src/CustomMeta/` (or `src/TermMeta/`), each calling `register_meta` on `init` with a REST-enabled JSON schema. Meta key prefix: `es_seo_*`.
> - Gutenberg-only editor UI — extend the existing SEO sidebar panels.
> - Manifest (`src/Blocks/manifest.json`) is the source of truth for caps, meta keys, filters, and settings defaults.
> - Target: **PHP 8.4+**, **WordPress 6.8+**.
>
> **Decisions locked in for this plan (from review):**
> 1. **Eightshift SEO owns the canonical `@graph`.** `eightshift-utils` already exposes an off-switch for its JSON-LD schema builder, and it is **disabled by default** — so no companion change in utils is required. This plugin is the single source of truth for JSON-LD on the front-end. **G2.1 graph aggregator is moved up into Phase 6** as a load-bearing prerequisite (still architecturally important — it's how all our own schema services compose).
> 2. `llms.txt` ships **without** `llms-full.txt`.
> 3. Markdown variant uses the cleaner **`.md` suffix** URL pattern.
> 4. AI bot policy default = **fully open** (no out-of-the-box blocking).
> 5. AI-assisted summaries (G3.1) — **out of scope**, possibly future companion plugin.
> 6. AI-bot traffic logging (G2.5) ships only with **strict retention, sampling, and pruning** so the DB does not grow unbounded.
> 7. Wikidata entity linking (G2.3) — **dropped**.
> 8. Statistic + Quote authoring blocks (G2.7) — **in scope**, despite "no schema-block pack" stance, because they're authoring helpers, not schema-only blocks.
> 9. Pre-publish GEO checks (G2.4) — **bundled into the existing pre-publish keyphrase panel**, not behind a separate toggle.

---

# Phase 6 — "GEO Foundations"

**Goal:** Take ownership of JSON-LD output, add bot-level governance, and lay the author E-E-A-T groundwork. Nothing here demands editor-side changes beyond profile fields.

**Features:** G2.1 Unified `@graph` aggregator · G1.1 AI crawler governance · G1.4 Author E-E-A-T schema · G1.7 `noai` / `noimageai` directives · G1.8 Article schema enrichment · G1.10 GEO health checks (incremental)

---

## 6.1 Unified `@graph` schema aggregator

**Outcome:** A single `<script type="application/ld+json">{ "@context", "@graph": [...] }</script>` is emitted in `wp_head`, populated by every contributor (BreadcrumbList, SiteRepresentation, Author, Article, FAQ, HowTo, Speakable, etc.). All existing schema services switch from "emit my own script" to "contribute a node." `eightshift-utils` is updated in parallel to opt out of its own emission.

### Tasks

1. **New service: `src/Schema/GraphEmitter.php`**
   - Hooks `wp_head` at priority 11 (between meta tags and og tags).
   - Reads contributors from `apply_filters('es_seo_schema_graph', [], $context)`, where `$context` is an array (`is_singular`, `post_id`, `is_archive`, `queried_object`, etc.).
   - De-duplicates by `@id` (last write wins, with a `_doing_it_wrong` log on collision in `WP_DEBUG`).
   - Emits one consolidated script with stable key order.
   - Uses `wp_json_encode( …, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )` and `_wp_specialchars( …, ENT_NOQUOTES )`.

2. **Refactor existing schema services into contributors**
   - `BreadcrumbListSchema.php` — was: emit `<script>`. Now: register a callback on `es_seo_schema_graph` that returns the breadcrumb node array (or `null` if not applicable).
   - `SiteRepresentationSchema.php` — same treatment for Organization / Person nodes.
   - Each node gets a stable `@id` derived from URL: e.g. `home_url('/#organization')`, `home_url('/#breadcrumb-' . $post_id)`.

3. **Cross-references**
   - Article nodes (added in 6.5) reference `publisher` and `author` by `@id`, not inline.
   - Person nodes (added in 6.3) reference `worksFor` by Org `@id`.
   - This is the whole point of the graph — one identity per entity, referenced by `@id`.

4. **Coordination with `eightshift-utils`**
   - The utils JSON-LD schema builder already has an off-switch and **ships disabled by default** — no companion change required.
   - As a defensive measure, register a runtime self-check on `wp_head` (priority 999) in `WP_DEBUG`-only mode: count emitted `<script type="application/ld+json">` blocks; if >1, log a `_doing_it_wrong` pointing the dev at the utils setting. Production sites see nothing.
   - Document the relationship in the SEO plugin README ("This plugin owns JSON-LD; utils' schema builder must remain off — which is the default").

5. **Filters**
   - `es_seo_schema_graph` (already in manifest) — add to inline PHPDoc.
   - `es_seo_schema_node_{$type}` — per-node filter for late tweaks (e.g. `es_seo_schema_node_organization`).
   - `es_seo_schema_context` — modify the context passed to contributors.

6. **Testing**
   - Validate homepage and a sample post against Google Rich Results Test.
   - Confirm no duplicate `<script type="application/ld+json">` blocks when both plugins are active.
   - Unit-test `@id` collision handling.

**Acceptance:**
- Exactly one JSON-LD `<script>` in `wp_head` regardless of how many contributors fire.
- All current schema (Breadcrumb, Site Representation) keeps validating.
- `eightshift-utils` pairs cleanly via the disable switch.

---

## 6.2 AI crawler governance (robots.txt + per-bot policy)

**Outcome:** A new "AI crawlers" tab in settings with per-bot policy (Allow / Disallow / Crawl-delay) for the major AI agents. Output goes into `robots.txt` via the existing `Sitemap/SitemapHooks.php` hook. Default policy is **Allow all**.

### Tasks

1. **Bot registry**
   - Add `src/Config/AiBotRegistry.php` — static class with a `getBots(): array` method returning a versioned list:
     ```php
     [
       'gptbot'           => ['name' => 'GPTBot',           'vendor' => 'OpenAI',    'category' => 'training'],
       'oai-searchbot'    => ['name' => 'OAI-SearchBot',    'vendor' => 'OpenAI',    'category' => 'search'],
       'chatgpt-user'     => ['name' => 'ChatGPT-User',     'vendor' => 'OpenAI',    'category' => 'user'],
       'claudebot'        => ['name' => 'ClaudeBot',        'vendor' => 'Anthropic', 'category' => 'training'],
       'claude-searchbot' => ['name' => 'Claude-SearchBot', 'vendor' => 'Anthropic', 'category' => 'search'],
       'claude-user'      => ['name' => 'Claude-User',      'vendor' => 'Anthropic', 'category' => 'user'],
       'google-extended'  => ['name' => 'Google-Extended',  'vendor' => 'Google',    'category' => 'training'],
       'perplexitybot'    => ['name' => 'PerplexityBot',    'vendor' => 'Perplexity','category' => 'search'],
       'perplexity-user'  => ['name' => 'Perplexity-User',  'vendor' => 'Perplexity','category' => 'user'],
       'applebot-extended'=> ['name' => 'Applebot-Extended','vendor' => 'Apple',     'category' => 'training'],
       'ccbot'            => ['name' => 'CCBot',            'vendor' => 'Common Crawl','category' => 'training'],
       'bytespider'       => ['name' => 'Bytespider',       'vendor' => 'ByteDance', 'category' => 'training'],
       'meta-externalagent'=>['name' => 'Meta-ExternalAgent','vendor' => 'Meta',     'category' => 'training'],
       'mistralai-user'   => ['name' => 'MistralAI-User',   'vendor' => 'Mistral',   'category' => 'user'],
       'cohere-ai'        => ['name' => 'cohere-ai',        'vendor' => 'Cohere',    'category' => 'training'],
       'duckassistbot'    => ['name' => 'DuckAssistBot',    'vendor' => 'DuckDuckGo','category' => 'search'],
       'youbot'           => ['name' => 'YouBot',           'vendor' => 'You.com',   'category' => 'search'],
       'ai2bot'           => ['name' => 'Ai2Bot',           'vendor' => 'AI2',       'category' => 'training'],
     ]
     ```
   - Add `LAST_VERIFIED` constant (date string) — surfaced in admin UI.
   - Filter: `es_seo_ai_bot_registry` so projects can extend.

2. **Manifest & defaults**
   - Add to `optionsDefaultValue`:
     ```json
     "aiCrawlers": {
       "enabled": true,
       "defaultPolicy": "allow",
       "perBot": {}
     }
     ```
   - `perBot` is a sparse map keyed by bot id → `{ "policy": "allow"|"disallow", "crawlDelay": 0 }`. Empty map = use `defaultPolicy`.
   - New group `aiCrawlers` under `groups.manage`.

3. **Settings UI**
   - New tab `AiCrawlersTab.js` under `src/Blocks/components/admin-settings/assets/tabs/`.
   - Sections grouped by **vendor** (collapsible).
   - Each row: bot name + category badge (Training / Search / User) + RadioControl (Allow / Disallow) + numeric Crawl-Delay.
   - Top of tab: site-wide `defaultPolicy` switch + last-verified date + link to docs.
   - "Reset to defaults" button per vendor.

4. **robots.txt emission**
   - Extend `Sitemap/SitemapHooks.php`'s existing `robots_txt` filter handler.
   - For each bot with non-default policy: emit a stanza:
     ```
     User-agent: GPTBot
     Disallow: /
     ```
   - Skip bots matching `defaultPolicy` (keep robots.txt small).
   - **Order:** AI bot stanzas come **before** the existing sitemap line.

5. **Filter contract**
   - `es_seo_ai_crawler_robots_txt` — final string, applied just before output.

6. **Testing**
   - Set GPTBot to Disallow → fetch `/robots.txt` → confirm stanza present.
   - Set defaultPolicy to "disallow" → confirm a single catch-all stanza emits for every bot.
   - Validate robots.txt with [technicalseo.com/tools/robots-txt/](https://technicalseo.com/tools/robots-txt/).

**Acceptance:**
- Settings round-trip cleanly via REST.
- Default install adds **zero** stanzas (fully open).
- Toggling a bot Disallow → robots.txt updates immediately (no caching beyond WP's normal).

---

## 6.3 Author entity (`Person`) schema with E-E-A-T fields

**Outcome:** Per-user profile fields capturing E-E-A-T signals; a new schema contributor that emits a `Person` node into the graph (referenced by `author` on Article nodes).

### Tasks

1. **User meta registration: `src/UserMeta/AuthorProfileMeta.php` (new directory)**
   - Register on `init` via `register_meta('user', ...)` for:
     - `es_seo_author_credentials` (string, ≤140 chars)
     - `es_seo_author_job_title` (string)
     - `es_seo_author_organization` (string, defaults to site org name on output)
     - `es_seo_author_same_as` (array of URI strings)
     - `es_seo_author_email_public` (boolean — default false)
   - All `show_in_rest` with strict JSON schemas; `auth_callback` requires `edit_user` cap on the target user.

2. **Profile screen extension**
   - Hook `show_user_profile` and `edit_user_profile` to render fields.
   - Hook `personal_options_update` and `edit_user_profile_update` to save.
   - "Social profiles" repeater (linkedin, x, mastodon, github, orcid, personal site) — same shape as the existing site `sameAs`.
   - Validation: each `sameAs` must be a valid `https://` URL.

3. **Schema contributor: `src/Schema/AuthorSchema.php`**
   - Registers on `es_seo_schema_graph`.
   - On singular post: emits a `Person` node for the post author (using `WP_User`'s display name + avatar URL + the new meta fields).
   - On `is_author()` archive: emits `Person` node + sets `mainEntity` reference.
   - Stable `@id`: `home_url('/?author=' . $user_id . '#person')`.
   - Cross-reference: `worksFor` → site `Organization` `@id`.

4. **Sidebar surfacing**
   - In the existing SEO sidebar panel, add a small read-only "Author E-E-A-T" status row: ✅ if all fields filled, ⚠️ otherwise — clickable to open the user's profile.

5. **Testing**
   - Fill author profile → view post → confirm Person node in graph with correct cross-refs.
   - Validate with Google Rich Results Test.

**Acceptance:**
- `Person` node is referenced (not duplicated) when the same author owns multiple posts visible on the page.
- All new fields are REST-readable and editable subject to capability.

---

## 6.4 `noai` / `noimageai` directives (per-post + site default)

**Outcome:** Two postmeta toggles + a site-wide default; emits `<meta name="robots" content="noai">` (and per-bot variants for the major training agents) when set.

### Tasks

1. **Manifest**
   - Add to `meta`:
     ```json
     "noai": "es_seo_noai",
     "noimageai": "es_seo_noimageai"
     ```
   - Add to `optionsDefaultValue.robotsDefaults`:
     ```json
     "ai": {
       "noai": false,
       "noimageai": false
     }
     ```

2. **CustomMeta classes**
   - `src/CustomMeta/SeoNoaiMeta.php` (boolean, REST-enabled).
   - `src/CustomMeta/SeoNoimageaiMeta.php` (boolean, REST-enabled).

3. **Sidebar UI**
   - Add a "AI training" subsection in the existing SEO sidebar panel (collapsed by default).
   - Two `ToggleControl`s. Help text: "Honored by Anthropic, Microsoft, and selected AI vendors. Not all bots respect these directives."

4. **Settings UI (Advanced tab)**
   - Two site-wide ToggleControls — used as fallback when post meta is `null`.

5. **Head emitter: extend `src/Head/RobotsDirectives.php`**
   - Resolve effective values (post meta → site default → `false`).
   - When `noai` truthy: emit
     ```html
     <meta name="robots" content="noai">
     <meta name="GPTBot" content="noai">
     <meta name="ClaudeBot" content="noai">
     <meta name="Google-Extended" content="noai">
     <meta name="Applebot-Extended" content="noai">
     ```
   - When `noimageai` truthy: same per-bot list with `noimageai` content.
   - Filter: extend existing `es_seo_robots`.

6. **Testing**
   - Toggle per-post → view-source → confirm tags.
   - Toggle site-wide, leave post meta null → tags appear.
   - Set post override to off when site default is on → tags do **not** appear.

**Acceptance:**
- Tag output matches the documented honor list.
- Tooltip text in admin clearly notes which bots respect the directive (and that this is an evolving compliance landscape).

---

## 6.5 Article schema enrichment

**Outcome:** A first-class `Article` (or `BlogPosting` / `NewsArticle` per post-type mapping) node is contributed to the graph for every singular post-type singular view, with author, publisher, dates, headline, section, wordCount, language, image, and `mainEntityOfPage`.

### Tasks

1. **Audit current emission**
   - Verify what `eightshift-utils` is currently emitting for Article. With Decision #1, utils is being switched off — so we **own** Article output.

2. **New service: `src/Schema/ArticleSchema.php`**
   - Registers on `es_seo_schema_graph`.
   - Guard: `is_singular($supportedPostTypes)`.
   - Type mapping (filterable via `es_seo_article_type`):
     - `post` → `BlogPosting`
     - `page` → `WebPage` (handled separately, see step 5)
     - news post type → `NewsArticle`
     - default → `Article`
   - Fields:
     - `@id` → `get_permalink() . '#article'`
     - `headline` → SEO title (truncated to 110 chars; `_doing_it_wrong` log if longer)
     - `description` → meta description
     - `datePublished`, `dateModified` → ISO 8601 with timezone
     - `inLanguage` → `get_locale()` mapped to BCP-47
     - `wordCount` → `str_word_count( wp_strip_all_tags( get_the_content() ) )`
     - `articleSection` → primary category name (use existing `es_seo_primary_category` if set; else first category)
     - `image` → featured image as `ImageObject` with `width`, `height`, `caption` (alt)
     - `author` → `{"@id": …}` referencing the Person node from 6.3
     - `publisher` → `{"@id": …}` referencing the Organization node from existing site representation
     - `mainEntityOfPage` → `{"@type": "WebPage", "@id": permalink}`
     - `keywords` → focus keyphrase + tag names (deduped)

3. **WebPage node**
   - For `is_page()` views, emit a `WebPage` node instead of `Article`.
   - For all singular views, also emit a minimal `WebPage` node referenced by `mainEntityOfPage` — even for posts.

4. **Filters**
   - `es_seo_article_schema_node` — final node, just before contribution.
   - `es_seo_webpage_schema_node` — same for WebPage.

5. **Testing**
   - Validate sample post with Google Rich Results Test → expect `Article` rich result eligibility.
   - Confirm `author` and `publisher` resolve via `@id` (not duplicated).

**Acceptance:**
- All Article-eligible posts pass Rich Results Test.
- `wordCount` matches manual count within ±5%.

---

## 6.6 GEO health checks (incremental)

**Outcome:** New checks added to the existing `Health/` dashboard.

### Tasks

1. **New checks under `src/Health/Checks/`**
   - `AiCrawlerPolicySetCheck.php` — passes if `aiCrawlers.enabled` is `true` (configured rather than ignored).
   - `AuthorsHaveBioCheck.php` — counts users with at least one published post and missing `description` or `es_seo_author_same_as`. Surfaces top offenders.
   - `SiteRepresentationCompleteCheck.php` — passes if Organization name + logo + ≥2 `sameAs` entries are configured.
   - `ArticleSchemaCoverageCheck.php` — sample 20 recent posts, count those without featured image / missing meta description (impacting Article schema completeness).

2. **Integration**
   - Each check implements `HealthCheckInterface`.
   - Auto-discovered via existing pattern.
   - Filter: existing `es_seo_health_checks`.

3. **Testing**
   - Verify each pass/warn/fail state visually in the dashboard.

**Acceptance:**
- Dashboard renders new checks without layout regressions.
- Each check completes in <50ms on a sample-size-20 query.

---

# Phase 7 — "Citable Content"

**Goal:** Give content the *machine-readable surfaces* and *editor-side structure* AI engines reward — Markdown variants, structured citations, FAQ/HowTo, TL;DR, speakable, and pre-publish nudges.

**Features:** G1.2 `llms.txt` · G1.3 Markdown variant (`.md`) · G1.5 TL;DR · G1.6 Citations field · G1.9 Speakable · G2.2 FAQ + HowTo postmeta · G2.4 Pre-publish GEO checks (bundled into existing panel)

---

## 7.1 `llms.txt` generator

**Outcome:** `/llms.txt` (Markdown) is auto-generated and served at site root, listing canonical URLs grouped by section. **Just `llms.txt` — no `llms-full.txt` per scope decision.**

### Tasks

1. **Manifest & defaults**
   - Add to `optionsDefaultValue`:
     ```json
     "llmsTxt": {
       "enabled": true,
       "intro": "",
       "outro": "",
       "postTypes": ["page", "post"],
       "perTypeLimit": 200
     }
     ```
   - New group `llmsTxt` in `groups.manage`.

2. **Settings UI**
   - New tab `LlmsTxtTab.js`:
     - `ToggleControl` enabled
     - `TextareaControl` intro / outro (Markdown allowed; rendered in a preview)
     - `MultiSelectControl` postTypes (filtered by `es_seo_supported_post_types`)
     - `RangeControl` perTypeLimit (50–500)
     - Live preview of output (calls a REST endpoint that returns the generated content; no file write).

3. **Generator service: `src/Llms/LlmsTxtGenerator.php`**
   - Output structure:
     ```markdown
     # {Site Name}

     > {Site tagline / intro}

     ## Pages
     - [Page Title](https://…/page) — TL;DR or excerpt (≤140 chars)

     ## Posts
     - [Post Title](https://…/post) — TL;DR or excerpt (≤140 chars)

     {Outro}
     ```
   - Source of TL;DR per item: postmeta `es_seo_tldr` (from 7.3) → falls back to meta description → falls back to excerpt.
   - Skip items with `es_seo_noindex` truthy.
   - Apply `perTypeLimit` per group; if truncated, append "_(showing N most recent — see sitemap for full list)_" line.

4. **Routing & caching**
   - Add a rewrite rule: `^llms\.txt$` → `index.php?es_seo_llms_txt=1`.
   - Hook `template_redirect`: when the query var is set, serve the cached content with `Content-Type: text/markdown; charset=utf-8`.
   - Cache: `set_transient('es_seo_llms_txt', $output, DAY_IN_SECONDS)`.
   - Invalidate on `save_post`, `deleted_post`, `update_option('es-seo-settings', …)`.
   - Hard size cap: 256 KB. If exceeded, log a `_doing_it_wrong` and truncate.

5. **CLI**
   - Add to `src/Cli/SeoCommand.php`:
     - `wp es-seo llms regenerate` — clears transient and primes the cache.
     - `wp es-seo llms preview` — prints to stdout.

6. **Testing**
   - With `enabled=false`: `/llms.txt` returns 404.
   - With `enabled=true`: `/llms.txt` returns 200 with valid Markdown.
   - Save a post → transient invalidates → next request regenerates.

**Acceptance:**
- File served under 50ms on cache hit.
- Output passes `markdownlint` (no syntax errors).

---

## 7.2 Markdown variant of singular content (`.md` suffix)

**Outcome:** Any singular post URL with `.md` appended (e.g. `/blog/my-post.md`) returns clean Markdown of the post body, prepended with YAML front-matter.

### Tasks

1. **Rewrite rule**
   - On `init`, register rules for each supported post type:
     ```php
     add_rewrite_rule('^([^/]+)\.md$', 'index.php?name=$matches[1]&es_seo_md=1', 'top');
     ```
   - Same for hierarchical and dated permalinks (use the existing permalink structure as a basis).
   - Add `es_seo_md` to `query_vars`.

2. **Service: `src/Markdown/MarkdownEndpoint.php`**
   - Hook `template_redirect`.
   - When `get_query_var('es_seo_md')`:
     - Resolve the post; 404 if not public / `es_seo_noindex` set / not in supported post types.
     - Build front-matter:
       ```yaml
       ---
       title: …
       description: …
       canonical: https://…
       datePublished: 2026-04-27
       dateModified: 2026-04-27
       author: …
       tldr: …    # if 7.3 set
       ---
       ```
     - Convert post content to Markdown (see step 3).
     - Output with `Content-Type: text/markdown; charset=utf-8`.
     - `exit;`

3. **HTML→Markdown converter**
   - Reuse the same converter used for `llms.txt`. Recommend `league/html-to-markdown` (PSR-4, ~50 KB, well-maintained) — add as Composer dep, prefix via existing `vendor-prefixed/` workflow (project already does this).
   - Strip non-content blocks: navigation, sidebars, comments — i.e. start from `the_content()` filtered output, not the full template.

4. **Caching**
   - Cache by post ID + post modified timestamp:
     ```
     wp_cache_set("es_seo_md_{$post_id}_{$modified_ts}", $output, 'es_seo', HOUR_IN_SECONDS);
     ```
   - Object-cache compatible; falls back to transients on object-cache-less sites.
   - On `save_post`: delete the cache key.

5. **Robots / canonical**
   - Each `.md` response includes a `Link: <{canonical}>; rel="canonical"` HTTP header so AI crawlers know the HTML is canonical.
   - Sitemap does **not** list `.md` URLs (G2.9 sitemap variant covers that — Phase 8).

6. **Edge cases**
   - Posts with shortcodes — render via `do_shortcode` first, then convert. Document caveats.
   - Password-protected posts → 404 on `.md`.
   - Drafts / scheduled → 404.
   - Multi-page posts (`<!--nextpage-->`): include all pages concatenated; document this clearly.

7. **Testing**
   - Plain text post: round-trip cleanly.
   - Post with images: `![alt](url)` retained.
   - Post with code blocks (Gutenberg `core/code`): fenced code preserved.
   - 100 posts × concurrent fetch: cache holds, no DB stampede.

**Acceptance:**
- `.md` URL renders in <100ms on cache hit, <500ms on cache miss.
- HTTP 404 for non-supported types or non-public posts.
- `Link` header present and correct.

---

## 7.3 TL;DR / Direct Answer field

**Outcome:** New postmeta `es_seo_tldr`, surfaced in the SEO sidebar with a 40–80 word soft cap. Used by `llms.txt`, `.md` front-matter, and Speakable.

### Tasks

1. **Manifest**
   - Add `"tldr": "es_seo_tldr"` to `meta`.

2. **CustomMeta: `src/CustomMeta/SeoTldrMeta.php`**
   - String, `show_in_rest`, schema with `maxLength: 600` (hard cap; soft cap is UX-side).

3. **Sidebar UI**
   - Add a `TextareaControl` in the existing SEO panel.
   - Live word counter; visual class `ok` for 40–80 words, `short` <40, `long` >80.
   - Help: "An AI-friendly direct answer for this post — used in `llms.txt`, `.md` front-matter, and read-aloud snippets."

4. **Output integration**
   - Already wired: `LlmsTxtGenerator` (7.1) and `MarkdownEndpoint` (7.2) read this field.
   - **Speakable contributor (7.5)** uses it as the primary speakable target.
   - **No** auto-fallback to `<meta name="description">` — keep these fields independent (decision: avoid surprising precedence).

5. **Testing**
   - Round-trip via REST.
   - Counter ticks correctly.

**Acceptance:**
- Field appears in the SEO sidebar without crowding other controls.
- REST schema validates a 600-char string and rejects beyond.

---

## 7.4 Citations / Sources field

**Outcome:** A repeater postmeta storing references; emitted as `citation` on the Article node and (optionally, via a template tag) rendered as a "Sources" list at the bottom of the post.

### Tasks

1. **Manifest**
   - Add `"citations": "es_seo_citations"` to `meta`.

2. **CustomMeta: `src/CustomMeta/SeoCitationsMeta.php`**
   - Type: array of objects.
   - JSON schema:
     ```php
     [
       'type' => 'array',
       'items' => [
         'type' => 'object',
         'properties' => [
           'label'         => ['type' => 'string', 'maxLength' => 240],
           'url'           => ['type' => 'string', 'format' => 'uri'],
           'publisher'     => ['type' => 'string', 'maxLength' => 120],
           'datePublished' => ['type' => 'string', 'format' => 'date'],
         ],
         'required' => ['label', 'url'],
       ],
     ]
     ```
   - `show_in_rest` with this full schema.

3. **Sidebar UI**
   - New collapsible "Citations" subsection in the SEO panel.
   - Repeater with: Label, URL, Publisher (optional), Date (optional).
   - Drag-to-reorder.
   - URL validation client-side.

4. **Schema integration (extends 6.5)**
   - In `ArticleSchema.php`, when citations are non-empty, include:
     ```php
     'citation' => array_map(fn($c) => [
       '@type'         => 'CreativeWork',
       'name'          => $c['label'],
       'url'           => $c['url'],
       'publisher'     => $c['publisher'] ? ['@type' => 'Organization', 'name' => $c['publisher']] : null,
       'datePublished' => $c['datePublished'] ?: null,
     ], $citations);
     ```
   - Strip nulls.

5. **Optional front-end rendering**
   - Add a template tag: `\EightshiftSeo\Templates\Citations::render( $post_id )`.
   - Returns HTML (`<aside class="es-seo-sources">…</aside>`).
   - Theme integration is opt-in — no automatic content filter.
   - Document the tag in the README.

6. **Filter**
   - `es_seo_citations_render` — modify HTML before return.

7. **Testing**
   - Add 5 citations → verify in graph + (if rendered) on front-end.
   - Submit malformed URL via REST → 400.

**Acceptance:**
- Repeater is keyboard-accessible (drag-and-drop has keyboard fallback).
- `citation` array appears on Article node.

---

## 7.5 Speakable selectors

**Outcome:** Article nodes get a `speakable` annotation pointing at TL;DR + first H2 + first list (overridable per post).

### Tasks

1. **Manifest**
   - Add `"speakableSelectors": "es_seo_speakable_selectors"` to `meta`.

2. **CustomMeta: `src/CustomMeta/SeoSpeakableSelectorsMeta.php`**
   - Type: array of strings (CSS selectors).
   - Default empty → use auto.

3. **Auto selectors (when meta empty)**
   - Build from:
     - `.es-seo-tldr` (a class auto-applied by the rendering of TL;DR if/when surfaced — for now, document the convention)
     - `article h2:first-of-type`
     - `article ul:first-of-type`
   - Filter: `es_seo_speakable_default_selectors`.

4. **Schema contributor: extends `ArticleSchema.php`**
   - Add `speakable`:
     ```php
     'speakable' => [
       '@type'    => 'SpeakableSpecification',
       'cssSelector' => $selectors,
     ]
     ```
   - Skip if neither TL;DR nor selectors resolved.

5. **Sidebar UI (advanced subsection)**
   - Optional `TextControl` array — most authors will leave empty (auto).
   - Help text: "CSS selectors for AI / voice readouts. Leave empty to auto-detect."

6. **Testing**
   - With TL;DR + auto: speakable contains `.es-seo-tldr` first.
   - With manual override: speakable contains exactly the configured selectors.

**Acceptance:**
- Validates with Google Rich Results Test.

---

## 7.6 FAQ + HowTo postmeta-driven schema

**Outcome:** Two repeater postmeta fields drive `FAQPage` and `HowTo` JSON-LD. Editing in the SEO sidebar (no new blocks). Front-end rendering is opt-in via template tag.

### Tasks

1. **Manifest**
   - Add to `meta`:
     ```json
     "faq": "es_seo_faq",
     "howto": "es_seo_howto"
     ```

2. **CustomMeta classes**
   - `src/CustomMeta/SeoFaqMeta.php` — array of `{question, answer}` (both required, both ≤ reasonable caps: question 200 chars, answer 1500).
   - `src/CustomMeta/SeoHowtoMeta.php` — `{name, description?, totalTime?, steps: [{name, text, image?}]}`.

3. **Sidebar UI**
   - New collapsible "FAQ" subsection.
   - New collapsible "HowTo" subsection (mutually compatible — a post can have both).
   - Repeater patterns; drag-to-reorder; HTML-stripping in answers (text-only).

4. **Schema contributors**
   - `src/Schema/FaqSchema.php` — registers on `es_seo_schema_graph`. Emits `FAQPage` node with `mainEntity` array of `Question`s. `@id` = `permalink#faq`.
   - `src/Schema/HowtoSchema.php` — emits `HowTo` node with `step` array of `HowToStep`s. `@id` = `permalink#howto`.
   - Skip emission if arrays empty.

5. **Optional front-end rendering**
   - Template tags in `\EightshiftSeo\Templates\`:
     - `Faq::render( $post_id )` → `<dl class="es-seo-faq">…</dl>`
     - `Howto::render( $post_id )` → ordered list with optional images.
   - Filters: `es_seo_faq_render`, `es_seo_howto_render`.

6. **Health check addition**
   - "FAQ usage" stats — count of posts using FAQ schema in the dashboard (informational, not pass/fail).

7. **Testing**
   - Add 3 FAQs → validate FAQPage with Google Rich Results Test.
   - Empty arrays → no schema emission.

**Acceptance:**
- FAQ + HowTo can coexist on the same post without duplicate `@id`.
- REST schema rejects HTML in question text.

---

## 7.7 Pre-publish GEO checks (bundled into existing panel)

**Outcome:** The existing pre-publish keyphrase panel gains GEO-specific checks. **Bundled, not toggleable** per scope decision.

### Tasks

1. **Locate existing panel**
   - Find current pre-publish panel registration (likely in JS under `src/Blocks/components/admin-pre-publish/` or similar).

2. **Add checks (all client-side, run against editor state)**
   - **Definition-first opener:** first 200 chars of `core/editor.getEditedPostContent()` plain-text matches `/^[^.!?]*\b(is|are|means|refers to)\b/i`.
   - **At least one statistic:** plain-text contains `/\b\d+(\.\d+)?\s?%|\b(in|by|since)\s\d{4}\b/`.
   - **At least one citation:** `es_seo_citations` non-empty OR `the_content` contains ≥1 outbound link to a non-self domain.
   - **TL;DR filled:** `es_seo_tldr` set.
   - **Heading hierarchy:** no skipped levels (use editor block tree; check `core/heading` blocks' `level` attr in order).
   - **At least one image with alt:** scan `core/image` blocks; report count missing alt.
   - **FAQ presence (informational):** `es_seo_faq` non-empty OR ≥3 H3 ending in `?`.

3. **UI**
   - Each check: ✅ pass / ⚠️ warn / ⓘ info, with one-line explanation.
   - Each fail/warn links to the relevant sidebar control (or in-content position via block-id scroll).
   - Group under "GEO readiness" within the existing panel.

4. **Localization**
   - All strings translatable via the existing text domain.

5. **Testing**
   - Each check exercised individually.
   - No console errors when fields are missing or empty.

**Acceptance:**
- Panel opens in <50ms.
- All checks run client-side; no extra REST requests.

---

# Phase 8 — "Authoring nudges & freshness"

**Goal:** Encourage GEO-friendly authoring patterns and capture freshness signals.

**Features:** G2.6 Freshness signals · G2.7 Statistic + Quote blocks · G2.8 Definition-first mode · G2.9 AI sitemap variant

---

## 8.1 Content freshness signals

**Outcome:** Per-post `dateReviewed` postmeta + a site-wide setting "only update `dateModified` when content body changes" + a "stale content" health dashboard widget.

### Tasks

1. **Manifest**
   - Add `"dateReviewed": "es_seo_date_reviewed"` to `meta`.
   - Add to `optionsDefaultValue`:
     ```json
     "freshness": {
       "preserveModifiedOnNonContentSave": false,
       "stalenessThresholdDays": 365
     }
     ```

2. **CustomMeta: `src/CustomMeta/SeoDateReviewedMeta.php`**
   - String, `format: date` (YYYY-MM-DD).

3. **Sidebar UI**
   - Add `DatePicker` "Last reviewed" in the existing panel.
   - "Mark as reviewed today" quick-action button.

4. **Schema integration (extends 6.5)**
   - In `ArticleSchema.php`, when set, include:
     ```php
     'dateReviewed' => $reviewed,
     ```

5. **`dateModified` preservation**
   - Hook `wp_insert_post_data` (priority 99): when `preserveModifiedOnNonContentSave` is on AND only non-content fields changed (compute hash of `post_content` vs. previous), restore the previous `post_modified`.
   - Implementation note: use `get_post_field('post_content', $ID)` snapshot in a `pre_post_update` action; compare; conditionally reset.

6. **Health dashboard widget**
   - New check `StaleContentCheck.php`: lists top 20 posts whose `dateModified` is older than `stalenessThresholdDays` and have substantial traffic (we don't have traffic data — just sort by views via `comment_count` proxy, or simply by age).
   - Display in dashboard with "Mark as reviewed" actions per row.

7. **Testing**
   - Toggle non-content preservation → save a post via Quick Edit (no content change) → confirm `post_modified` unchanged.
   - Add review date → confirm in schema.

**Acceptance:**
- No regressions in `dateModified` for content edits.
- Stale list refreshes within 60s of dashboard load.

---

## 8.2 Statistic + Quote authoring blocks

**Outcome:** Two minimal Gutenberg blocks (`es-seo/statistic`, `es-seo/quote`) with structured inputs and visible HTML output. Each contributes a small JSON-LD fragment to the parent Article via the graph.

### Tasks

1. **Block: `es-seo/statistic`**
   - Path: `src/Blocks/components/blocks/statistic/`.
   - Attributes: `value` (string), `label` (string), `source` (string), `sourceUrl` (URL), `datePublished` (date).
   - Edit UI: simple form layout.
   - Save: semantic HTML —
     ```html
     <figure class="es-seo-statistic">
       <strong>{value}</strong>
       <figcaption>{label} <cite><a href="{sourceUrl}">{source}</a></cite></figcaption>
     </figure>
     ```
   - Style: minimal — themes can override.

2. **Block: `es-seo/quote`**
   - Path: `src/Blocks/components/blocks/expert-quote/`.
   - Attributes: `quote`, `author`, `authorTitle`, `authorUrl`.
   - Save:
     ```html
     <figure class="es-seo-quote">
       <blockquote cite="{authorUrl}">{quote}</blockquote>
       <figcaption>{author}, {authorTitle}</figcaption>
     </figure>
     ```

3. **Schema contributions**
   - On `the_content` (or post-render-time), parse blocks (`parse_blocks(get_post()->post_content)`) for these block names.
   - For each statistic: contribute a `Claim` entry into the Article's `mentions` array (or stand-alone `Quotation` for quotes).
   - This needs to flow through the graph emitter — extend `ArticleSchema.php` to call a small helper `extractInlineSchemaFromBlocks(int $post_id): array`.
   - Cache parsed blocks per post + modified time to avoid re-parsing.

4. **Block discovery**
   - Confirm `useBlocks` flag in manifest config (currently `false`) — toggle to `true` or scope the new blocks under a separate registration path. (Verify with current Eightshift block conventions — likely the `useBlocks` flag was off because no blocks existed yet.)

5. **Sidebar surfacing**
   - In the GEO pre-publish checks (7.7), reward presence of these blocks under the "Statistic / Quote present" indicator.

6. **Testing**
   - Insert each block; round-trip; confirm front-end rendering.
   - Confirm schema contributions in the graph.

**Acceptance:**
- Both blocks pass `block.json` validation.
- Schema contributions don't double-emit on AMP / cached pages.

---

## 8.3 Definition-first mode (opt-in)

**Outcome:** Optional sidebar toggle that prompts the author to use a definition-first opener; emits `DefinedTerm` schema when a `<p class="es-seo-definition">` (or attribute on first paragraph) is present.

### Tasks

1. **Manifest**
   - Add `"definitionTerm": "es_seo_definition_term"` to `meta`.

2. **CustomMeta**
   - `SeoDefinitionTermMeta.php` — string (the term itself).
   - Optional: keep auto-detection only; skip explicit meta if author marks the paragraph via class.

3. **Author UX (lightweight)**
   - In the SEO sidebar, add a `TextControl` "Defined term" + Toggle "Use definition-first opener."
   - When toggled on, inject a small editor notice (via `core/notices`) if the post's first paragraph doesn't match the regex from 7.7 → suggest fixing.

4. **Detection**
   - Server-side: parse first block; if `core/paragraph` and content matches `/^.{0,50}?\b(is|are|means|refers to)\b/i`, treat as a definition.
   - Override: if `es_seo_definition_term` set, use that as `name` regardless.

5. **Schema contribution**
   - Add `DefinedTerm` node to graph when detected:
     ```php
     [
       '@type'       => 'DefinedTerm',
       '@id'         => "$permalink#term",
       'name'        => $term,
       'description' => $firstParagraphText,
       'inDefinedTermSet' => $siteName,
     ]
     ```
   - Reference from Article node via `about`.

6. **Pre-publish check (extension of 7.7)**
   - Promote the existing definition-first check from informational to ✅/⚠️ pass/warn.

7. **Testing**
   - Post starting with "X is …" → `DefinedTerm` emitted.
   - Toggle off → not emitted.

**Acceptance:**
- Detection runs without parsing the entire content tree on every request — cache by post + modified time.

---

## 8.4 AI-targeted sitemap variant

**Outcome:** A second sitemap (`/llm-sitemap.xml`) that includes only canonical singular URLs + their `.md` variants + `dateModified` + (if set) `dateReviewed`. Surfaced as a recommendation in the AI Crawlers tab.

### Tasks

1. **Manifest**
   - Add to `optionsDefaultValue.sitemap`:
     ```json
     "llmSitemap": {
       "enabled": true,
       "includeMd": true,
       "postTypes": []
     }
     ```
   - Empty `postTypes` = inherit from main sitemap exclusions.

2. **Generator service: `src/Sitemap/LlmSitemapProvider.php`**
   - Hook into native sitemap registry via `wp_sitemaps_register_provider` with a new `'llm'` provider, OR (cleaner) provide a separate route entirely.
   - Output schema: standard sitemap-protocol XML with `<lastmod>`, plus a custom namespace for `<llm:dateReviewed>`.
   - For each post: include the canonical URL, AND a sibling entry for the `.md` variant, with `Link` rel pointing back to canonical.

3. **Routing**
   - Rewrite: `^llm-sitemap\.xml$` → `index.php?es_seo_llm_sitemap=1`.
   - Cache: filesystem cache or transient with same invalidation as native sitemap.

4. **AI Crawlers tab integration**
   - In the AI Crawlers tab (6.2), surface a "Recommended sitemap for AI crawlers" callout linking to `/llm-sitemap.xml` and showing a copy-able robots.txt directive:
     ```
     Sitemap: https://example.com/llm-sitemap.xml
     ```
   - Optional auto-include in robots.txt via existing toggle (`addToRobotsTxt`-style).

5. **CLI**
   - `wp es-seo sitemap llm` — print URL list.
   - `wp es-seo sitemap regenerate` (extends existing if present) — clears the LLM sitemap cache.

6. **Testing**
   - Validate XML with `xmllint`.
   - Confirm `.md` URLs match the routes from 7.2.
   - Disable `includeMd` → `.md` URLs absent.

**Acceptance:**
- Sitemap responds in <300ms on cache hit.
- Schema validates against sitemaps.org XSD (custom namespace declared).

---

# Phase 9 — "Bot traffic insights"

**Goal:** Operators can see whether AI bots are crawling — without growing the database unbounded.

**Feature:** G2.5 AI bot traffic insights

---

## 9.1 AI bot traffic insights (read-only, retention-bounded)

**Outcome:** A small custom DB table records **daily counters** per AI user-agent. A dashboard widget shows a 30-day chart. **No per-request rows. No URL logging. Strict pruning.**

### Tasks

1. **Custom table** — created on plugin activation
   - Table name: `{$wpdb->prefix}es_seo_bot_counters`.
   - Columns:
     ```sql
     CREATE TABLE {prefix}es_seo_bot_counters (
       id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
       day DATE NOT NULL,
       bot_id VARCHAR(64) NOT NULL,
       hits INT UNSIGNED NOT NULL DEFAULT 0,
       UNIQUE KEY uniq_day_bot (day, bot_id)
     ) {$charset_collate};
     ```
   - One row per (day, bot) — at most ~20 bots × 90 days = **~1800 rows max**. Bounded.
   - Migration via `dbDelta` in `Activate.php`.

2. **Sampling strategy (performance + privacy)**
   - Hook `init` very early.
   - Match `$_SERVER['HTTP_USER_AGENT']` against the bot registry from 6.2 (regex per bot).
   - **If non-bot user agent: bail immediately** — zero overhead for human visitors.
   - **If bot: increment day counter via in-memory buffer + shutdown hook.**
     - Buffer the increment in `wp_cache_*` (or static var if no object cache).
     - On `shutdown`, flush the buffer with a single `INSERT ... ON DUPLICATE KEY UPDATE hits = hits + N`.
     - At request scope, **only one DB write** — and only when a bot was seen.
   - **Sampling toggle:** for very-high-traffic sites, opt-in `samplingRate` (default 1 = log every bot hit). 0.1 means log 10%.

3. **Manifest & defaults**
   - Add to `optionsDefaultValue`:
     ```json
     "botInsights": {
       "enabled": false,
       "samplingRate": 1.0,
       "retentionDays": 90
     }
     ```
   - Default disabled — operator opts in.

4. **Pruning (cron)**
   - Schedule `es_seo_bot_counters_prune` daily.
   - Delete rows older than `retentionDays`.
   - Also caps total rows at 5000 as a hard safety net (older rows pruned beyond that).

5. **Settings UI (Advanced tab)**
   - Toggle enable/disable.
   - `RangeControl` retentionDays (30–365).
   - `RangeControl` samplingRate (0.01–1.0).
   - "Reset counters" destructive button.
   - Privacy note inline: "Only counts hits per user-agent per day. No IPs, URLs, or request bodies are stored."

6. **Dashboard widget**
   - Add to existing `Health/HealthDashboard.php`.
   - 30-day stacked bar chart per bot vendor (use a tiny inline SVG renderer — no charting library).
   - Top-table: total hits per bot in last 7 / 30 days.

7. **REST endpoint (read-only)**
   - `GET /wp-json/es-seo/v1/bot-insights` — returns aggregated counters; gated by capability.
   - Powers the dashboard widget.

8. **CLI**
   - `wp es-seo bots stats [--days=30]`
   - `wp es-seo bots prune`

9. **Performance guardrails**
   - Add an integration test: 1000 simulated requests with mixed UAs → measure write count → must be ≤ N (where N = unique bots seen).
   - Document: "If you serve >100M req/day, set samplingRate = 0.01."

10. **Testing**
    - Faked GPTBot UA → counter increments.
    - Human UA → no DB write.
    - Prune cron → rows older than retention deleted.
    - Disable feature → no hooks registered, table stays in place but inert.

**Acceptance:**
- **Frontend impact for human visitors: literally zero DB writes** (we bail before any work).
- Bot writes: max 1 INSERT per request.
- Table size grows linearly with `min(active_bots × retention_days, 5000)` — deterministic and bounded.

---

# Cross-cutting concerns

## Documentation
- README updates after every phase.
- A new `docs/GEO.md` page describing each feature, the AI bots honored, and link out to vendor docs (kept up to date with the bot registry's `LAST_VERIFIED` date).
- CHANGELOG entries follow Keep-a-Changelog.

## Tests
- Unit tests for: graph emitter de-duplication, robots.txt assembly, Markdown converter, citation schema, FAQ/HowTo schema, bot counter math.
- E2E tests (Playwright) for: `.md` endpoint, `llms.txt` endpoint, sidebar field round-trip, pre-publish panel.

## Migrations
- All new postmeta is additive — no migrations required.
- Custom table (9.1) created on activation; deleted on uninstall via `uninstall.php`.

## Dependencies
- Add `league/html-to-markdown` (Composer) — prefixed via existing `vendor-prefixed/` setup. Used by 7.1 + 7.2.
- No new JS deps — keep the existing `@wordpress/scripts` stack.

## Filters added (manifest update)
Add to `src/Blocks/manifest.json` `filters` block:
```json
"aiBotRegistry": "es_seo_ai_bot_registry",
"aiCrawlerRobotsTxt": "es_seo_ai_crawler_robots_txt",
"schemaNodeOrganization": "es_seo_schema_node_organization",
"schemaNodePerson": "es_seo_schema_node_person",
"articleSchemaNode": "es_seo_article_schema_node",
"webpageSchemaNode": "es_seo_webpage_schema_node",
"articleType": "es_seo_article_type",
"speakableDefaultSelectors": "es_seo_speakable_default_selectors",
"faqRender": "es_seo_faq_render",
"howtoRender": "es_seo_howto_render",
"citationsRender": "es_seo_citations_render",
"llmSitemapEntry": "es_seo_llm_sitemap_entry"
```

## Caps
- No new caps. Reuse `eightshift_seo_manage` for all settings tabs and the bot-insights dashboard.

---

# Summary table

| Phase | Theme | Features |
|---|---|---|
| **6** | GEO Foundations | G2.1 graph aggregator · G1.1 AI bot governance · G1.4 author E-E-A-T · G1.7 noai/noimageai · G1.8 Article enrichment · G1.10 health checks |
| **7** | Citable Content | G1.2 llms.txt · G1.3 `.md` endpoint · G1.5 TL;DR · G1.6 citations · G1.9 speakable · G2.2 FAQ + HowTo · G2.4 pre-publish checks |
| **8** | Authoring & freshness | G2.6 freshness · G2.7 statistic + quote blocks · G2.8 definition-first · G2.9 AI sitemap |
| **9** | Bot insights | G2.5 bounded bot-traffic counters |

---

# Open implementation questions (non-blocking, can defer)

1. **`league/html-to-markdown` license** — confirm MIT / OSL compatibility with the prefixed vendoring workflow.
2. **Block file structure** — does the Eightshift convention prefer `block.json` per block in `src/Blocks/components/blocks/{name}/`, or is there a wrapper convention? Verify before 8.2.
3. **REST namespace** — `es-seo/v1` for the bot-insights endpoint (9.1). Consistent with existing routes? (verify — there may already be one).
4. **Canonical capability for bot insights** — `eightshift_seo_manage` covers settings; should viewing the chart require a separate, lower-level capability for editor roles? Decide before 9.1.
