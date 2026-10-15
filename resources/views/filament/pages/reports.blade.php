<x-filament-panels::page>
    <div class="max-w-xs">
        <label for="report-month" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">Month</label>
        <x-filament::input.wrapper>
            <x-filament::input.select id="report-month" wire:model.live="month">
                @foreach ($this->periods() as $period => $label)
                    <option value="{{ $period }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <x-filament::section heading="Totals" :description="$isCurrent ? 'The month so far.' : null">
        <dl class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                'Bookings' => $totals['bookings'],
                'Attended' => $totals['attended'],
                'No-shows' => $totals['noShows'],
                'Late cancellations' => $totals['lateCancellations'],
                'Cancellations' => $totals['cancellations'],
                'Guest places' => $totals['guestPlaces'],
            ] as $label => $value)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="text-2xl font-semibold tabular-nums">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Attendance rate: <span class="font-medium text-gray-950 dark:text-white">{{ $totals['attendanceRate'] === null ? '—' : $totals['attendanceRate'].'%' }}</span>.
            Bookings still booked 24 hours after the class count as attended.
        </p>
    </x-filament::section>

    <x-filament::section heading="Credits" :description="$isCurrent ? 'Expired credits are counted when the month closes.' : null">
        <dl class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                'Granted' => $credits['granted'],
                'Used' => $credits['used'],
                'Adjusted' => $credits['adjusted'],
                'Expired' => $credits['expired'],
            ] as $label => $value)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="text-2xl font-semibold tabular-nums">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Used is bookings minus refunds.</p>
    </x-filament::section>

    <x-filament::section heading="Sessions">
        @if ($sessions === [])
            <p class="text-gray-500 dark:text-gray-400">No classes in this month.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th scope="col" class="py-2 pe-4 text-start font-medium">Date</th>
                            <th scope="col" class="py-2 pe-4 text-start font-medium">Class</th>
                            <th scope="col" class="py-2 pe-4 text-end font-medium">Capacity</th>
                            <th scope="col" class="py-2 pe-4 text-end font-medium">Occupied</th>
                            <th scope="col" class="py-2 pe-4 text-end font-medium">Fill</th>
                            <th scope="col" class="py-2 text-end font-medium">Attended</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($sessions as $session)
                            <tr>
                                <td class="whitespace-nowrap py-2 pe-4 tabular-nums">{{ $session['startsAt']->format('D j M, H:i') }}</td>
                                <td class="py-2 pe-4">{{ $session['title'] }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $session['capacity'] }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $session['occupied'] }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $session['fillPercent'] }}%</td>
                                <td class="py-2 text-end tabular-nums">{{ $session['attended'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Memberships" description="Active at the end of the month.">
        @if ($memberships === [])
            <p class="text-gray-500 dark:text-gray-400">No active memberships in this month.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th scope="col" class="py-2 pe-4 text-start font-medium">Package</th>
                            <th scope="col" class="py-2 pe-4 text-end font-medium">Memberships</th>
                            <th scope="col" class="py-2 text-end font-medium">Income</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($memberships as $row)
                            <tr>
                                <td class="py-2 pe-4">{{ $row['package'] }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $row['count'] }}</td>
                                <td class="py-2 text-end tabular-nums">{{ \App\Support\Money::format($row['income']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 font-semibold dark:border-white/10">
                            <th scope="row" class="py-2 pe-4 text-start">Total</th>
                            <td class="py-2 pe-4 text-end tabular-nums">{{ $membershipCount }}</td>
                            <td class="py-2 text-end tabular-nums">{{ $income }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
