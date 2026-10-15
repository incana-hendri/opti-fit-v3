<?php

namespace App\Livewire;

use App\Actions\Bookings\CancelBooking;
use App\Exceptions\BookingException;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /bookings: upcoming bookings with Cancel, and the last 60 days (docs/04 § /bookings; BR-7.7, 7.11).
 */
class BookingsPage extends Component
{
    /** docs/04: the Past tab reaches back this many days. */
    private const PAST_DAYS = 60;

    public string $tab = 'upcoming';

    #[Locked]
    public ?int $cancelId = null;

    // BR-2.7: bookings are only ever looked up through the customer's own household.
    public function confirmCancel(int $bookingId): void
    {
        $this->cancelId = $this->household()->bookings()->findOrFail($bookingId)->id;
        $this->dispatch('open-sheet', name: 'cancel-booking');
    }

    public function cancel(): void
    {
        $household = $this->household();
        $booking = $household->bookings()->with('member')->findOrFail($this->cancelId);

        try {
            $creditReturned = app(CancelBooking::class)->handle($booking, $this->user());
            $message = $creditReturned
                ? "{$booking->member->first_name}'s booking is cancelled. Your credit has been returned."
                : "{$booking->member->first_name}'s booking is cancelled.";
            $this->dispatch('toast', message: $message);
        } catch (BookingException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        }

        $this->cancelId = null;
        $this->dispatch('close-sheet', name: 'cancel-booking');
    }

    public function render(): View
    {
        $household = $this->household();

        return view('livewire.bookings-page', [
            'upcoming' => $this->upcoming($household),
            'past' => $this->past($household),
            'cancelling' => $this->cancelId === null ? null : $household->bookings()->with(['member', 'classSession'])->find($this->cancelId),
        ])->title('Bookings');
    }

    /**
     * Sessions with at least one booked place of this household, each carrying only that household's bookings.
     *
     * @return Collection<int, ClassSession>
     */
    private function upcoming(Household $household): Collection
    {
        return ClassSession::query()
            ->where('starts_at', '>', now())
            ->whereHas('bookings', fn ($bookings) => $bookings->bookedFor($household))
            ->with(['location', 'bookings' => fn ($bookings) => $bookings->bookedFor($household)->with('member')->orderBy('id')])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Bookings whose class has been or that were cancelled, newest class first, with the session attached to each.
     *
     * @return Collection<int, ClassSession>
     */
    private function past(Household $household): Collection
    {
        return ClassSession::query()
            ->where('starts_at', '>=', now()->subDays(self::PAST_DAYS))
            ->whereHas('bookings', fn ($bookings) => $bookings->pastFor($household))
            ->with(['location', 'bookings' => fn ($bookings) => $bookings->pastFor($household)->with(['member', 'classSession', 'cancelledBy'])->orderBy('id')])
            ->orderByDesc('starts_at')
            ->get();
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    private function household(): Household
    {
        return $this->user()->household()->firstOrFail();
    }
}
