<x-mail::table>
| | |
|:--|:--|
| Package | {{ $membership->package->name }} |
| Covered | {{ $membership->members->pluck('first_name')->join(', ', ' and ') }} |
| Credits | {{ $membership->monthly_credits }} per month |
| Price | {{ \App\Support\Money::format($membership->monthly_price_cents) }} per month |
</x-mail::table>
