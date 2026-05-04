# Eightshift SEO

A lightweight, developer-first SEO plugin built for [Eightshift](https://eightshift.com/) projects. Covers everything a modern WordPress site needs for search visibility and AI discoverability — without the bloat of general-purpose SEO plugins.

---

## Features

- **Meta titles & descriptions** — template-based (`%title%`, `%sitename%`, `%sep%`, …) with per-post/term overrides
- **Robots directives** — per-post noindex/nofollow, global archive/taxonomy defaults, `wp_robots` integration
- **Canonical URLs** — auto-generated, overridable per post
- **Open Graph & Twitter Cards** — full tag set with per-post image overrides
- **JSON-LD structured data** — `WebSite`, `WebPage`, `Article`/`BlogPosting`, `BreadcrumbList`, `Person`, `Organization`, `FAQPage`, `HowTo`, `DefinedTerm`, `SpeakableSpecification` nodes
- **XML sitemap control** — exclude post types / taxonomies from the native WordPress sitemap
- **IndexNow** — instant Bing/Yandex submission on publish/update
- **llms.txt** — AI-crawler discovery file served at `/llms.txt`
- **LLM sitemap** — AI-targeted sitemap at `/llm-sitemap.xml` with optional `.md` sibling URLs
- **Markdown endpoints** — every public post is available at `/{slug}.md` for AI consumption
- **Freshness / dateReviewed** — per-post reviewed date surfaced in JSON-LD and the LLM sitemap
- **Health checks** — built-in dashboard tab that audits titles, descriptions, canonical, and more
- **WP-CLI commands** — settings export/import, bulk meta operations, sitemap ping, llms.txt regeneration
- **Settings import/export** — full JSON round-trip from the admin UI or CLI
- **Gutenberg sidebar panel** — SERP preview, title/description fields, schema toggle, noindex per post

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.4 |
| WordPress | ^6.4 |
| Composer | ^2 |
| Node / npm | For building JS assets |

---

## Installation

```bash
# Install PHP dependencies
composer install --no-dev

# Install JS dependencies and build assets
npm install
npm run build
```

Activate the plugin from **Plugins → Installed Plugins** or via WP-CLI:

```bash
wp plugin activate eightshift-seo
```

---

## Settings

All settings are stored in a single `wp_options` entry and managed via **Eightshift SEO** in the WordPress admin menu. Settings are organised into tabs:

| Tab | Description |
|---|---|
| General | Global title template, separator, homepage settings |
| Site | Site representation (Organization / Person), logo, social profiles |
| Defaults | Per-post-type title & description templates |
| Sitemap | Exclude post types / taxonomies from the native sitemap |
| Social | Open Graph defaults, Twitter card type |
| Advanced | Archive noindex defaults, attachment redirect, per-taxonomy robots |
| AI Crawlers | Bot allow/disallow rules, `robots.txt` directives |
| llms.txt | Enable llms.txt, intro/outro copy, included post types, per-type limit |
| Tools | Settings export / import, IndexNow key management |
| Health | Automated SEO audit across a sample of published posts |

---

## WP-CLI

```bash
# Export settings to a JSON file
wp es-seo settings export
wp es-seo settings export --file=./backup.json

# Import settings from a JSON file
wp es-seo settings import ./backup.json

# Bulk-set a meta field across a post type
wp es-seo meta set --post_type=page --field=noindex --value=1
wp es-seo meta set --post_type=page --field=noindex --value=1 --dry-run

# Clear a meta field across a post type
wp es-seo meta clear --post_type=post --field=noindex

# Ping search engines with the sitemap URL
wp es-seo sitemap ping
wp es-seo sitemap ping --post_type=post

# Force-regenerate the llms.txt transient
wp es-seo llms regenerate
```

---

## Template tokens

Use these tokens in title and description templates (Settings → Defaults):

| Token | Description |
|---|---|
| `%title%` | Post title or archive label |
| `%sitename%` | Site name (`get_bloginfo('name')`) |
| `%tagline%` | Site tagline |
| `%sep%` | Separator character (configured in General) |
| `%excerpt%` | Post excerpt (auto-generated from content if empty) |
| `%archive_title%` | Post type archive label or term name |
| `%author%` | Post author display name |
| `%date%` | Post publish date |
| `%modified_date%` | Post modified date |
| `%id%` | Post / term ID |
| `%parent_title%` | Parent post title (hierarchical post types) |
| `%primary_category%` | Primary category name |
| `%category%` | Comma-separated category names |
| `%tag%` | Comma-separated tag names |
| `%page%` | Current page number on paginated content |
| `%pagetotal%` | Total pages for paginated content |
| `%search_phrase%` | Search query string |
| `%current_year%` | Current year |

Add custom tokens via the `es_seo_template_tokens` filter.

---

## Filters

All filter names are prefixed with `es_seo_`. The full list:

| Filter | Description |
|---|---|
| `es_seo_titleTemplate` | Override resolved title before output |
| `es_seo_descriptionTemplate` | Override resolved description before output |
| `es_seo_templateTokens` | Add / override template tokens |
| `es_seo_templateTokensDateFormat` | Date format used in `%date%` / `%modified_date%` |
| `es_seo_canonical` | Override the canonical URL |
| `es_seo_robots` | Override the robots directive array |
| `es_seo_ogTags` | Modify Open Graph tag array |
| `es_seo_twitterTags` | Modify Twitter Card tag array |
| `es_seo_schemaGraph` | Modify the full JSON-LD `@graph` array |
| `es_seo_schemaContext` | Override the schema context object |
| `es_seo_webpageSchemaNode` | Modify the `WebPage` node |
| `es_seo_articleSchemaNode` | Modify the `Article`/`BlogPosting` node |
| `es_seo_siteRepresentationSchema` | Modify the `Organization`/`Person` node |
| `es_seo_breadcrumbSchema` | Modify the `BreadcrumbList` node |
| `es_seo_schemaNodePerson` | Modify the `Person` node |
| `es_seo_articleType` | Override the article schema type string |
| `es_seo_addArticleNode` | Control whether the Article node is added |
| `es_seo_addBreadcrumbNode` | Control whether BreadcrumbList is added |
| `es_seo_addRepresentationNode` | Control whether the site representation node is added |
| `es_seo_addAuthorNode` | Control whether the Author/Person node is added |
| `es_seo_supportedPostTypes` | Override the list of post types the plugin manages |
| `es_seo_sitemapPostTypes` | Override post types included in the sitemap |
| `es_seo_sitemapTaxonomies` | Override taxonomies included in the sitemap |
| `es_seo_llmSitemapEntry` | Modify individual LLM sitemap entries |
| `es_seo_imageSitemapEntry` | Modify individual image sitemap entries |
| `es_seo_hreflangAlternates` | Add hreflang link tags |
| `es_seo_aiBotRegistry` | Extend the AI bot registry |
| `es_seo_aiCrawlerRobotsTxt` | Modify robots.txt directives for AI crawlers |
| `es_seo_indexNowSubmit` | Modify the IndexNow submission payload |
| `es_seo_healthChecks` | Register custom health checks |
| `es_seo_webmasterVerificationTags` | Add webmaster verification meta tags |
| `es_seo_enableUsersSitemap` | Enable the users sitemap |
| `es_seo_speakableDefaultSelectors` | Override default Speakable CSS selectors |
| `es_seo_addInlineMentions` | Control inline entity mention detection |
| `es_seo_faqRender` | Modify FAQ block rendering for schema extraction |
| `es_seo_howtoRender` | Modify HowTo block rendering for schema extraction |

---

## License

MIT — see [LICENSE](LICENSE) for details.
