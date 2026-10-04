# HoodFi — Liquidity Protocol on Robinhood Chain

Non-custodial ETH liquidity pool website. **No SQL, no framework** — a single `index.php`
plus one Solidity contract. Every number on the site is read directly from Robinhood Chain.

## Files

| File | Purpose |
|---|---|
| `index.php` | The entire website: wallet connect, live pool dashboard, deposit/withdraw, admin panel |
| `contracts/LiquidityPool.sol` | The pool smart contract (deploy once via Remix) |
| `docs/DEPLOYMENT.md` | Step-by-step hosting + contract deploy guide |
| `docs/ADMIN-GUIDE.md` | Admin panel, branding and theme guide |
| `LICENSE` | MIT |


## Homepage sections

- Live stats strip (TVL, LP shares, unique depositors, latest block)
- How-it-works cards + honesty notes
- Pool calculator widget (preview shares before depositing) with the public share math
- Trust feature cards (non-custodial, fully on-chain, withdraw anytime, verifiable live)
- Recent pool activity feed — real deposit/withdraw events read from the chain
- FAQ accordion + call-to-action band

## How it works

- Users deposit ETH → contract mints LP shares (HF-LP) proportional to their pool share
- Withdraw anytime → shares burn, ETH returns in the same transaction
- Owner can only pause/unpause — **owner can never move user funds**

## Setup

1. **Hosting:** upload `index.php` to any PHP hosting (7.4+). Keep the folder writable so
   `settings.json` and uploaded logos can be saved.
2. **Contract:** deploy `contracts/LiquidityPool.sol` via [Remix](https://remix.ethereum.org)
   on Robinhood Chain — Mainnet chain ID `4663` (RPC `https://rpc.mainnet.chain.robinhood.com`)
   or Testnet `46630` (RPC `https://rpc.testnet.chain.robinhood.com`).
3. **Admin panel:** open `yoursite.com/?action=admin` (default password is set at the top of
   `index.php` — change `ADMIN_PASSWORD` before going live). From the panel you can change:
   - Contract address (mainnet + testnet) — no file editing needed
   - Site name, logo upload, Telegram / X handles
   - 5 completely different themes (Aurora / Midnight / Press / Phosphor / Maison)

## Notes

- `settings.json` and `logo.*` are created on the server automatically — do not commit them.
- All pool data (TVL, shares, depositors, events) is read on-chain via public RPC + explorer API.
