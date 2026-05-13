# anselmi-dev/sumsub

Laravel package for [Sumsub](https://sumsub.com) KYC (Know Your Customer) identity verification.

Handles applicant creation, SDK token generation and webhook processing out of the box, while staying fully replaceable via contracts.

Supports both **single-tenant** and **SaaS / multi-tenant** deployments.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.3 |
| Laravel | ^11.0 \| ^12.0 \| ^13.0 |

---

## Installation

```bash
composer require anselmi-dev/sumsub
```

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=sumsub-config
```

This creates `config/sumsub.php`. Then add the following variables to your `.env`:

```env
SUMSUB_APP_TOKEN=your-app-token
SUMSUB_SECRET_KEY=your-secret-key
SUMSUB_BASE_URL=https://api.sumsub.com
SUMSUB_WEBHOOK_SECRET=your-webhook-secret
SUMSUB_DEFAULT_LEVEL=basic-kyc-level
SUMSUB_WEBHOOK_ROUTE=webhooks/sumsub
SUMSUB_QUEUE_CONNECTION=redis
SUMSUB_QUEUE_NAME=default

# SaaS mode (optional, default: false)
SUMSUB_SAAS_MODE=false
```

You can find your **App Token** and **Secret Key** in the Sumsub dashboard under **Developer Tools → App Tokens**.

---

## Database

Run the migration to create the `sumsub_applicants` table:

```bash
php artisan migrate
```

Or publish the migration first if you need to customise it:

```bash
php artisan vendor:publish --tag=sumsub-migrations
php artisan migrate
```

---

## Single-tenant usage (default)

No extra setup required. Use the `Sumsub` facade directly:

### Create an applicant

```php
use AnselmiDev\Sumsub\Facades\Sumsub;

$applicant = Sumsub::createApplicant(auth()->user());
```

You can pass a custom level name as the second argument:

```php
$applicant = Sumsub::createApplicant(auth()->user(), 'advanced-kyc-level');
```

If the user already has an applicant, the existing record is returned (idempotent).

### Generate an SDK token (for the frontend widget)

```php
$token = Sumsub::generateSdkToken(auth()->user());

// Returns:
// [
//     'token'  => 'eyJhbGci...',
//     'userId' => '5cb56e8e...',
// ]
```

Pass this token to the [Sumsub Web SDK](https://developers.sumsub.com/web-sdk/) on the frontend:

```js
const snsWebSdkInstance = snsWebSdk
    .init(token, () => getNewAccessToken())
    .withConf({ lang: 'es' })
    .build();

snsWebSdkInstance.launch('#sumsub-websdk-container');
```

### Check the applicant status

```php
use AnselmiDev\Sumsub\Models\SumsubApplicant;

$applicant = SumsubApplicant::where('user_id', auth()->id())->latest()->first();

$applicant->isPending();   // true | false
$applicant->isCompleted(); // true | false
$applicant->isApproved();  // review_answer === 'GREEN'
$applicant->isRejected();  // review_answer === 'RED'
```

### Refresh applicant data from Sumsub

Syncs the local record with the latest status from the Sumsub API:

```php
$applicant = Sumsub::refreshApplicant($applicant);
```

---

## SaaS / multi-tenant mode

Enable SaaS mode when your application serves multiple tenants and you need to isolate their KYC data.

### 1. Enable the flag

```env
SUMSUB_SAAS_MODE=true
```

Or in `config/sumsub.php`:

```php
'saas_mode' => true,
```

When `saas_mode` is `true`, the package automatically:

- Stores a `tenant_id` on every `sumsub_applicants` row.
- Scopes all repository queries to the current tenant — tenants never see each other's applicants.
- Namespaces the `externalUserId` sent to Sumsub as `{tenant_id}:{user_id}` to prevent collisions across tenants sharing the same Sumsub project.

### 2. Use `forTenant()` to switch context

Call `Sumsub::forTenant()` at the start of any operation to set the active tenant:

```php
use AnselmiDev\Sumsub\Facades\Sumsub;

// All tenants share one Sumsub project (data isolation only)
$applicant = Sumsub::forTenant($tenant->id)
                   ->createApplicant($user);

// Each tenant has its own Sumsub project (separate credentials)
$applicant = Sumsub::forTenant(
                tenantId:  $tenant->id,
                appToken:  $tenant->sumsub_app_token,
                secretKey: $tenant->sumsub_secret_key,
             )->createApplicant($user);
```

`forTenant()` returns a new `SumsubService` instance — it does not mutate the singleton, so it is safe to call in concurrent requests.

### 3. Typical SaaS middleware pattern

Resolve the tenant once per request and share the scoped service:

```php
// app/Http/Middleware/SetSumsubTenant.php

use AnselmiDev\Sumsub\Services\SumsubService;
use Closure;
use Illuminate\Http\Request;

class SetSumsubTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $request->user()?->tenant;

        if ($tenant) {
            app()->instance(
                SumsubService::class,
                app(SumsubService::class)->forTenant(
                    tenantId:  (string) $tenant->id,
                    appToken:  $tenant->sumsub_app_token,   // null = use global config
                    secretKey: $tenant->sumsub_secret_key,  // null = use global config
                )
            );
        }

        return $next($request);
    }
}
```

Register the middleware in your HTTP kernel or route group, and then use the facade normally — it will always resolve the tenant-scoped instance:

```php
$applicant = Sumsub::createApplicant(auth()->user());
```

### 4. Webhook routing in SaaS mode

**Shared Sumsub project (one webhook URL for all tenants)**

No extra setup needed. All webhooks arrive at the same endpoint and are matched to the correct tenant via the `applicant_id` already stored in `sumsub_applicants.tenant_id`.

**Per-tenant Sumsub project (one webhook URL per tenant)**

Register tenant-specific routes in your host app, forwarding each to the package controller with the resolved tenant:

```php
// routes/api.php

