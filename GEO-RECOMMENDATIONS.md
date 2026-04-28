# Eightshift SEO — GEO (Generative Engine Optimization) Recommendations

A research-backed shortlist of features that would extend the plugin from classic SEO into **GEO / AEO** territory — i.e. helping content get **cited, retrieved, and recommended** by AI search engines (ChatGPT, Perplexity, Google AI Overviews, Gemini, Claude, Bing Copilot, You, Mistral, etc.).

Format mirrors the existing `FEATURE-RECOMMENDATIONS.md`: each item lists **What / Why / Scope (S/M/L) / Architectural notes**, grouped by tier, ending with a suggested roadmap and open questions.

> **Guiding constraints (re-confirmed against current code, 2026-04-27):**
> - Plugin already ships through Phase 5: webmaster verification, site representation schema, post-list columns, hreflang adapters, IndexNow, image sitemap, health dashboard, WP-CLI (`wp es-seo`).
> - Target stays **fresh Eightshift projects**, not a Yoast-parity layer.
> - No duplication of `eightshift-utils` Schema.org baseline or `eightshift-ui-kit` breadcrumb rendering.
> - Storage: separate `es_seo_*` postmeta keys, single JSON blob for settings, Gutenberg-only editor UI, native `register_setting`/`register_meta` with `show_in_rest`.
> - Existing extension points: filters listed in `src/Blocks/manifest.json` (`filters.*`), `Schema/`, `Head/`, `Sitemap/`, `Multilingual/`, `Health/`.

---

## Why GEO matters now (one-paragraph context)

