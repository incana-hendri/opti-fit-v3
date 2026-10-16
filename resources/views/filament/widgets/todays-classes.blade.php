<x-filament-widgets::widget>
    <x-filament::section heading="Today's classes">
        @forelse ($days as $day => $sessions)
            <h3 class="mt-4 mb-2 text-sm font-semibold text-gray-500 first:mt-0 dark:text-gray-400">{{ $day }}</h3>
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($sessions as $session)
                    <li>
                        <a href="{{ \App\Filament\Resources\ClassSessions\ClassSessionResource::getUrl('view', ['record' => $session]) }}"
                           class="flex min-h-11 flex-wrap items-center gap-x-4 gap-y-1 py-3 hover:bg-gray-50 dark:hover:bg-white/5">
                            <span class="w-14 font-semibold tabular-nums">{{ $session->starts_at->format('H:i') }}</span>
                            <span class="min-w-0 flex-1 basis-40 font-medium">{{ $session->title }}</span>
                            <span class="tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $session->occupying_bookings_count }}/{{ $session->capacity }}
                                @if ($session->guest_places)
                                    <span class="text-gray-500 dark:text-gray-400">(+{{ $session->guest_places }} {{ str('guest')->plural((int) $session->guest_places) }})</span>
                                @endif
                            </span>
                            {{-- A class under way is past worrying about its minimum. --}}
                            @if ($session->isBelowMinimum() && $session->starts_at->isFuture())
                                <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">Below minimum ({{ $session->min_participants }})</x-filament::badge>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @empty
            <p class="text-gray-500 dark:text-gray-400">No classes today or tomorrow.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
