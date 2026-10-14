{{-- BR-1.3 --}}
<x-layouts.auth title="Verify your email" heading="Check your inbox">
    <p class="text-muted">
        We sent a link to <strong class="text-white">{{ auth()->user()->email }}</strong>.
        Click it to confirm your email, then you can set up your family.
    </p>

    @if (session('status') === 'verification-link-sent')
        <p class="mt-5 rounded-xl bg-success/15 px-4 py-3 text-sm text-success" role="status">A new link is on its way.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <x-button type="submit" variant="secondary" block>Send the link again</x-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="min-h-11 text-sm text-subtle hover:text-white">Log out</button>
    </form>
</x-layouts.auth>
