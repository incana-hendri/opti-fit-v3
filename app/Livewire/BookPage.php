<?php

namespace App\Livewire;

use App\Actions\Bookings\BookSession;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Cutoff;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /book: the day strip, the session cards and the booking sheet (docs/04 § /book; BR-7.2, 7.3).
 */
class BookPage extends Component
{
    #[Locked]
    public ?int $sessionId = null;

    /** @var list<int|string> */
    public array $memberIds = [];

    public ?string $error = null;

    public function openBook(int $sessionId): void
    {
        $household = $this->household();
        $session = ClassSession::query()->scheduled()->findOrFail($sessionId);

        $this->sessionId = $session->id;
        $this->error = null;
        $this->memberIds = $this->freeMembers($household, $session)->pluck('id')->all();
        $this->dispatch('open-sheet', name: 'book');
    }

    public function book(): void
    {
        $household = $this->household();
        $session = ClassSession::query()->findOrFail($this->sessionId);

        try {
            app(BookSession::class)->handle($household, $session, array_map('intval', $this->memberIds), $this->user());
        } catch (BookingException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->dispatch('close-sheet', name: 'book');
        $this->dispatch('toast', message: 'Booked! See you '.$this->when($session->starts_at));
        $this->reset('sessionId', 'memberIds', 'error');
    }

    public function render(): View
    {
        $household = $this->household();
        $covered = $household->coveredMembers();
        $balance = CreditLedger::balance($household);
        $sessions = $this->sessions();
        $booked = $this->bookedBySession($household, $sessions);

        return view('livewire.book-page', [
            'balance' => $balance,
            'hasPackage' => $covered->isNotEmpty(),
            'days' => $this->days($sessions),
            'sessionsByDay' => $sessions->groupBy(fn (ClassSession $session) => $session->starts_at->format('Y-m-d')),
            'states' => $sessions->mapWithKeys(fn (ClassSession $session) => [$session->id => $this->stateOf($session, $covered, $booked->get($session->id, new Collection))]),
            'booked' => $booked,
            'sheet' => $this->sheet($household, $covered, $balance),
        ])->title('Book');
    }

    /**
     * Scheduled classes from now to the end of the booking window (BR-7.2 item 3).
     *
     * @return Collection<int, ClassSession>
     */
    private function sessions(): Collection
    {
        return ClassSession::query()
            ->scheduled()
            ->with('location')
            ->withOccupancy()
            ->where('starts_at', '>=', now())
            ->where('starts_at', '<=', Cutoff::windowEnd())
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Today to the end of the window, with a dot on the days that have classes.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return list<array{date: Carbon, hasSessions: bool}>
     */
    private function days(Collection $sessions): array
    {
        $withSessions = $sessions->map(fn (ClassSession $session) => $session->starts_at->format('Y-m-d'))->flip();
        $days = [];

        for ($day = today(); $day->lte(Cutoff::windowEnd()); $day = $day->copy()->addDay()) {
            $days[] = ['date' => $day, 'hasSessions' => $withSessions->has($day->format('Y-m-d'))];
        }

        return $days;
    }

    /**
     * This household's bookings that hold a place, per session id.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return SupportCollection<int, Collection<int, Booking>>
     */
    private function bookedBySession(Household $household, Collection $sessions): SupportCollection
    {
        return $household->bookings()
            ->occupying()
            ->whereIn('class_session_id', $sessions->modelKeys())
            ->with('member:id,first_name')
            ->get()
            ->groupBy('class_session_id');
    }

    /**
     * bookable | closed | full | all-booked, in the order docs/04 gives the card states.
     *
     * @param  Collection<int, Member>  $covered
     * @param  Collection<int, Booking>  $bookings  this household's bookings on the session
     */
    private function stateOf(ClassSession $session, Collection $covered, Collection $bookings): string
    {
        return match (true) {
            $covered->isNotEmpty() && $covered->every(fn (Member $member) => $bookings->contains('member_id', $member->id)) => 'all-booked',
            Cutoff::hasPassed($session) => 'closed',
            $session->placesLeft() <= 0 => 'full',
            default => 'bookable',
        };
    }

    /**
     * The sheet's data for the chosen session: each covered member and whether they are already booked.
     *
     * @param  Collection<int, Member>  $covered
     * @return array{session: ClassSession, rows: list<array{member: Member, booked: bool}>, selected: int, creditsAfter: int}|null
     */
    private function sheet(Household $household, Collection $covered, int $balance): ?array
    {
        if ($this->sessionId === null) {
            return null;
        }

        $session = ClassSession::query()->with('location')->withOccupancy()->find($this->sessionId);
        if ($session === null) {
            return null;
        }

        $bookedIds = $this->bookedIds($household, $session);
        $selected = $covered->filter(fn (Member $member) => ! in_array($member->id, $bookedIds, true) && in_array($member->id, array_map('intval', $this->memberIds), true))->count();

        return [
            'session' => $session,
            'rows' => $covered->map(fn (Member $member) => ['member' => $member, 'booked' => in_array($member->id, $bookedIds, true)])->values()->all(),
            'selected' => $selected,
            'creditsAfter' => $balance - $selected,
        ];
    }

    /**
     * @return Collection<int, Member>
     */
    private function freeMembers(Household $household, ClassSession $session): Collection
    {
        $bookedIds = $this->bookedIds($household, $session);

        return $household->coveredMembers()->reject(fn (Member $member) => in_array($member->id, $bookedIds, true));
    }

    /**
     * @return list<int>
     */
    private function bookedIds(Household $household, ClassSession $session): array
    {
        return $household->bookings()->occupying()->where('class_session_id', $session->id)->pluck('member_id')->all();
    }

    // "Monday 05:00", with the date when the class is more than a week away.
    private function when(Carbon $startsAt): string
    {
        return $startsAt->format($startsAt->lt(today()->addDays(7)) ? 'l H:i' : 'l j M, H:i');
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
