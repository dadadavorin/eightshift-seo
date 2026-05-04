# Eightshift SEO — Improvements & Optimisations

Findings from a full codebase review + live site testing (meditationapp.test) on 2026-05-04.
Items are grouped by priority and type. **Bugs** should be fixed first; **UX** and **code quality** issues can follow.

---

## 1. Bugs

### 1.1 Auto-excerpt truncation cuts mid-word
**File:** `src/Templates/TemplateResolver.php:267`
**Severity:** High

`mb_substr($content, 0, 160)` cuts at the 160th *character*, not at a word boundary.
The live site confirms this — the meta description ends with `...reduced anxie` (word "anxiety" is cut).

```php
// Current (buggy)
return \mb_substr($content, 0, 160);

// Fix — trim to last complete word
$trimmed = mb_substr($content, 0, 160);
if (mb_strlen($content) > 160) {
    $lastSpace = mb_strrpos($trimmed, ' ');
    $trimmed   = $lastSpace !== false ? mb_substr($trimmed, 0, $lastSpace) : $trimmed;
}
return $trimmed;
```

> Note: `ArticleSchema::buildDescription()` already uses `wp_trim_words()` correctly — the two should use the same approach for consistency.

---

### 1.2 `WebSite` JSON-LD node is missing from the schema graph
**File:** `src/Schema/ArticleSchema.php:83`, `src/Schema/` (no WebSiteSchema.php)
**Severity:** High

Both the `WebPage` and `Article` nodes reference `isPartOf: { @id: ".../#website" }`, but no `@type: WebSite` node is ever emitted into the graph. This is a dangling reference — Google's Rich Results validator will flag it as an unresolved node, and the site loses eligibility for Sitelinks Searchbox structured data.

**Fix:** Add a `WebSiteSchema.php` class (or extend `SiteRepresentationSchema`) that emits:
```json
{
  "@type": "WebSite",
  "@id": "https://example.com/#website",
  "url": "https://example.com/",
  "name": "Site Name",
  "inLanguage": "en-US",
  "potentialAction": {
    "@type": "SearchAction",
    "target": { "@type": "EntryPoint", "urlTemplate": "https://example.com/?s={search_term_string}" },
    "query-input": "required name=search_term_string"
  }
}
```
The `potentialAction` (Sitelinks Searchbox) is optional but provides an extra rich-result opportunity.

---

### 1.3 `ArticleSchema` title hard-truncated at 110 characters mid-word
**File:** `src/Schema/ArticleSchema.php:254`
**Severity:** Low

`mb_substr($title, 0, 110)` has the same mid-word issue as 1.1. Titles rarely reach 110 chars in practice, but the fix is the same pattern.

---

## 2. UX Issues

### 2.1 SERP preview character counter shows `0/60` when using a title template
**File:** `src/Blocks/components/editor-sidebar/assets/panels/SearchAppearancePanel.js`
**Severity:** Medium

When the SEO title field is empty (i.e. using the post-type default template), the character counter displays `0/60`. This is misleading — the *rendered* title is clearly visible in the preview above. Editors may think the title is not set.

**Fix:** Compute the rendered template length client-side (the resolved title is already shown in the SERP preview component) and use that as the baseline for the counter when the field is empty.

---

### 2.2 Duplicate taxonomy names in Sitemap and Advanced tabs
**File:** `src/Blocks/components/admin-settings/assets/tabs/SitemapTab.js`, `AdvancedTab.js`
**Severity:** Low-Medium

On sites with multiple public taxonomies that share the same display name (e.g., `category` from WordPress core and a custom `tribe_events_cat` that is also labelled "Category"), checkboxes appear duplicated with no way to distinguish them.

**Fix:** Append the taxonomy slug in parentheses when two or more taxonomies share the same label:
```jsx
label={`${tax.name} (${tax.slug})`}
// Or conditionally:
label={duplicateNames.has(tax.name) ? `${tax.name} (${tax.slug})` : tax.name}
```

---

### 2.3 Health tab renders a "Save settings" button with nothing to save
**File:** `src/Blocks/components/admin-settings/assets/tabs/HealthTab.js` (or `AdminApp.js`)
**Severity:** Low

The Health tab shows the global "Save settings" button at the bottom, but this tab has no editable fields — it only displays read-only check results. Clicking the button is a no-op (or saves unchanged settings). The button should be hidden on this tab.

---

### 2.4 Tools tab — no IndexNow key status or guidance
**File:** `src/Blocks/components/admin-settings/assets/tabs/ToolsTab.js`
**Severity:** Low

The IndexNow toggle enables submissions, but:
- The generated API key is not shown anywhere in the UI.
- There is no link to the key verification endpoint (`/{key}.txt`).
- Users have no way to confirm the key is correctly served without checking manually.

**Fix:** Show the generated key and a clickable link to its verification URL once IndexNow is enabled, so users can confirm it's working.

---

### 2.5 "Per-type limit" slider for llms.txt starts at 200 but manifest default is also 200
**File:** `src/Blocks/components/admin-settings/assets/tabs/LlmsTxtTab.js`
**Observation:** The manifest default and the UI default are consistent (200). No bug — but the UI does not communicate the 256 KB cap that triggers a warning in the generator. Add a note near the slider: *"The output is capped at 256 KB regardless of this setting."*

---

## 3. Code Quality & Consistency

