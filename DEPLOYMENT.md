# Deployment Guide

## 1. Website (index.php)

1. Upload `index.php` to any PHP 7.4+ hosting (cPanel / shared hosting works).
2. Make sure the folder is **writable** — the site creates:
   - `settings.json` — site name, logo, socials, theme, contract address (set from admin panel)
   - `logo.<ext>` — uploaded logo
3. Open the site — it runs on **Robinhood Chain Mainnet (Chain ID 4663)** only.

### Requirements

| Item | Detail |
|---|---|
| PHP | 7.4 or newer (8.x recommended) |
| SQL | **Not needed** — zero database |
| Writable folder | For `settings.json` + logo uploads |
| HTTPS | Recommended (wallets prefer secure origins) |

## 2. Smart contract

1. Open [Remix](https://remix.ethereum.org).
2. Create `LiquidityPool.sol` and paste the contract from `contracts/LiquidityPool.sol`
   (or upload the file directly — do NOT copy-paste partially).
3. Compile with Solidity `0.8.20+` (0.8.34 works).
4. Deploy with **Injected Provider — MetaMask** on Robinhood Chain Mainnet (4663).
   - RPC: `https://rpc.mainnet.chain.robinhood.com`
   - Explorer: `https://robinhoodchain.blockscout.com`
5. Copy the deployed contract address.

## 3. Connect site to contract

- Easiest: **Admin panel → Site Settings → Contract Address** → paste → Save.
- Or hardcode in `index.php` → `CONFIG.CONTRACTS` → `4663: "0x…"`.
- Admin panel value always overrides the hardcoded one.

## 4. Admin access

- URL: `yoursite.com/?action=admin`
- Password is set at the top of `index.php` in `ADMIN_PASSWORD` — **change it before going live**.
