# anselmi-dev/sumsub

Paquete Laravel base para integrar la API de [Sumsub](https://sumsub.com) (KYC / verificación de identidad).

Proporciona lo necesario para operar con Sumsub: cliente HTTP firmado, creación de applicants, tokens del SDK, webhooks y eventos.

> **¿Usas Livewire?** Instala solo [`anselmi-dev/livewire-sumsub`](../livewire-sumsub) — incluye este paquete como dependencia y centraliza credenciales + widget en una sola instalación.

---

## Requisitos

| Dependencia | Versión |
|---|---|
| PHP | ^8.3 |
| Laravel | ^11.0 \| ^12.0 \| ^13.0 |
| guzzlehttp/guzzle | ^7.0 |

---

## Instalación

```bash
composer require anselmi-dev/sumsub
```

El service provider se registra automáticamente vía Laravel package discovery.

---

## Configuración

Publica el archivo de configuración:

```bash
php artisan vendor:publish --tag=sumsub-config
```

Esto crea `config/sumsub.php`. Añade las variables en tu `.env`:

```env
SUMSUB_APP_TOKEN=your-app-token
SUMSUB_SECRET_KEY=your-secret-key
SUMSUB_BASE_URL=https://api.sumsub.com
SUMSUB_WEBHOOK_SECRET=your-webhook-secret
SUMSUB_DEFAULT_LEVEL=basic-kyc-level
SUMSUB_WEBHOOK_ROUTE=webhooks/sumsub
SUMSUB_WEBHOOK_ROUTE_NAME=sumsub.webhook
```

Los jobs de webhook usan la cola por defecto de Laravel (`QUEUE_CONNECTION` en `config/queue.php`).

El **App Token** y la **Secret Key** están en el panel de Sumsub: **Developer Tools → App Tokens**.

---

## Base de datos

El paquete carga las migraciones automáticamente. Ejecuta:

```bash
php artisan migrate
```

Si necesitas personalizar la migración antes de ejecutarla:

```bash
php artisan vendor:publish --tag=sumsub-migrations
php artisan migrate
```

Tabla creada: `sumsub_applicants` (`user_id`, `applicant_id`, `level_name`, `review_status`, `review_answer`, `raw_data`).

---

## Uso

### Crear un applicant

```php
use AnselmiDev\Sumsub\Facades\Sumsub;

$applicant = Sumsub::createApplicant(auth()->user());
```

Nivel de verificación opcional:

```php
$applicant = Sumsub::createApplicant(auth()->user(), 'advanced-kyc-level');
```

Si el usuario ya tiene un applicant local, se devuelve el existente (idempotente). Si Sumsub responde **409** (applicant ya existe en Sumsub pero no en tu BD), el paquete lo recupera por `externalUserId` y lo sincroniza.

### Generar token del SDK (frontend)

```php
$result = Sumsub::generateSdkToken(auth()->user());

// [
//     'token'  => 'eyJhbGci...',
//     'userId' => '5cb56e8e...',
// ]
```

Pásalo al [Sumsub Web SDK](https://developers.sumsub.com/web-sdk/):

```js
const snsWebSdkInstance = snsWebSdk
    .init(token, () => getNewAccessToken())
    .withConf({ lang: 'es' })
    .build();

snsWebSdkInstance.launch('#sumsub-websdk-container');
```

### Consultar estado

```php
use AnselmiDev\Sumsub\Models\SumsubApplicant;

$applicant = SumsubApplicant::where('user_id', auth()->id())->latest()->first();

$applicant->isPending();
$applicant->isCompleted();
$applicant->isApproved();  // review_answer === 'GREEN'
$applicant->isRejected();  // review_answer === 'RED'

// Estado simplificado (pending | progress | completed | cancelled)
$applicant->status->value;
$applicant->status->label();
$applicant->status->color();
```

### Refrescar desde la API de Sumsub

```php
$applicant = Sumsub::refreshApplicant($applicant);
```

---

## UI en la aplicación host

Este paquete **no incluye** componentes Livewire ni vistas Blade. Expone `KycVerificationState` para mapear el dominio al estado del widget:

| Origen | Método |
|---|---|
| `SumsubApplicant` persistido | `KycVerificationState::fromApplicant($applicant)` o `$applicant->verificationState()` |
| `SumsubStatus` | `KycVerificationState::fromSumsubStatus($status)` |
| Evento SDK (`reviewAnswer`) | `KycVerificationState::fromReviewAnswer('GREEN')` |

Estados del enum: `idle`, `loading`, `sdk_ready`, `progress`, `completed`, `cancelled`, `error`.

Ejemplo en Livewire:

```php
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\DataTypes\KycVerificationState;
use AnselmiDev\Sumsub\Services\SumsubService;

public string $state = KycVerificationState::Idle->value;

public function mount(KycRepositoryInterface $repository): void
{
    $applicant = $repository->findByUserId(auth()->id());
    if ($applicant) {
        $this->state = $applicant->verificationState()->value;
    }
}

public function startVerification(SumsubService $sumsub): void
{
    $this->state = KycVerificationState::Loading->value;
    $result = $sumsub->generateSdkToken(auth()->user());
    $this->state = KycVerificationState::SdkReady->value;
}
```

---

## Webhooks

Ruta registrada automáticamente:

```
POST /webhooks/sumsub
```

Configura esta URL en Sumsub (**Webhooks**). El controlador valida la firma HMAC-SHA256 con `SUMSUB_WEBHOOK_SECRET` y encola `ProcessSumsubWebhook`.

Excluye la ruta del CSRF si aplica:

```php
// bootstrap/app.php o VerifyCsrfToken
protected $except = [
    'webhooks/sumsub',
];
```

Personaliza URI y nombre de ruta en `.env`:

```env
SUMSUB_WEBHOOK_ROUTE=webhooks/sumsub
SUMSUB_WEBHOOK_ROUTE_NAME=sumsub.webhook
```

---

## Eventos

| Evento | Cuándo se dispara |
|---|---|
| `ApplicantCreated` | Tras crear un applicant en Sumsub y guardarlo localmente |
| `ApplicantStatusChanged` | Cada webhook que actualiza el registro local |
| `ApplicantReviewed` | Cuando llega un resultado final (`GREEN` / `RED` / `RETRY`) |

### Ejemplo: marcar KYC verificado al aprobar

```php
use AnselmiDev\Sumsub\Events\ApplicantReviewed;
use Illuminate\Support\Facades\Event;

Event::listen(ApplicantReviewed::class, function (ApplicantReviewed $event) {
    if ($event->isApproved()) {
        $event->applicant->user->update(['kyc_verified_at' => now()]);
    }

    if ($event->isRejected()) {
        // notificar, actualizar estado, etc.
    }
});
```

Helpers en `ApplicantReviewed`:

```php
$event->applicant;
$event->reviewAnswer;    // 'GREEN' | 'RED' | 'RETRY'
$event->webhookPayload;

$event->isApproved();
$event->isRejected();
$event->needsRetry();
```

---

## Reemplazar el repositorio (avanzado)

Por defecto se usa `SumsubApplicantRepository` sobre la tabla `sumsub_applicants`.

Para usar tus propios modelos, implementa `KycRepositoryInterface` y regístralo en `AppServiceProvider`:

```php
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use App\Repositories\MyCustomKycRepository;

public function register(): void
{
    $this->app->bind(KycRepositoryInterface::class, MyCustomKycRepository::class);
}
```

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

## Flujo completo de KYC

```
1. Usuario inicia verificación (UI en tu app)
2. Backend: Sumsub::createApplicant($user)
3. Backend: Sumsub::generateSdkToken($user)
4. Frontend: Sumsub Web SDK con el token
5. Usuario sube documentos a Sumsub
6. Sumsub → POST /webhooks/sumsub
7. Paquete valida firma y procesa ProcessSumsubWebhook
8. Job actualiza SumsubApplicant y dispara ApplicantReviewed
9. Tu listener actualiza el usuario / notifica
```

---

## Referencia del Facade

```php
use AnselmiDev\Sumsub\Facades\Sumsub;

Sumsub::createApplicant(Authenticatable $user, ?string $levelName = null): SumsubApplicant
Sumsub::generateSdkToken(Authenticatable $user, ?string $levelName = null): array
Sumsub::refreshApplicant(SumsubApplicant $applicant): SumsubApplicant
```

---

## Estructura del paquete

```
anselmi-dev/sumsub/
├── config/
│   └── sumsub.php              # Config publicable
├── database/
│   └── migrations/             # Migraciones publicables
├── routes/
│   └── api.php                 # Ruta del webhook
├── src/
│   ├── Console/                # Comandos Artisan
│   ├── Contracts/              # SumsubClientInterface, KycRepositoryInterface
│   ├── DataTypes/              # SumsubStatus
│   ├── Events/                 # ApplicantCreated, ApplicantReviewed, …
│   ├── Facades/                # Sumsub
│   ├── Http/
│   │   ├── Client/             # SumsubClient (API REST firmada)
│   │   └── Webhooks/           # SumsubWebhookController
│   ├── Jobs/                   # ProcessSumsubWebhook
│   ├── Models/                 # SumsubApplicant
│   ├── Repositories/           # SumsubApplicantRepository
│   ├── Services/               # SumsubService
│   └── SumsubServiceProvider.php
└── tests/                      # (Pest / PHPUnit)
```

Convenciones y decisiones de diseño: ver [ARCHITECTURE.md](./ARCHITECTURE.md).

---

## Desarrollo local

### Path repository en Composer

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

### Simular webhooks

Solo en entornos no productivos:

```bash
php artisan sumsub:simulate-webhook --answer=GREEN --sync

php artisan sumsub:simulate-webhook 5cb56e8e0a975a35f333cb83 --answer=RED
```

---

## Publicación de assets

| Tag | Comando | Destino |
|---|---|---|
| Config | `php artisan vendor:publish --tag=sumsub-config` | `config/sumsub.php` |
| Migraciones | `php artisan vendor:publish --tag=sumsub-migrations` | `database/migrations/` |

---

## Licencia

MIT
