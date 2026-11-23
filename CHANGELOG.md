# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Studio page for generating images from a prompt, size, quality and up to four reference image URLs.
- Live price estimate with a token-by-token breakdown, calculated on the server from Pixelforge's published pricing.
- Background submission to Pixelforge with idempotency keys, `Retry-After`-aware backoff, and separate retry budgets for waiting on a concurrency slot and for errors.
- Webhook endpoint with HMAC signature verification, replay protection, deduplication by event id and out-of-order safety.
- Follow-up checks that ask Pixelforge directly when a generation's webhook is overdue, without a scheduler, so the app can scale to zero.
- Generation history with live status, estimated and charged amounts, images, and plain-language failure reasons.
- Exact billing in nanodollars: Pixelforge cost from each generation's own rates plus a 25% markup, charged once on success; failed generations are free.
- Credit balances with simulated purchases, a transaction history, and holds for generations in progress.
- Admin profit report per UTC day and per user, checked against Pixelforge's usage report.
- Support for Laravel Cloud's managed queue.
- `users:grant-admin`, and `generations:reconcile` to check every unfinished generation at once after an outage.
