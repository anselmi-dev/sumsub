# anselmi-dev/sumsub

Laravel package for [Sumsub](https://sumsub.com) KYC (Know Your Customer) identity verification.

Handles applicant creation, SDK token generation and webhook processing out of the box, while staying fully replaceable via contracts.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.3 |
| Laravel | ^11.0 \| ^12.0 \| ^13.0 |

---

## Installation

### Local development (path repository)

Add the path repository to your `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../packages/anselmi-dev/sumsub",
        "options": { "symlink": true }
    }
]
```

Then require the package:

```bash
composer require anselmi-dev/sumsub:@dev
```

### From Packagist (when published)

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

## Usage

### 1. Create an applicant

```php
use AnselmiDev\Sumsub\Facades\Sumsub;

$applicant = Sumsub::createApplicant(auth()->user());
// Returns a SumsubApplicant model
```

You can pass a custom level name as the second argument:

```php
$applicant = Sumsub::createApplicant(auth()->user(), 'advanced-kyc-level');
```

If the user already has an applicant, the existing record is returned (idempotent).

---

### 2. Generate an SDK token (for the frontend widget)

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

---

### 3. Check the applicant status

```php
use AnselmiDev\Sumsub\Models\SumsubApplicant;

$applicant = SumsubApplicant::where('user_id', auth()->id())->latest()->first();

$applicant->isPending();   // true | false
$applicant->isCompleted(); // true | false
$applicant->isApproved();  // review_answer === 'GREEN'
$applicant->isRejected();  // review_answer === 'RED'
```

---

### 4. Refresh applicant data from Sumsub

Syncs the local record with the latest status from the Sumsub API:

```php
$applicant = Sumsub::refreshApplicant($applicant);
```

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

Sumsub::createApplicant(Authenticatable $user, ?string $levelName = null): SumsubApplicant
Sumsub::generateSdkToken(Authenticatable $user, ?string $levelName = null): array
Sumsub::refreshApplicant(SumsubApplicant $applicant): SumsubApplicant
```

---

## License

MIT
