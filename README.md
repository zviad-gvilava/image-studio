# Image Studio

A Laravel, Vue and Inertia app for generating images with the [Pixelforge](https://pixelforge.promptedit.dev/docs) API. Users see what an image can cost before they create it, pay Pixelforge's exact cost plus 25% once it's ready, and never pay for a failure.

**Live app:** https://image-studio-production-xz9i1b.laravel.cloud (register to try it; credit purchases are simulated)

## What it does

- **Studio.** Write a prompt, pick a size, a quality and up to four reference image URLs, and see the price range update as you type, with a breakdown of every token type.
- **Live history.** Each generation shows its status (submitting, queued, processing, succeeded, failed), the estimate next to what was actually charged, the image, and in plain words why it failed or what it is waiting for. The page refreshes itself while anything is in progress.
- **Credits.** A balance in US dollars, simulated "Buy credits" packs, and a full history of purchases and charges. Generating is blocked when the worst-case price isn't covered.
- **Profit report** (admins). What users were charged, what Pixelforge charged us and the profit, per UTC day and per user, with every day checked against Pixelforge's own `GET /v1/usage`.

## How money is handled

| Rule                                                                                                                                                                                                                                               | Where                                     |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| Every amount is a whole number of **nanodollars** ($0.000000001), the precision Pixelforge reports `cost_usd` in. No floats anywhere: decimals are parsed and multiplied with `brick/math`.                                                        | `app/Billing/Money.php`                   |
| Pixelforge's cost is the generation's token usage times **the rates returned with that generation**, which are fixed when it is accepted, not whatever `/v1/pricing` says today.                                                                   | `app/Pixelforge/Rates.php`                |
| The user pays cost × 1.25. This is exact at current rates; if a future rate ever left a fraction of a nanodollar, it is rounded up so we never charge less than cost plus markup.                                                                  | `app/Billing/Markup.php`                  |
| The estimate is a range: text tokens are within ±15% of `ceil(characters / 4)`, image and output tokens are exact, and reasoning tokens fall in a range set by quality. Pricing comes from `/v1/pricing` (cached for 5 minutes), never hard-coded. | `app/Billing/PriceEstimator.php`          |
| While a generation runs, its worst-case price is held against the balance, so several parallel requests can't overspend it.                                                                                                                        | `app/Billing/CreditLedger.php`            |
| A generation is charged **exactly once**: status only moves forward, the charge is written in the same database transaction under a row lock, and `credit_transactions.generation_id` is unique.                                                   | `app/Generations/GenerationLifecycle.php` |
| Failed generations are recorded with a cost and price of $0.                                                                                                                                                                                       | same                                      |
| The report groups by the UTC day Pixelforge **completed** a generation, because that is the day it bills, so each day can be compared with `/v1/usage`.                                                                                            | `app/Reports/ProfitReport.php`            |

## How it copes with Pixelforge

| Situation                                                                       | What happens                                                                                                                                                                       |
| ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `429 concurrency_limit_exceeded` (only 3 at a time)                             | The generation waits its turn, retrying for up to about 10 minutes. The user sees "Waiting for a free slot".                                                                       |
| `429 rate_limit_exceeded`, `503 overloaded`, a 5xx or a network error           | Retries respecting `Retry-After`, backing off further each time with jitter, then gives up after 5 attempts (about 30 seconds). The generation fails for free with a clear reason. |
| `400 content_policy_violation`, `429 daily_limit_exceeded` and other rejections | Fails at once, for free, with a reason the user can act on.                                                                                                                        |
| Retrying a request that may have reached Pixelforge                             | Every attempt sends the same `Idempotency-Key` (our generation's id), so a retry can never create or bill a second generation.                                                     |
| A webhook arrives before the create request returns                             | Our record exists before Pixelforge hears about it, and its id travels in `metadata.generation_id`, so the event is matched anyway.                                                |
| The same event delivered twice                                                  | Event ids are unique in `webhook_events`; recording the event and applying it share one transaction, so a redelivery is ignored but a failed one is retried.                       |
| Events out of order                                                             | Status only moves forward (`queued → processing → succeeded/failed`), and final states never change.                                                                               |
| Webhooks lost                                                                   | A minute after Pixelforge accepts a generation, a queued check asks Pixelforge directly if no webhook has settled it, and keeps checking with growing gaps until it finishes.      |
| We gave up, but Pixelforge had accepted it after all                            | Pixelforge's word wins for anything it accepted (it bills us for it), so the generation is settled normally.                                                                       |
| Forged or replayed webhooks                                                     | HMAC-SHA256 over the raw body, compared in constant time, and rejected if the timestamp is more than 5 minutes off.                                                                |

## Where to look

```
app/Pixelforge/          API client, typed responses, error codes, webhook signature check
app/Billing/             Money, Markup, PriceEstimator, CreditLedger
app/Generations/         StartGeneration, GenerationLifecycle (the only place status and cost change), HandlePixelforgeEvent
app/Jobs/SubmitGeneration.php                 Submission and retry policy
app/Jobs/CheckOnGeneration.php                Safety net for lost webhooks
app/Console/Commands/ReconcileGenerations.php Manual sweep of unfinished generations, e.g. after an outage
app/Reports/ProfitReport.php                  Admin report
resources/js/pages/Studio.vue                 Generate page and live history
```

## Running it locally

Requires PHP 8.4+, Composer and Node 22.18+.

```bash
composer setup
```

Then add your Pixelforge API key to `.env` (`PIXELFORGE_API_KEY`) and start everything (web server, queue worker, Vite and logs):

```bash
composer run dev
```

`php artisan db:seed` creates `test@example.com` (password `password`) as an admin with $10 of credits. To make another user an admin:

```bash
php artisan users:grant-admin someone@example.com
```

**Webhooks locally.** Pixelforge can't reach `localhost`, so expose the app with a tunnel (Cloudflare Tunnel, ngrok or Expose), set the API key's webhook URL to `https://<tunnel>/api/webhooks/pixelforge`, and put its signing secret in `PIXELFORGE_WEBHOOK_SECRET`. Without a tunnel, the follow-up checks ask Pixelforge directly, so statuses still update within a minute or two.

## Tests

```bash
composer test
```

Runs Pint, PHPStan (level 7) and the PHPUnit suite. The money tests use the worked examples from the Pixelforge docs (`$0.373875000` for the documented worst case, `$0.169080000` for the documented usage day), and the webhook tests deliver correctly signed, duplicated, out-of-order and early events.

## Deploying (Laravel Cloud)

1. Create a Postgres database for the app.
2. Add a managed queue for background jobs. Laravel Cloud's runs on Amazon SQS, which is why `aws/aws-sdk-php` is installed. No scheduler is needed.
3. Set `PIXELFORGE_API_KEY` to a key used only by this deployment, and `PIXELFORGE_WEBHOOK_SECRET` to that key's signing secret.
4. Set the key's webhook URL to `https://<your-app>/api/webhooks/pixelforge`.
5. Register, then run `php artisan users:grant-admin <your email>` to see the profit report.

Using one key per environment matters: `/v1/usage` reports per key, so the deployed app's numbers can match it exactly.

## Decisions worth discussing

- **Lost webhooks are caught per generation, not by a cron.** A scheduler running every minute would keep a scale-to-zero app and its database awake around the clock. Checking each generation a minute after it is accepted, and again with growing gaps while it runs, gives the same safety net, and nothing runs while nothing is in flight.
- **Webhooks are applied inline, not queued.** The work is a couple of indexed writes in one transaction, well inside Pixelforge's 10-second limit, and it makes deduplication and the state change atomic. If processing became slow (for example copying images to storage), it would move to a job.
- **Pricing lives only on the server.** The browser asks for an estimate as the form changes, so there is a single implementation of the pricing rules and the number shown is the number charged against.
- **Users are charged on completion, not upfront.** Holding the worst case covers the risk without refunds or adjustments.
- **Images are not copied.** The brief allows links to expire after 24 hours; the history then says so instead of showing a broken image.
- **Deleting an account keeps its cost records** (the user reference is nulled), so totals keep matching Pixelforge.
- **SQLite locally, Postgres in production.** The row locks that serialise spending only take effect on Postgres; SQLite serialises writes anyway.

## What I'd do next

- Copy finished images to object storage so they outlive Pixelforge's links.
- Push status updates over websockets instead of polling.
- Alert when a day stops matching Pixelforge's usage report.
- Fair queueing per user when the shared concurrency limit is the bottleneck.