Route::post('webhooks/sumsub/{tenant}', function (Request $request, Tenant $tenant) {
    // Temporarily override webhook_secret for this tenant's Sumsub project
    config(['sumsub.webhook_secret' => $tenant->sumsub_webhook_secret]);

    return app(\AnselmiDev\Sumsub\Http\Webhooks\SumsubWebhookController::class)($request);
});
```

---

## Comparison: single-tenant vs SaaS

| Feature | Single-tenant | SaaS |
|---|---|---|
| `SUMSUB_SAAS_MODE` | `false` (default) | `true` |
| `tenant_id` column | always `null` | populated automatically |
| Repository scope | no scope | scoped to `tenant_id` |
| `externalUserId` in Sumsub | `"{user_id}"` | `"{tenant_id}:{user_id}"` |
| Credentials | one global set | per-tenant via `forTenant()` |
| Webhook URL | single | shared or per-tenant |
| Breaking change | none | none (nullable column) |

---

## Webhooks

The package registers a route automatically:

```
POST /webhooks/sumsub
```

Point your Sumsub webhook to this URL (Sumsub dashboard → **Webhooks**).

The controller validates the HMAC-SHA256 signature using `SUMSUB_WEBHOOK_SECRET` and dispatches a `ProcessSumsubWebhook` job.

> **Note:** Add this route to the CSRF exception list if you are using `VerifyCsrfToken` middleware.
>
> ```php
> // app/Http/Middleware/VerifyCsrfToken.php
> protected $except = [
>     'webhooks/sumsub',
> ];
> ```

You can customise both the URI and the route name:

```env
SUMSUB_WEBHOOK_ROUTE=webhooks/sumsub
SUMSUB_WEBHOOK_ROUTE_NAME=sumsub.webhook
```

---

## Events

Listen to these events in your `AppServiceProvider` or `EventServiceProvider`:

| Event | When it fires |
|---|---|
| `ApplicantCreated` | After a new applicant is created in Sumsub |
| `ApplicantStatusChanged` | Every time a webhook is received and the local record is updated |
| `ApplicantReviewed` | When a final review result (`GREEN` / `RED` / `RETRY`) arrives |

### Example: update user KYC status on approval

```php
// app/Providers/AppServiceProvider.php

