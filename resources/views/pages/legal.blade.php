{{-- /privacy and /terms: Markdown from resources/markdown, styled by .prose-legal (docs/04). --}}
<x-layouts.public :title="$title">
    <article class="prose-legal mx-auto max-w-3xl px-4 pt-10 lg:px-6">
        {!! $html !!}
    </article>
</x-layouts.public>