### 3.1 Inconsistent excerpt-generation strategy
**Files:** `src/Templates/TemplateResolver.php:267`, `src/Schema/ArticleSchema.php:272`

- **Meta description path** → `TemplateResolver::getPostExcerpt()` → raw `mb_substr` at 160 chars
- **Article schema path** → `ArticleSchema::buildDescription()` → `wp_trim_words($excerpt, 30, '')`

These should use the same underlying method. A good consolidation point is `TemplateResolver::getPostExcerpt()`, which can be made the single source of truth (using `wp_trim_words` or word-boundary `mb_substr`).

---

### 3.2 WP-CLI import/export uses raw file paths without validation
**File:** `src/Cli/SeoCommand.php:74, 108`
**Severity:** Low (WP-CLI is admin-only)

`file_put_contents($file, ...)` and `file_get_contents($file)` accept a user-supplied path with no validation. A user could write to arbitrary locations.

**Fix:** Validate that the path is within `ABSPATH` or use `WP_Filesystem`:
```php
if (!\str_starts_with(\realpath(\dirname($file)) ?: '', \ABSPATH)) {
    \WP_CLI::error('File path must be inside the WordPress root.');
}
```

---

### 3.3 `%pagenumber%` is a redundant alias for `%page%`
**File:** `src/Templates/TemplateResolver.php:114-115`

Both tokens resolve to the same current page number. The alias adds surface area without value. Consider deprecating `%pagenumber%` with a filter fallback, and removing it from the Defaults tab token list.

---

### 3.4 Hard-coded 2000 post limit in `LlmSitemapProvider`
**File:** `src/Sitemap/LlmSitemapProvider.php:156`

`'posts_per_page' => 2000` can cause memory exhaustion or timeouts on large sites. The per-type limit is configurable in settings, so the same value (`$perTypeLimit`) should be reused here (or at least a filter-configurable cap applied).

---

### 3.5 Health checks also query up to 2000 posts on first run
**File:** `src/Health/` (multiple check classes)

The 1-hour transient cache hides this after the first call, but the cold-cache run can be slow. Consider adding a configurable cap (e.g. 500) to keep health checks fast, and documenting that checks are approximate for large sites.

---

## 4. Missing Features / Schema Completeness

### 4.1 `og:image:type` tag not emitted
**File:** `src/Head/OpenGraph.php`

When `og:image` is set, it is good practice to also emit `og:image:type` (e.g. `image/jpeg`, `image/png`, `image/webp`). Facebook and LinkedIn use this to optimise rendering. The MIME type can be derived from the attachment ID via `get_post_mime_type()`.

---

### 4.2 `og:image:secure_url` not emitted
**File:** `src/Head/OpenGraph.php`

For HTTPS sites, `og:image:secure_url` (duplicate of `og:image` using the HTTPS URL) is commonly added. It is technically redundant on modern HTTPS-only sites but improves compatibility with some older crawlers. Low priority but easy to add.

---

### 4.3 Schema graph lacks a `WebSite` node (see also Bug 1.2)

Covered in Bug 1.2. Adding a `WebSite` node also enables the `potentialAction: SearchAction` (Sitelinks Searchbox), which is a rich-result opportunity for high-authority sites.

---

### 4.4 No bulk meta-editing UI — WP-CLI only
**File:** `src/Cli/SeoCommand.php`

Bulk operations (set/clear meta fields across multiple posts) are only available via WP-CLI. Adding a basic bulk-operation section under the Tools tab (e.g. "Clear all noindex flags" or "Re-generate meta descriptions from templates") would make the plugin more accessible to non-technical users.

---

### 4.5 `dateReviewed` not populated on posts without explicit value
**File:** `src/Llms/` / `src/Schema/ArticleSchema.php`

The `dateReviewed` field (for AI freshness signals) is only emitted when manually set. A reasonable fallback would be to use `post_modified` as the review date, clearly labelled, so all posts carry a freshness signal by default. This could be opt-in via settings.

---

## 5. Summary Table

| # | Area | Issue | Priority |
|---|------|-------|----------|
| 1.1 | Bug | Auto-excerpt cuts mid-word | **High** |
| 1.2 | Bug | Missing `WebSite` JSON-LD node | **High** |
| 1.3 | Bug | Article schema title cut mid-word | Low |
| 2.1 | UX | SERP counter shows `0/60` with template | Medium |
| 2.2 | UX | Duplicate taxonomy labels | Medium |
| 2.3 | UX | Save button on read-only Health tab | Low |
| 2.4 | UX | IndexNow key not visible in UI | Low |
| 2.5 | UX | llms.txt 256 KB cap not communicated | Low |
| 3.1 | Code | Inconsistent excerpt strategies | Medium |
| 3.2 | Code | WP-CLI paths not validated | Low |
| 3.3 | Code | `%pagenumber%` alias redundant | Low |
| 3.4 | Code | Hard-coded 2000 limit in LLM sitemap | Medium |
| 3.5 | Code | Health checks slow on cold cache | Low |
| 4.1 | Feature | Missing `og:image:type` | Low |
| 4.2 | Feature | Missing `og:image:secure_url` | Low |
| 4.3 | Feature | WebSite schema + SearchAction | Medium |
| 4.4 | Feature | No bulk meta UI | Low |
| 4.5 | Feature | `dateReviewed` fallback to post_modified | Low |
