# Anikatsu — Project-Wide Technical Audit (Deep, No-Implementation)

> Scope: PHP app + `api/*`, providers/stream resolution (`app/*`), streaming pages/player logic (`streaming.php`, `files/js/*`, `files/css/*`), routing/URL generation, security, caching, DB (SQLite), JS runtime, CSS responsiveness, SEO, accessibility, performance.
>
> Method: Read key entry points and core modules, plus watch/search/player integration points. File-level citations are based on what’s present in this repository.
>
---

## Executive Summary

- **Overall architecture rating:** **5/10** (legacy procedural + newer modular app layer mixed; schema contracts inconsistent between “legacy” and “normalized” payloads)
- **Stability rating:** **4/10** (provider scraping fragility + mixed payload schema + caching of upstream failures)
- **Security rating:** **3/10** (iframe/stream embed hardening insufficient; security headers/CSP absent/unclear; streaming sources not allowlisted; XSS surface via inconsistent output escaping)
- **Scalability rating:** **5/10** (SQLite works, but indexing for analytics/trending/continue/history queries is incomplete; caching exists but failure caching likely)
- **Technical debt rating:** **7/10** (tight coupling to `_config.php`, duplicate normalization logic, provider interface gaps)
- **Performance rating:** **4/10** (many upstream calls at request time; large inline CSS/JS on pages; autoplay/auto-next + player failover may amplify traffic)

---

## Key Findings by Severity

---

# CRITICAL

## C1) Unsafe streaming embed / iframe hardening missing
- **Issue:** Playback is loaded via iframes and/or direct stream URLs resolved from scrapers. There’s no strict allowlist validation on iframe hosts or stream URL schemas.
- **Affected files:**
  - `streaming.php`
  - `api/watch_v2.php`
  - `api/watch.php`
  - `app/_legacy_bridge.php`
  - `files/js/player-manager.js`
- **Root cause:** Normalized server list includes arbitrary `playbackUrl` and embeds it in an iframe (`sandbox` is present but includes `allow-same-origin` in `streaming.php`), without allowlisting.
- **Impact:** High likelihood of security compromise (malicious iframe content, token exfiltration, unwanted navigation, abuse of iframe sandbox escape vectors depending on browser/version; mixed-content issues if HTTP sneaks in).
- **Recommended fix:**
  1. Enforce **allowlist** of permitted playback hostnames for iframe sources and HLS sources.
  2. Enforce **HTTPS-only** for all resolved playback URLs.
  3. Remove `allow-same-origin` unless strictly required; prefer `sandbox="allow-scripts"` without same-origin.
  4. Add/verify **CSP**: restrict `frame-src` and `media-src` to the allowlisted domains.
  5. Validate `playbackUrl` server-side before returning `api/watch_v2.php` payload.

---

## C2) Injection/XSS surface from inconsistent output escaping + HTML injection in UI
a.k.a. “search/render + player guide HTML”
- **Issue:** Multiple templates render content derived from remote APIs/providers. While many variables use `app_e()` or `htmlspecialchars()`, some paths render raw strings or create HTML with `.innerHTML`.
- **Affected files:**
  - `home.php` (JS search results rendering uses `innerHTML`, mitigates via local `escapeHtml`, but remote data shapes are inconsistent)
  - `files/js/player-manager.js` (uses `this.sourceGuideEl.innerHTML = parts.join('')`)
- **Root cause:** UI mixes template escaping and dynamic HTML assembly.
- **Impact:** If any field contains unexpected HTML (e.g., `server.name`, `animeTitle`, etc.), it may become XSS.
- **Recommended fix:**
  - Prefer DOM APIs (`textContent`) instead of `innerHTML` for guide/search results.
  - Ensure all fields used in `innerHTML` are escaped and never contain untrusted HTML.

---

## C3) Stream resolution can cache failures/invalid payloads (availability failure)
- **Issue:** Caching exists (`includes/cache.php`) and the legacy HTTP/API layer likely caches responses. If the resolver caches “error/empty” upstream results, the site can serve empty lists/servers until expiry.
- **Affected files:**
  - `includes/cache.php`
  - `_config.php` (legacy fetch/caching behavior)
  - `api/home.php`, `api/catalog.php`, `api/search.php`, `api/watch_v2.php`
- **Root cause:** Cache strategy stores JSON decoded payloads without strict schema validation and without explicit “don’t cache failures”.
- **Impact:** Large portion of the UI can become blank after upstream provider/API changes.
- **Recommended fix:**
  - Only cache responses that pass schema validation.
  - Never cache transient errors; cache negative results with short TTL and explicit “error” metadata.

