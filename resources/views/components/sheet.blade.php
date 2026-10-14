{{--
    Bottom sheet on mobile, centred modal from 640px (docs/08). Focus-trapped, closes with Esc.
    Open:  $dispatch('open-sheet', '{name}')     Close: $dispatch('close-sheet', '{name}')
    From Livewire: $this->dispatch('open-sheet', name: '{name}')
--}}
@props(['name', 'title'])

<div x-data="{ open: false, isMine: (detail) => (detail?.name ?? detail) === @js($name) }"
     x-on:open-sheet.window="if (isMine($event.detail)) open = true"
     x-on:close-sheet.window="if (isMine($event.detail)) open = false"
     x-on:keydown.escape.window="open = false"
     x-effect="document.body.classList.toggle('overflow-hidden', open)">
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="open" x-transition.opacity.duration.150ms class="absolute inset-0 bg-black/60" @click="open = false" aria-hidden="true"></div>

        <div x-show="open" x-trap.noscroll="open"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-6 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-y-6 opacity-0"
             role="dialog" aria-modal="true" aria-labelledby="sheet-{{ $name }}-title"
             {{ $attributes->class('relative max-h-[90dvh] w-full overflow-y-auto rounded-t-3xl border border-line bg-surface px-4 pt-3 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:max-w-lg sm:rounded-3xl sm:px-6 sm:pt-6') }}>
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-white/20 sm:hidden" aria-hidden="true"></div>

            <div class="mb-4 flex items-start justify-between gap-4">
                <h2 id="sheet-{{ $name }}-title" class="text-xl">{{ $title }}</h2>
                <button type="button" @click="open = false" class="-mt-2 -mr-2 grid size-11 shrink-0 place-items-center rounded-full text-subtle hover:bg-white/5 hover:text-white" aria-label="Close">
                    <x-heroicon-o-x-mark class="size-6" aria-hidden="true" />
                </button>
            </div>

            {{ $slot }}
        </div>
    </div>
</div>