use AnselmiDev\Sumsub\Events\ApplicantReviewed;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(ApplicantReviewed::class, function (ApplicantReviewed $event) {
        if ($event->isApproved()) {
            $event->applicant->user->update(['kyc_verified_at' => now()]);
        }

        if ($event->isRejected()) {
            // notify user, update status, etc.
        }
    });
}
```

The `ApplicantReviewed` event exposes:

```php
$event->applicant;       // SumsubApplicant model
$event->reviewAnswer;    // 'GREEN' | 'RED' | 'RETRY'
$event->webhookPayload;  // full raw Sumsub payload (array)

$event->isApproved();
$event->isRejected();
$event->needsRetry();
```

---

## Replacing the repository (advanced)

By default the package stores data in its own `sumsub_applicants` table via `SumsubApplicantRepository`.

If you want to use your own models (e.g. an existing `KycDocumentStatus`), bind a custom repository in your `AppServiceProvider`:

```php
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use App\Repositories\MyCustomKycRepository;

public function register(): void
{
    $this->app->bind(KycRepositoryInterface::class, MyCustomKycRepository::class);
}
```

Your class must implement `KycRepositoryInterface`:

```php
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\Models\SumsubApplicant;

class MyCustomKycRepository implements KycRepositoryInterface
{
    public function findByUserId(int|string $userId): ?SumsubApplicant { ... }
    public function findByApplicantId(string $applicantId): ?SumsubApplicant { ... }
    public function create(array $data): SumsubApplicant { ... }
    public function updateStatus(string $applicantId, array $data): SumsubApplicant { ... }
}
```

---

## Complete KYC flow

```
1. User clicks "Start verification"
2. Backend: Sumsub::createApplicant($user)         → creates applicant in Sumsub, saves SumsubApplicant
3. Backend: Sumsub::generateSdkToken($user)        → gets a short-lived frontend token
4. Frontend: initialises Sumsub Web SDK with token
5. User uploads documents & selfie directly to Sumsub
6. Sumsub sends POST /webhooks/sumsub
7. Package validates signature, dispatches ProcessSumsubWebhook job
8. Job updates SumsubApplicant, fires ApplicantReviewed
9. Your listener updates user.kyc_verified_at / notifies user
```

---

## Facade reference

```php
use AnselmiDev\Sumsub\Facades\Sumsub;

// SaaS helpers
Sumsub::forTenant(string $tenantId, ?string $appToken = null, ?string $secretKey = null): SumsubService

// Core methods
Sumsub::createApplicant(Authenticatable $user, ?string $levelName = null): SumsubApplicant
Sumsub::generateSdkToken(Authenticatable $user, ?string $levelName = null): array
Sumsub::refreshApplicant(SumsubApplicant $applicant): SumsubApplicant
```

---

## Local development

### Installing via path repository

If you are working on the package alongside your app, use a Composer path repository instead of Packagist:

```json
"repositories": [
    {
        "type": "path",
        "url": "../packages/anselmi-dev/sumsub",
        "options": { "symlink": true }
    }
]
```

```bash
composer require anselmi-dev/sumsub:@dev
```

Composer creates a symlink, so any change to the package is reflected immediately in your app without re-running `composer update`.

### Simulating webhooks

Simulate a webhook event without hitting the real Sumsub API:

```bash
# Simulate a GREEN (approved) webhook
php artisan sumsub:simulate-webhook --answer=GREEN --sync

# Simulate a RED (rejected) for a specific applicant
php artisan sumsub:simulate-webhook 5cb56e8e0a975a35f333cb83 --answer=RED
```

---

## License

MIT