---

---

# HIGH

## H1) Hardcoded default admin credentials (DB bootstrap)
- **Issue:** Admin account is auto-created in `app_db_migrate()` with static email/user/pass.
- **Affected files:** `includes/db.php`
- **Root cause:** Bootstrapping inserts admin user if none exists.
- **Impact:** Admin takeover if the DB file is accessible, reset, or deployed to shared environments.
- **Recommended fix:** Remove auto-creation in production; require first-run setup via environment variables or one-time setup mechanism.

---

## H2) CSRF enforcement correctness depends on endpoint coverage
- **Issue:** CSRF helpers exist and `api/user.php` and `api/admin.php` validate CSRF on POST. But other state-changing endpoints and/or legacy endpoints may not.
- **Affected files:**
  - `includes/security.php`
  - `api/user.php`, `api/admin.php` (confirmed to validate)
  - Potentially other `api/*.php` and legacy routes
- **Root cause:** Consistency across endpoints is uncertain; architecture doesn’t show a unified middleware.
- **Impact:** If any endpoint misses CSRF validation, account actions become forgeable.
- **Recommended fix:** Add request-validation wrapper/middleware for all `api/*.php` that checks method + CSRF for all state-changing actions.

---

## H3) Provider abstraction incomplete / normalization contracts inconsistent
- **Issue:** Legacy functions and modular app layer return different payload keys (`anime_id`/`img_url` vs `animeId`/`animeImg`, etc.).
- **Affected files:**
  - `_config.php`
  - `home.php` (normalizes multiple variants)
  - `api/search.php` (returns legacy API data arrays)
  - `api/*` watch/title/episode payloads
- **Root cause:** Multiple schema versions co-exist without a strict contract.
- **Impact:** Runtime warnings/empty data cards; higher “blank UI” probability when providers change.
- **Recommended fix:** Define canonical internal DTO schema and enforce it at provider boundary. Add automated tests for normalization.

---

## H4) SQLite indexing gaps for analytics/trending/history
- **Issue:** Schema defines tables and some UNIQUE constraints, but no explicit indexes for common query patterns (trending metrics/events; continue_watching ordering; watch_history ordering).
- **Affected files:** `includes/db.php`
- **Root cause:** Only UNIQUE constraints; relying on implicit indexes.
- **Impact:** Degraded performance as metrics_events grows.
- **Recommended fix:** Add explicit indexes for:
  - `metrics_events(event_type, created_at)` and/or `metrics_events(created_at, anime_id, event_type)`
  - `watch_history(user_id, watched_at)`
  - `continue_watching(user_id, updated_at)`

---

---

# MEDIUM

## M1) Error handling/observability gaps in tracking and APIs
- **Issue:** Tracking swallows all exceptions.
- **Affected files:** `includes/tracking.php`
- **Impact:** Debugging provider/API failures becomes slower; operational blind spots.
- **Recommended fix:** Log minimal metadata to PHP error log while still not failing UX.

---

## M2) Public APIs not rate-limited (search/home/catalog)
- **Issue:** Public JSON endpoints call legacy upstream sources on demand; no visible throttling.
- **Affected files:**
  - `api/search.php`, `api/home.php`, `api/catalog.php`, `api/anime.php`
- **Root cause:** Missing rate limiting/circuit breakers at API layer.
- **Impact:** Susceptible to request storms; provider bans; higher latency.
- **Recommended fix:** Add rate limiting (IP/session/user), caching, and circuit breakers per upstream.

---

## M3) Autoplay/auto-next and failover may cause extra upstream hits
- **Issue:** Player can auto-next after episode end; failover timer switches servers (and may trigger additional iframe loads).
- **Affected files:** `files/js/player-manager.js`, `streaming.php`
- **Impact:** More traffic to upstream stream providers; can amplify rate limits.
- **Recommended fix:** Add server health weighting, exponential backoff per playback URL, and ensure auto-next uses already-resolved normalized episode server list if possible.

---

# LOW

## L1) SEO duplication / incomplete canonicalization
- **Issue:** Meta tags vary by page; canonical tags not visible.
- **Affected files:** `index.php`, `home.php`, `anime.php`, `search.php`, `animeDetails.php`
- **Impact:** SEO and social sharing inconsistencies.
- **Recommended fix:** Add `<link rel="canonical">`, consistent meta template, OpenGraph/Twitter normalization.

---

