# Anikatsu (Anikami) – Anime Streaming & Discovery Platform

A PHP-based anime browsing and streaming site: discover trending/seasonal anime, search, read details, and watch episodes through multiple streaming providers, with user accounts, reviews, and comments.

---

## 🚀 Features

- **Discovery**: home page with trending, popular, seasonal, and new-season anime; genre and A-Z browsing; status pages (ongoing/completed); type pages (movies/TV series).
- **Anime details**: synopsis, genres, status, episode list, related recommendations.
- **Streaming**: multi-provider episode playback (`providers/streaming/*`) with HLS (`hls.js`) and iframe fallback, auto-next, and server failover via `files/js/player-manager.js`.
- **Search**: keyword search across the anime catalog.
- **Accounts**: registration, login/logout, email verification, forgot/reset password, remember-me sessions (`includes/auth.php`, `includes/auth_security.php`).
- **Comments & Reviews**: per-anime user comments and reviews (`_php/ak_comments.php`, `_php/ak_reviews.php`, `includes/reviews.php`).
- **Admin panel** (`admin/`): dashboard, analytics, audit log, activity feed, user management, comment/review moderation, site settings.
- **Sitemaps**: dynamic XML sitemaps per category (`sitemaps/`).
- **PWA**: manifest + service worker for installable/offline support.
- **Metadata providers**: Jikan (MyAnimeList) and AniList GraphQL, with automatic fallback between them when one is rate-limited or down.

---

## 📁 Project Structure

```
anikatsu/
├── api/                  # JSON endpoints: anime, episodes, watch, search, catalog, home,
│                         #   auth, user, comments, reviews, admin
├── admin/                # Admin panel (dashboard, analytics, users, comments, settings)
├── app/                  # Modern app layer: legacy bridge, API client helper
├── includes/             # bootstrap, db, auth, auth_security, security, cache, tracking,
│                         #   reviews, comments, user_data, admin_tools, analytics
├── providers/
│   ├── metadata/         # JikanProvider, AniListProvider
│   └── streaming/        # StreamProvider + per-source implementations (VidLink, VidNest, ...)
├── _php/                 # Shared template partials: header, footer, sidenav, sliders, ads
├── _deprecated/          # Retired code kept for reference
├── files/                # css/, js/, images/, slider/ — static frontend assets
├── data/                 # SQLite schema/DB (app.sqlite is local-only, gitignored)
├── sitemaps/             # Dynamic XML sitemap generators
├── genre/, status/, type/, sub-category/, latest/, az-list/   # Browse routes (id.php-style)
├── _config.php           # Site config, legacy API helpers, Jikan/AniList integration
├── index.php / home.php / anime.php / animeDetails.php / streaming.php / search.php
├── login.php / register.php / logout.php / forgot-password.php / reset-password.php
├── manifest.json / sw.js  # PWA
└── robots.txt / sitemap.php
```

---

## 🛠️ Key Technologies

- **PHP 8+** (procedural + modular service layer)
- **SQLite** (`data/app.sqlite`, via `includes/db.php`)
- **Jikan API** (MyAnimeList data) and **AniList GraphQL API** (metadata, with cross-fallback)
- **hls.js** + **Plyr** / native iframe embeds for video playback
- **Vanilla JavaScript** (player manager, search, comments, sliders)
- **Disqus** integration for legacy comment threads

---

## 🚦 Local Setup (XAMPP)

1. **Clone & place in the web root**
   ```bash
   git clone https://github.com/VaradCoder/anikami.git anikatsu
   # put it in C:\xampp\htdocs\anikatsu
   ```
2. **Database** — `includes/db.php` auto-creates the SQLite schema (`data/app.sqlite`) and bootstraps a default admin user on first run. **Change the default admin password immediately** after first login.
3. **Run** — open `http://localhost/anikatsu/`.

Requires **PHP 8+** with `pdo_sqlite` and `curl` extensions enabled.

---

## 🔒 Security Notes

- The default admin account created on first DB migration uses a placeholder password — rotate it immediately in any non-local deployment.
- Streaming sources are loaded via iframe/HLS from third-party providers; treat `providers/streaming/*` as the trust boundary when adding new sources.
- Never commit `data/app.sqlite` (real user accounts/session data) — already excluded via `.gitignore`.

---

## 🧑‍💻 Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you'd like to change.

---

## 📄 License

MIT

---

## 💡 Credits

Built with ❤️ by Varad Bhole.

Portfolio: https://varad09.netlify.app/
GitHub: https://github.com/VaradCoder
