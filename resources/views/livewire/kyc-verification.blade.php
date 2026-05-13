{{--
    Sumsub KYC Verification Widget
    ────────────────────────────────────────────────────────────────────────────
    Widget states: idle | loading | sdk_ready | progress | completed | cancelled | error

    To customise this view, publish it:
      php artisan vendor:publish --tag=sumsub-views

    The published file will land in:
      resources/views/vendor/sumsub/livewire/kyc-verification.blade.php

    Requires: Flux UI (https://fluxui.dev) for icons and button components.
--}}
<div
    class="w-full"
    x-data="sumsubWidget(@js($sdkToken))"
    x-init="init()"
    @sumsub:launch.window="launch($event.detail.token)"
    @sumsub:token-refreshed.window="resolveRefresh($event.detail.token)"
>

    {{-- ─── IDLE ─────────────────────────────────────────────────────────── --}}
    @if ($state === 'idle')
        <div class="flex flex-col items-center gap-6 py-10 text-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon.shield-check class="h-10 w-10 text-zinc-400" />
            </div>

            <div class="max-w-sm space-y-2">
                <flux:heading size="xl">{{ __('Verifica tu identidad') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Para operar en la plataforma necesitamos verificar tu identidad. El proceso tarda menos de 5 minutos.') }}
                </flux:text>
            </div>

            <ul class="space-y-2 text-left text-sm text-zinc-600 dark:text-zinc-400">
                <li class="flex items-center gap-2">
                    <flux:icon.check-circle class="h-4 w-4 shrink-0 text-green-500" />
                    {{ __('DNI, pasaporte o tarjeta de residencia') }}
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon.check-circle class="h-4 w-4 shrink-0 text-green-500" />
                    {{ __('Selfie en tiempo real') }}
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon.check-circle class="h-4 w-4 shrink-0 text-green-500" />
                    {{ __('Proceso 100% online y seguro') }}
                </li>
            </ul>

            <flux:button wire:click="startVerification" variant="primary" icon="arrow-right">
                {{ __('Iniciar verificación') }}
            </flux:button>
        </div>
    @endif

    {{-- ─── LOADING ──────────────────────────────────────────────────────── --}}
    @if ($state === 'loading')
        <div class="flex flex-col items-center gap-4 py-16 text-center">
            <flux:icon.arrow-path class="h-10 w-10 animate-spin text-zinc-400" />
            <flux:text class="text-zinc-500">
                {{ __('Preparando el proceso de verificación…') }}
            </flux:text>
        </div>
    @endif

    {{-- ─── SDK WIDGET ───────────────────────────────────────────────────── --}}
    @if ($state === 'sdk_ready')
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div id="sumsub-websdk-container" class="min-h-[600px] w-full"></div>
        </div>
    @endif

    {{-- ─── PROGRESS (in review queue) ──────────────────────────────────── --}}
    @if ($state === 'progress')
        <div class="flex flex-col items-center gap-6 py-10 text-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-yellow-50 dark:bg-yellow-900/20">
                <flux:icon.clock class="h-10 w-10 text-yellow-500" />
            </div>
            <div class="max-w-sm space-y-2">
                <flux:heading size="xl">{{ __('Verificación en proceso') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Hemos recibido tu documentación y la estamos revisando. Te notificaremos por email cuando el proceso finalice.') }}
                </flux:text>
            </div>
        </div>
    @endif

    {{-- ─── COMPLETED (approved) ────────────────────────────────────────── --}}
    @if ($state === 'completed')
        <div class="flex flex-col items-center gap-6 py-10 text-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-green-50 dark:bg-green-900/20">
                <flux:icon.check-badge class="h-10 w-10 text-green-500" />
            </div>
            <div class="max-w-sm space-y-2">
                <flux:heading size="xl">{{ __('¡Identidad verificada!') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Tu identidad ha sido verificada correctamente. Ya puedes operar en la plataforma.') }}
                </flux:text>
            </div>
            {{ $slot ?? '' }}
        </div>
    @endif

    {{-- ─── CANCELLED (rejected) ────────────────────────────────────────── --}}
    @if ($state === 'cancelled')
        <div class="flex flex-col items-center gap-6 py-10 text-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-50 dark:bg-red-900/20">
                <flux:icon.x-circle class="h-10 w-10 text-red-500" />
            </div>
            <div class="max-w-sm space-y-2">
                <flux:heading size="xl">{{ __('Verificación rechazada') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('No hemos podido verificar tu identidad con la documentación aportada. Puedes intentarlo de nuevo.') }}
                </flux:text>
            </div>
            <flux:button wire:click="startVerification" variant="primary" icon="arrow-path">
                {{ __('Intentar de nuevo') }}
            </flux:button>
        </div>
    @endif

    {{-- ─── ERROR ────────────────────────────────────────────────────────── --}}
    @if ($state === 'error')
        <div class="flex flex-col items-center gap-6 py-10 text-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-red-50 dark:bg-red-900/20">
                <flux:icon.exclamation-triangle class="h-10 w-10 text-red-500" />
            </div>
            <div class="max-w-sm space-y-2">
                <flux:heading size="xl">{{ __('Ha ocurrido un error') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ $errorMessage ?? __('No se pudo conectar con el servicio de verificación.') }}
                </flux:text>
            </div>
            <flux:button wire:click="startVerification" variant="primary" icon="arrow-path">
                {{ __('Reintentar') }}
            </flux:button>
        </div>
    @endif

</div>

@push('scripts')
<script src="https://static.sumsub.com/idensic/static/sns-websdk-builder.js"></script>
<script>
    function sumsubWidget(initialToken) {
        return {
            sdkInstance: null,
            pendingRefreshResolve: null,

            init() {
                // If a token was already generated server-side (e.g. on page refresh with
                // sdk_ready state), launch the SDK immediately.
                if (initialToken) {
                    this.$nextTick(() => this.launch(initialToken));
                }
            },

            launch(token) {
                if (this.sdkInstance) {
                    this.sdkInstance.destroy();
                    this.sdkInstance = null;
                }

                const component = this;

                this.sdkInstance = snsWebSdk
                    .init(token, () => component.requestNewToken())
                    .withConf({
                        lang: document.documentElement.lang || 'es',
                        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                    })
                    .withOptions({ addViewportTag: false, adaptIframeHeight: true })
                    .on('idCheck.onStepCompleted', (payload) => {
                        console.debug('[Sumsub] step completed', payload);
                    })
                    .on('idCheck.applicantReviewComplete', (payload) => {
                        // reviewAnswer: GREEN | RED | RETRY
                        const answer = payload?.reviewResult?.reviewAnswer ?? 'pending';
                        Livewire.dispatch('sumsub:review-complete', { reviewAnswer: answer });
                    })
                    .on('idCheck.onError', (error) => {
                        console.error('[Sumsub] SDK error', error);
                    })
                    .build();

                this.sdkInstance.launch('#sumsub-websdk-container');
            },

            /**
             * Called by the SDK when the token is about to expire.
             * Returns a Promise that resolves with the new token once Livewire responds.
             */
            requestNewToken() {
                return new Promise((resolve) => {
                    this.pendingRefreshResolve = resolve;
                    Livewire.dispatch('sumsub:refresh-token');
                });
            },

            resolveRefresh(token) {
                if (this.pendingRefreshResolve) {
                    this.pendingRefreshResolve(token);
                    this.pendingRefreshResolve = null;
                }
            },
        };
    }
</script>
@endpush
