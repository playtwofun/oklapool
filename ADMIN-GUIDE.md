# Admin Panel Guide

Login: `yoursite.com/?action=admin` — password is `ADMIN_PASSWORD` in `index.php`.

## Site Settings

| Setting | What it does |
|---|---|
| **Site Name** | Updates nav brand, footer and browser tab title everywhere |
| **Logo** | Upload PNG/JPG/SVG/WebP/GIF (max 2 MB) — replaces the nav logo instantly |
| **Telegram / X** | Handle (`@name`) or full URL — links appear in the footer |
| **Contract Address** | Mainnet pool contract — change anytime, no file editing |
| **Theme** | 3 totally different looks — click a card for instant live preview |

Everything saves to `settings.json` on the server — no database.

## Live pool data

The panel reads directly from the blockchain:

- TVL, LP shares, unique depositors, deposit/withdraw counts
- Latest pool events (from explorer API, RPC fallback)
- Contract owner, paused status, latest block, RPC latency

## Themes

1. **Aurora** — light, periwinkle, soft glass, animated shader hero
2. **Midnight** — dark terminal, mint accent, centered hero
3. **Press** — brutalist light gray, black rules, orange accent, uppercase display type

## Security notes

- The contract owner can only `pause()` / `unpause()` / transfer ownership.
  User funds can **never** be moved by the owner.
- Change `ADMIN_PASSWORD` before production.
- `settings.json` and `logo.*` must stay out of git (already in `.gitignore`).