AI answer engines now mediate a growing share of search. Google AI Overviews appear on ~30%+ of queries; ChatGPT alone serves ~800M weekly users; Perplexity, Copilot, and Gemini all surface direct answers with citations. Crucially, AI engines parse **sections, not pages** — they retrieve self-contained chunks, prefer freshness, weight definition-first openings, and reward content backed by statistics, expert quotes, and clear sourcing (Princeton's GEO study measured +30–41% visibility lift from these). Classic SEO is the foundation; GEO adds (a) **machine-readable surfaces** for LLM crawlers, (b) **explicit citation hooks** in markup and content, and (c) **bot-level governance** (which AI vendors may train on / cite your content). The plugin already has the right plumbing — most additions slot into existing services.

---

## 🎯 Tier 1 — High leverage, low cost (recommended first)

These items are mostly server-side emissions and settings — they fit into existing services in `src/Head/`, `src/Schema/`, and `src/Sitemap/` with little new infrastructure.

### G1.1 AI crawler governance (robots.txt + per-bot policy)
**What:** A "AI crawlers" settings section letting admins set per-bot policy (Allow / Disallow / Crawl-delay) for the well-known AI agents:
- **OpenAI:** `GPTBot`, `OAI-SearchBot`, `ChatGPT-User`
- **Anthropic:** `ClaudeBot`, `Claude-SearchBot`, `Claude-User`
- **Google:** `Google-Extended` (Gemini/AI Overviews training), `GoogleOther`
- **Perplexity:** `PerplexityBot`, `Perplexity-User`
- **Others:** `Applebot-Extended`, `CCBot`, `Bytespider`, `cohere-ai`, `Meta-ExternalAgent`, `MistralAI-User`, `xAI-Bot`, `DuckAssistBot`, `Ai2Bot`, `YouBot`
Output goes through the `robots_txt` filter (which we already use for the sitemap entry) and a settings UI similar to the existing webmaster tab.
**Why:** The bot landscape now distinguishes **training**, **search-indexing**, and **on-demand retrieval** bots — blanket-blocking is no longer the right answer. Fresh Eightshift sites need a one-screen governance UI that ships sensible defaults (e.g. allow retrieval/search bots, block training-only bots) and stays current as new agents appear.
**Scope:** **S/M** — extend `Sitemap/SitemapHooks.php`'s `robots_txt` handler; new settings group; ship a maintained bot registry as a PHP constant array (versioned in `Config.php`).
**Notes:** Filterable via a new `es_seo_ai_bot_registry` so projects can add custom bots. Surface a "last verified" date in the UI so users know the registry is current.

### G1.2 `llms.txt` generator
**What:** Auto-generated `/llms.txt` (Markdown) at site root listing canonical URLs grouped by section (Pages, Posts, Documentation, etc.), each with a one-line description. Settings:
- enable/disable
- choose post types to include (multi-select, defaults: pages + main blog post type)
- intro/outro free-text fields
- exclude individual posts via existing "Hide from sitemap" toggle (or new `es_seo_llms_excluded`)

Optionally also emit `/llms-full.txt` (concatenated post content as Markdown) — gated behind a separate toggle since it's heavier.
**Why:** `llms.txt` (Jeremy Howard, Sept 2024) is not yet a ratified standard, but Mintlify-hosted docs, Anthropic, Cursor, and a growing list of dev-focused properties ship it. Cost to ship is tiny; downside if it never takes off is one extra route. Cost if it *does* take off and we don't have it: missed visibility in agentic tools.
**Scope:** **M** — new `src/Llms/LlmsTxt.php` service, a rewrite rule, a cached generator (transient + invalidate on save_post), and Markdown conversion (via `wp_strip_all_tags` + a small block-to-markdown helper, or pull `league/html-to-markdown` only if needed). Add `wp es-seo llms regenerate` CLI command.
**Notes:** Keep the file under ~50KB to stay LLM-context-friendly; truncate listings beyond a configurable limit and link out.

### G1.3 Markdown rendering of singular content (`?format=md` / `.md` suffix)
**What:** Allow any singular post URL to be served as clean Markdown by appending `.md` (e.g. `/blog/my-post.md`) or via a `?format=md` query var. Strips chrome (header, footer, sidebar), converts the post body to Markdown, and prepends YAML front-matter (title, description, datePublished, dateModified, author, canonical).
**Why:** Major LLM crawlers and agentic tools have shown a strong preference for plain-text/Markdown variants over rendered HTML — they're cheaper to tokenize and less noisy. Sites like Anthropic's docs already do this. This is the single most concrete "AI-native" surface a content site can offer.
**Scope:** **M** — rewrite rule + `template_redirect` handler + Markdown converter. Reuse the same converter as G1.2.
**Notes:** Cache aggressively (`wp_cache_*` or transient by post modified time). Ensure `Vary: Accept` if we ever support content negotiation (out of scope for first cut).

### G1.4 Author entity (`Person`) schema with E-E-A-T fields
**What:** Per-user (in `wp-admin → Profile`) fields:
- short bio (already in WP)
- credentials / job title
- organization (defaults to site organization)
- `sameAs` URLs (LinkedIn, Wikipedia, ORCID, GitHub, X, Mastodon)
- email (optional public)

Plus a new `Schema/AuthorSchema.php` that emits a `Person` node referenced by Article schema's `author` (and contributes to the global `@graph` once G2.1 lands). On author archives, emit standalone `Person` JSON-LD.
**Why:** AI engines explicitly weight **E-E-A-T** signals when deciding who to cite. A linked Wikipedia/LinkedIn/ORCID via `sameAs` is the cheapest possible signal that "this person is a real expert." Currently the plugin emits Org/Person at the site level only — author-level entities are missing.
**Scope:** **M** — `user_meta` registration with `show_in_rest`, profile screen extension, schema service. Coordinate with `eightshift-utils` so we don't double-emit `Person` (G2.1 graph aggregation handles this).

### G1.5 TL;DR / Direct Answer field per post
**What:** Optional postmeta `es_seo_tldr` (40–80 word soft cap, validated in the editor) shown in:
- the SEO sidebar panel (with character counter and length helper)
- `<meta name="description">` *if* user hasn't filled regular description (configurable precedence)
- a new `speakable` (Schema.org) annotation on the Article node
- the front of `/llms-full.txt` content for that post (G1.2)
**Why:** AI engines lean heavily on the first 150–200 tokens (the "definition-first" effect documented in the CMU GEO paper). Authors rarely write opening paragraphs that double as standalone answers. A separate field forces the discipline and gives us a clean structured handle.
**Scope:** **S** — one `register_meta` + one Gutenberg control + emission hook.

### G1.6 Citation / Sources field (per post)
**What:** A repeater postmeta storing an ordered list of `{label, url, publisher?, datePublished?}` references. Two outputs:
1. Optional rendered "Sources" list at the bottom of the post (template tag + filter so themes can place it).
2. Schema.org `citation` array on the Article node.
**Why:** Princeton GEO study showed citations boost AI visibility ~30%; ChatGPT and Perplexity disproportionately cite content that itself cites primary sources. We're giving authors a structured field instead of relying on inline links.
**Scope:** **M** — repeater postmeta is the trickier part (REST shape + Gutenberg control). Reuse existing custom-meta scaffolding pattern.
**Notes:** Validate URLs server-side; mark `nofollow` is *not* applied (these are explicit citations the author chose).

### G1.7 AI-bot-aware robots meta directives (`noai` / `noimageai`)
**What:** Two new postmeta toggles + site-wide defaults:
- `es_seo_noai` → emits `<meta name="robots" content="noai">` and the per-bot variants `<meta name="GPTBot" content="noai">` for the major training agents.
- `es_seo_noimageai` → same but `noimageai`.

Plus an Advanced-tab setting "Default AI training policy: Allow / Block" applied as a fallback.
**Why:** Lots of orgs have content-licensing constraints (interview transcripts, paid research, regulated industry content) that shouldn't be in training data even if SEO indexing is desired. Currently the plugin can't express that; users have to hand-edit headers.
**Scope:** **S** — two new meta fields + one `Head/` emitter, integrates with existing `RobotsDirectives.php`.
**Notes:** `noai`/`noimageai` are honored by Anthropic, Microsoft Bing, and a few others; ignored by some. We document compliance status per bot in the UI tooltip.

### G1.8 Article schema enrichment (author, dates, sections, wordCount)
**What:** Audit and extend whatever the plugin (or `eightshift-utils`) currently emits as Article JSON-LD to ensure it includes:
- `author` → `Person` node (from G1.4) with `@id`
- `publisher` → `Organization` node (existing site representation)
- `datePublished`, `dateModified` (already there in WP core, confirm exposed)
- `headline` (≤110 chars, validated)
- `articleSection` (primary category)
- `wordCount`
- `inLanguage`
- `image` (featured image with width/height/alt)
- `mainEntityOfPage`
**Why:** These are the exact fields LLM citation pipelines look for to generate "According to [Site], …" attribution. Missing fields = lost citations.
**Scope:** **S/M** — depends on whether utils already emits Article (verify first). If yes, contribute via filter only. If no, we own it.
**Notes:** Add `wp es-seo schema validate <url>` CLI to diff against schema.org's required/recommended.

### G1.9 Speakable (`SpeakableSpecification`) selectors
**What:** Auto-emit `speakable` schema on Article nodes pointing at: TL;DR field (G1.5) + the first H2 + the first list. Override-able via a postmeta CSS selector list.
**Why:** Voice-first answer surfaces (Google Assistant, Alexa) and AI-Overview audio readouts use this. Trivial to emit; meaningful when present.
**Scope:** **S** — one schema service contribution.

### G1.10 GEO-aware health checks
**What:** Add to the existing `Health/` dashboard:
- "llms.txt reachable?" (if G1.2 enabled)
- "Site representation has logo + sameAs?"
- "Default AI bot policy set?" (G1.1)
- "Author profiles have bio + sameAs?" (G1.4) — count of users missing it
- "Posts missing TL;DR?" (top 10)
**Why:** The dashboard already exists; adding GEO checks is mostly a few new `HealthCheckInterface` classes. Surfaces the value of every new feature in one place.
**Scope:** **S** — one class per check.

---

## 🧱 Tier 2 — Structural / architectural additions

Larger or coordinated changes that unlock new capability.

### G2.1 Unified `@graph` schema aggregator
**What:** Introduce a single `Schema/GraphEmitter` service that:
- collects schema contributions via a `es_seo_schema_graph` filter (one entry per node)
- de-duplicates by `@id`
- emits **one consolidated `<script type="application/ld+json">{ "@context", "@graph": […] }</script>`** in `wp_head`
- coordinates with `eightshift-utils` (which currently emits its own) — utils opts into the filter; the emitter fires only once

All existing schema services (BreadcrumbList, SiteRepresentation, Author, Article enrichment, FAQ, HowTo) become contributors instead of independent emitters.
**Why:** Today every schema service emits its own `<script>` block — fine for Google but noisy for LLMs and prone to duplicate `@id`s when multiple plugins overlap. A single graph is the canonical pattern (Yoast, RankMath, Schema App all converged on this) and is a prerequisite for clean cross-references between Article → Person → Organization.
**Scope:** **L** — refactor of the schema layer + tight coordination with `eightshift-utils`. Worth doing before piling on more schema types.
**Notes:** Open question for the user: should this plugin own the canonical graph and utils contribute, or vice versa?

### G2.2 FAQ + HowTo postmeta-driven schema (no blocks required)
**What:** Two repeater postmeta fields on supported post types:
- `es_seo_faq` → array of `{question, answer}` → emits `FAQPage` node, optionally rendered to the front via a template tag.
- `es_seo_howto` → ordered list of `{name, text, image?}` → emits `HowTo` node.

Editing happens in the SEO sidebar (no new blocks). Front-end rendering is *opt-in*, theme-controlled.
**Why:** FAQ schema is the highest-citation-rate schema type for ChatGPT/Perplexity per multiple 2026 studies; HowTo is second. Doing it as postmeta (rather than blocks) is far less work, doesn't pollute the block library, and matches the plugin's "lightweight, no shortcodes" stance. The original Tier 2 in `FEATURE-RECOMMENDATIONS.md` proposed a block pack — postmeta is the GEO-first version.
**Scope:** **M**.
**Notes:** Validate length and formatting; reject HTML in answers.

### G2.3 Entity linking (`sameAs`, `about`, `mentions`)
**What:** A "Linked entities" sidebar control letting authors attach Wikidata/Wikipedia URLs to a post; emitted as `about` (primary entity) and `mentions` (secondary) on the Article node, plus `sameAs` on the global `Organization`/`Person`.
Optional: a small UI that suggests entities by querying Wikidata's API on demand (server-side proxy to avoid CORS).
**Why:** Entity recognition (linking text to a known knowledge-graph node) is *the* mechanism by which AI engines reconcile a brand or topic across the web. Wikidata-linking is the cheapest E-E-A-T move after author bios.
**Scope:** **M/L** — UI surface is moderate; the optional autocomplete proxy is a small REST route.

### G2.4 Pre-publish GEO checks (Gutenberg pre-publish panel)
**What:** Extend the existing pre-publish keyphrase panel with GEO-specific checks:
- ✅ First 200 chars contain a definitional sentence (regex: `is/are/means/refers to`)
- ✅ At least one statistic (regex: `\b\d+(\.\d+)?%|\b(in|by)\s\d{4}`)
- ✅ At least one citation (sources field G1.6 non-empty OR ≥1 outbound link to non-self domain)
- ✅ TL;DR field filled (G1.5)
- ✅ Headings hierarchy (no skipped levels)
- ✅ At least one image with alt text
- ⚠️ Reading time vs. content depth heuristic
- ✅ FAQ section present (if FAQ postmeta filled OR detected from H3 question patterns)
**Why:** The Princeton GEO study quantified each of these as a citation-rate lift. Surfacing them at publish time is the single biggest behavioural nudge available.
**Scope:** **M** — pure JS, runs against the editor's own state, no requests.

### G2.5 AI bot traffic insights (read-only)
**What:** Lightweight access-log sampler: a small middleware (PHP, gated by capability) that records aggregate hit counts per AI user-agent (GPTBot, ClaudeBot, etc.) into a custom table, displayed as a 30-day chart in the Health dashboard. No per-request logging — only counters.
**Why:** Operators want to see *whether AI bots are actually crawling* before deciding bot policy (G1.1). Without data, governance is guessing.
**Scope:** **M** — single DB table + WP-Cron rotation + dashboard widget.
**Notes:** Privacy review needed. Counters only, no IPs, no URLs (or hashed URLs). Document clearly in privacy notice.

### G2.6 Content freshness / decay signals
**What:**
- **Per post:** "Reviewed on YYYY-MM-DD" postmeta separate from `dateModified`, emitted as `dateReviewed` on Article schema.
- **Site-wide setting:** "Auto-update `dateModified` only when content body changes" (vs. WP default which bumps on any save).
- **Health dashboard:** "Top 20 most outdated published posts" (by `dateModified`).
**Why:** AI-surfaced URLs are 25.7% fresher on average than traditional-search URLs (Backlinko 2026). Sites that systematically refresh their corner-stone content get cited more. The "reviewed" date is a softer signal that lets operators bump freshness without faking edits.
**Scope:** **S/M**.

### G2.7 Statistics & quotation Gutenberg blocks
**What:** Two minimal blocks:
- **Statistic:** `{value, label, source, sourceUrl, datePublished}` — renders a styled callout, emits a `Claim` schema fragment.
- **Expert Quote:** `{quote, author, authorTitle, authorUrl}` — renders a blockquote with cite, emits a `Quotation` schema fragment.
**Why:** These are exactly the two structural features the GEO research identified as highest citation lift (+30% / +41%). Giving authors a Block they can reach for nudges the entire content team toward GEO-friendly patterns.
**Scope:** **M** — two blocks; clear scope boundary (no FAQ block — that's covered as postmeta in G2.2).
**Notes:** This is the *only* place we add blocks, and they're authoring helpers, not content blocks. Aligns with "no schema-block pack" decision.

### G2.8 Glossary / definition mode for the first paragraph
**What:** Optional sidebar toggle "Use definition-first opener" — when on, prompts the author to start the post with a `<p class="es-seo-definition">[Term] is/are/refers to …</p>`. The block (or paragraph attribute) is detected and emitted as `DefinedTerm` schema or as `description` on a `DefinedTermSet` if multiple are present.
**Why:** CMU GEO paper showed pages with definition-first openings get a ~17.3% citation lift; this turns it into a deliberate, observable pattern instead of a hope.
**Scope:** **S/M**.

### G2.9 AI-targeted XML sitemap variant
**What:** A second sitemap (`/llm-sitemap.xml` or extension on the existing) that includes only:
- canonical singular URLs
- their `.md` variants (G1.3)
- their `dateModified` and (if set) `dateReviewed`
- a `priority` weighted by inbound internal links (a cheap heuristic)
Surface it in the AI-bot section so users can point GPTBot/ClaudeBot/PerplexityBot at it explicitly via robots.txt directive.
**Why:** Some retrieval pipelines now read sitemaps to seed crawl. A curated, AI-shaped sitemap (no taxonomy listings, no thin pages, includes Markdown variants) tells them exactly what's worth indexing.
**Scope:** **M**.

---

## 🔬 Tier 3 — Specialist / experimental

Lower priority, narrower fit, or speculative.

### G3.1 Auto-generated post summaries via local AI
**What:** Optional integration that calls a configured AI provider (OpenAI, Anthropic, Ollama) to draft a TL;DR (G1.5) when the field is empty. User approves before save.
**Why:** Convenient. Ethically and operationally fraught — needs API keys, costs money, may produce hallucinations. Likely a companion plugin rather than core.
**Scope:** **L**. **Recommend deferring** or splitting into a separate plugin.

### G3.2 ClaimReview / FactCheck schema
**What:** Postmeta + UI for fact-check organizations / news sites to publish `ClaimReview`-eligible posts.
**Why:** Specialist; only relevant for fact-checking publishers. Could be a separate companion plugin.
**Scope:** **M**. Defer.

### G3.3 Hreflang for Markdown variants
**What:** Extend the existing hreflang adapters so the `.md` variants (G1.3) link to translated `.md` siblings.
**Why:** Logical extension if both G1.3 and existing hreflang shipped. Tiny code; only meaningful for multilingual sites running G1.3.
**Scope:** **S**. Bundle with G1.3 if multilingual is detected.

### G3.4 RAG-friendly anchor IDs
**What:** Auto-generate stable, slug-style `id` attributes on every H2/H3/H4 (if the theme/blocks don't already), so AI citations can deep-link to specific sections (`/post#section-name`).
**Why:** Several AI engines (Perplexity especially) cite section anchors when available — improves the user-facing citation experience and slightly boosts crawlability.
**Scope:** **S** — one filter on `the_content` adding IDs where missing. Watch out for AMP / theme conflicts.

### G3.5 Brand mention monitoring
**What:** Periodic check (cron) that queries selected AI engines via their public APIs ("What is [site name]?") and records whether the site is cited / what URL was cited. Surface as a dashboard widget.
**Why:** Closes the GEO loop — you can see if your work is actually being cited. But: needs API keys, costs money, results are inherently noisy.
**Scope:** **L**. Likely a companion plugin / commercial-tier feature; out of scope for core.

### G3.6 Per-section robots / AI directives
**What:** Block-level `data-noai` attribute or wrapper block letting authors mark *parts* of a post as off-limits to AI training (interview transcripts, quoted research, etc.). Emitted as a section comment + as `<meta name="GPTBot:section">` directives where supported.
**Why:** Some AI bot specs have section-level directive support on their roadmap. Today: emerging. **Recommend tracking** rather than building.
**Scope:** **L**. Defer until the spec stabilizes.

### G3.7 Synonym / alternate-name index for entity matching
**What:** Per-entity (Organization, Product, Person) list of alternate names / known abbreviations / common misspellings, emitted as `alternateName` on the Org/Person node.
**Why:** Helps AI engines disambiguate brand mentions across the web ("Eightshift" vs "Eightshift SEO" vs "ES SEO"). Trivial.
**Scope:** **S**.

### G3.8 Mentions feed / content sharing endpoint
**What:** A WebSub or PubSubHubbub feed of new/updated posts, in addition to RSS. Some AI ingestion pipelines prefer push over poll.
**Why:** Niche; only a few AI vendors support push. Probably defer.
**Scope:** **M**. Defer.

### G3.9 Per-post "AI training license" declaration
**What:** A dropdown declaring the post's AI-training license (e.g. "All rights reserved", "CC-BY-4.0", "TDM Reservation per EU Article 4"). Output as a `usageRights` claim in schema and a TDMRep header where applicable.
**Why:** EU's Article 4 Text and Data Mining opt-out is starting to be honored by some bots. Useful for EU-regulated publishers; specialist otherwise.
**Scope:** **M**. Consider for v2 of G1.7.

---

## 🚫 Out of scope (explicit)

To keep the plugin lean, even good GEO ideas should stay out if they cross our lines:

- **Real-time AI rewriting / generation** of content (G3.1 noted as deferred / companion plugin material)
- **Brand-mention monitoring as a service** (G3.5) — needs API budget, recurring infra, billing
- **A "GEO score" black-box metric** — we ship checklists with cited reasons (G2.4), not opinionated scores
- **Yoast/RankMath GEO setting migration** — same migration-out-of-scope rule as before
- **Schema.org block pack** — already deferred in `FEATURE-RECOMMENDATIONS.md`; postmeta-driven FAQ/HowTo (G2.2) replaces the need

---

## 📌 Suggested roadmap

Pragmatic bundling. Each phase is a shippable release.

### Phase 6 — "GEO foundations" (Tier 1 core)
- G1.1 AI crawler governance
- G1.2 `llms.txt` generator
- G1.4 Author entity / E-E-A-T
- G1.5 TL;DR field
- G1.7 `noai` / `noimageai` directives
- G1.8 Article schema enrichment (verify against utils, add what's missing)
- G1.10 GEO health checks (incremental)

*Rationale:* Highest perceived "this site is GEO-ready" lift, all server-side, no editor refactors. Can ship without G2.1 because most of these don't need a unified graph yet.

### Phase 7 — "Citable content" (mid-sized authoring lift)
- G2.1 Unified `@graph` aggregator *(prerequisite for cleanly stacking everything else)*
- G1.3 Markdown rendering (`.md`)
- G1.6 Citations / Sources field
- G1.9 Speakable selectors
- G2.2 FAQ + HowTo postmeta schema
- G2.4 Pre-publish GEO checks

*Rationale:* The graph aggregator is the architectural anchor; everything else in this phase contributes to it.

### Phase 8 — "Authoring nudges & freshness"
- G2.3 Entity linking (Wikidata)
- G2.6 Freshness signals
- G2.7 Statistic + Quotation blocks
- G2.8 Definition-first mode
- G2.9 AI sitemap variant

### Phase 9 — "Visibility & telemetry"
- G2.5 AI bot traffic insights
- G3.4 RAG-friendly anchor IDs
- G3.7 Alternate names

### Companion / future (decide separately)
- G3.1 AI-assisted summaries (likely a companion plugin)
- G3.5 Brand mention monitoring (companion / SaaS-tier)
- G3.2 ClaimReview, G3.6 Section directives, G3.8 WebSub, G3.9 Training licenses — wait for ecosystem

---

## ❓ Open questions (please answer before we draft the implementation plan)

1. **Graph ownership:** Do we make `eightshift-seo` the **canonical owner** of the JSON-LD `@graph`, with `eightshift-utils` contributing via `es_seo_schema_graph` filter? This is the biggest cross-plugin question and determines whether G2.1 is Phase 7 or moves to Phase 6 as a prerequisite.
2. **`llms.txt` scope:** Ship just `/llms.txt` (index), or also `/llms-full.txt` (concatenated content)? Latter doubles storage / cache concerns.
3. **Markdown variant URL pattern:** `.md` suffix (`/post.md`) or query var (`/post?format=md`)? `.md` is cleaner and what most adopters use, but rewrite rules can collide with site-specific permalinks. Preference?
4. **AI-bot policy default:** When G1.1 ships, what should the *out-of-the-box* policy be? Allow all retrieval/search bots + block training-only bots? Or fully open (current default)?
5. **AI-assisted authoring (G3.1):** In core (with bring-your-own-key), in a companion plugin, or out entirely? Affects scope of Phase 6+.
6. **Bot-traffic logging (G2.5):** Comfortable with us writing a small custom DB table, or stay strictly options/postmeta-only?
7. **Entity autocomplete (G2.3):** Add a Wikidata-backed picker (one outbound API call from admin), or just a free-text URL field?
8. **Statistic/Quote blocks (G2.7):** OK to add two new blocks despite the "no schema block pack" decision, given these are *authoring* blocks rather than schema-only blocks?
9. **Pre-publish checks (G2.4):** Bundle into the existing pre-publish panel, or behind a per-site toggle? They're opinionated and some teams may push back.
10. **Anything you want to scope *out* up front?** E.g. you may want to defer entity linking entirely if Wikidata feels too far afield from the plugin's identity.

---

## Sources

Research drawn from (April 2026):

- Backlinko — *Generative Engine Optimization (GEO): How to Win in AI Search* — [backlinko.com/generative-engine-optimization-geo](https://backlinko.com/generative-engine-optimization-geo)
- Search Engine Land — *Mastering generative engine optimization in 2026* — [searchengineland.com](https://searchengineland.com/mastering-generative-engine-optimization-in-2026-full-guide-469142)
- Princeton/CMU — *GEO: Generative Engine Optimization (Aggarwal et al.)* — [arxiv.org/pdf/2311.09735](https://arxiv.org/pdf/2311.09735)
- arxiv — *Structural Feature Engineering for GEO* — [arxiv.org/html/2603.29979v1](https://arxiv.org/html/2603.29979v1)
- llmstxt.org — *The /llms.txt file* — [llmstxt.org](https://llmstxt.org/)
- aeo.press — *The State of llms.txt in 2026* — [aeo.press](https://www.aeo.press/ai/the-state-of-llms-txt-in-2026)
- Cloudflare — *From Googlebot to GPTBot: who's crawling your site* — [blog.cloudflare.com](https://blog.cloudflare.com/from-googlebot-to-gptbot-whos-crawling-your-site-in-2025/)
- Search Engine Journal — *Anthropic's Claude Bots robots.txt* — [searchenginejournal.com](https://www.searchenginejournal.com/anthropics-claude-bots-make-robots-txt-decisions-more-granular/568253/)
- Frase.io — *FAQ Schema for GEO/AEO* — [frase.io](https://www.frase.io/blog/faq-schema-ai-search-geo-aeo)
- upGrowth — *Schema Markup for GEO: 9 Citation Patterns* — [upgrowth.in](https://upgrowth.in/schema-markup-geo-9-patterns-ai-citations-2026/)
- Stackmatix — *Structured Data AI Search: Schema Markup Guide 2026* — [stackmatix.com](https://www.stackmatix.com/blog/structured-data-ai-search)