## L2) CSS architecture: huge monolithic stylesheet + global overrides
- **Issue:** `files/css/style.css` is very large and includes many global selectors and overrides; high risk of regressions.
- **Affected files:** `files/css/style.css`, plus smaller CSS files.
- **Impact:** Maintainability + responsiveness regressions + z-index collisions.
- **Recommended fix:** Split into component styles, reduce global overrides, adopt CSS variables and consistent naming.

---

## L3) Accessibility gaps around interactive controls
- **Issue:** Many UI elements are div/span-based with click handlers; ARIA is inconsistent.
- **Affected files:** templates (header/search/player controls) and JS.
- **Impact:** Reduced keyboard/screen-reader usability.
- **Recommended fix:** Ensure semantic elements/buttons, keyboard handlers, and ARIA labels.

---

## System-Wide Architecture Notes

- **Two-layer architecture:**
  1. “Legacy” procedural scraping/mapping in `_config.php` and legacy endpoints.
  2. “Modern” modular app layer: `app/bootstrap.php`, `app/Services/StreamSourceResolver.php`, `app/Providers/Servers/*`.

- **Current coupling:** Most UI pages call internal `api/*.php`, which in turn call legacy providers via `legacy_api()` and `legacy_get_*` helpers.

- **Streaming pipeline:**
  - `anime.php` -> UI uses `app_api_get('/api/anime.php')` and `app_api_get('/api/episodes.php')`.
  - `streaming.php` -> UI fetches `api/watch_v2.php` with `episodeId`.
  - `api/watch_v2.php` -> uses `StreamSourceResolver` + provider(s) and returns `servers[]` with `playbackUrl`.
  - `files/js/player-manager.js` -> loads playback: HLS -> `hls.js` + `Plyr`; else -> iframe.

- **Main architectural debt:** no strict boundary between provider output and UI input; schema compatibility is handled ad-hoc in templates.

---

## Top 20 Most Important Fixes (Priority Order; No Implementation)

1. **Allowlist + validate iframe playback domains** (security, stream embedding) 
   - `api/watch_v2.php`, `streaming.php`, `app/Services/*`
2. **Remove `allow-same-origin` from stream iframe sandbox (or justify & harden)** 
   - `streaming.php`
3. **Add/verify CSP headers for `frame-src` and `media-src`** 
   - global config / entry points
4. **Stop caching error/empty upstream payloads**; add schema validation before cache write 
   - `_config.php`, `includes/cache.php`
5. **Canonical DTO schema and strict normalization at provider boundary** 
   - `_config.php`, `api/*.php`, UI pages
6. **Unify CSRF validation middleware across all state-changing `api/*.php`** 
   - API layer
7. **Remove hardcoded default admin credentials auto-bootstrap** 
   - `includes/db.php`
8. **Add explicit DB indexes for trending/analytics/history queries** 
   - `includes/db.php`
9. **Add rate limiting + circuit breakers for public endpoints** 
   - `api/search.php`, `api/home.php`, `api/catalog.php`
10. **Replace `innerHTML` usage with safe DOM APIs** in player guide / any other dynamic rendering 
   - `files/js/player-manager.js`, potentially `home.php`
11. **Prevent untrusted URL injection into iframe/src** (scheme allowlist; reject javascript/data) 
   - `api/watch_v2.php`, `api/watch.php`
12. **Add observability logging for upstream/provider failures** (don’t swallow all) 
   - `includes/tracking.php` and API wrappers
13. **Failover algorithm improvements**: health scoring, avoid immediate retries loop 
   - `files/js/player-manager.js`
14. **Ensure search results payload schema consistency** (keys/structure) 
   - `api/search.php`, `_config.php`, UI `home.php` and `search.php`
15. **Optimize “home” API fanout**: reduce number of upstream calls per request 
   - `api/home.php`, `api/catalog.php`
16. **Add request timeouts to upstream fetches and graceful degradation** 
   - `_config.php` legacy fetch layer
17. **Accessibility improvements for interactive div/span elements** 
   - templates + JS
18. **Canonical tags / SEO template consolidation** 
   - main templates
19. **Split monolithic CSS and remove conflicting global overrides** 
   - `files/css/style.css`
20. **Add automated regression tests for normalization contracts** 
   - CI/test harness for payload shapes

---

## Conclusion

The dominant production risks are **stream embed safety** and **schema contract inconsistency**, which cascade into reliability and UX failures. Next to that, operational hardening is needed: **rate limiting, caching discipline (no failure caching), and security headers (CSP, iframe controls)**.

