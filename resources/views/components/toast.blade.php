{{--
    Global toast stack, rendered once by the base layout.
    Trigger from Alpine/Livewire: $dispatch('toast', { message: 'Booked!', type: 'success' })
    or flash session('toast') before a redirect.
--}}
<div x-data="{
        toasts: [],
        add(detail) {
            const toast = { id: Date.now() + Math.random(), message: detail.message ?? detail, type: detail.type ?? 'success' };
            this.toasts.push(toast);
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== toast.id), 4000);
        },
    }"
     x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
     x-on:toast.window="add($event.detail)"
     role="status" aria-live="polite"
     class="pointer-events-none fixed inset-x-0 bottom-24 z-[60] flex flex-col items-center gap-2 px-4 lg:top-6 lg:bottom-auto">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition.opacity.duration.150ms
             class="pointer-events-auto flex w-full max-w-sm animate-sheet-up items-center gap-3 rounded-2xl border border-line bg-raised px-4 py-3 text-sm font-medium shadow-lg">
            <x-heroicon-o-check-circle x-show="toast.type === 'success'" class="size-5 shrink-0 text-success" aria-hidden="true" />
            <x-heroicon-o-exclamation-triangle x-show="toast.type === 'error'" class="size-5 shrink-0 text-danger" aria-hidden="true" />
            <x-heroicon-o-information-circle x-show="toast.type === 'info'" class="size-5 shrink-0 text-info" aria-hidden="true" />
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
