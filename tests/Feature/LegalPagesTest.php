<?php

// docs/legal/README.md: the policies are rendered from Markdown with only the listed amendments.
function legalContent(string $path): string
{
    $html = test()->get($path)->assertOk()->getContent();

    return html_entity_decode(strip_tags(substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'))), ENT_QUOTES);
}

it('renders the terms with every amended sentence', function () {
    $text = legalContent('/terms');

    expect($text)
        ->toContain('Terms of Service')
        ->toContain('Frequency of classes (the packages listed on our pricing page, which may change from time to time)')
        ->toContain('Credits are shared by the family members covered by your package.')
        ->toContain('Bookings close 8 hours before the class starts (or as shown for that class).')
        ->toContain('If you cancel before the booking cutoff (8 hours before the class, unless shown otherwise), your credit is returned. Later cancellations free your spot but the credit is not returned. If the studio cancels a class, your credit is always returned.')
        ->toContain('No-shows are treated as attended (credits used)')
        ->toContain('Emergency cancellations considered case-by-case')
        ->toContain('Effective Date: November 2026');
});

it('renders the privacy policy with the amendments', function () {
    $text = legalContent('/privacy');

    expect($text)
        ->toContain('Privacy Policy')
        ->toContain('Optional notes about injuries or conditions of family members. These are stored encrypted and visible only to studio staff.')
        ->toContain('We use only essential cookies.')
        ->toContain('Last Updated: November 2026')
        ->not->toContain('Analytics')
        ->not->toContain('Usage Data')
        ->not->toContain('Functional Cookies');
});

it('keeps the removed wording and the print artefacts out of the legal pages', function (string $path) {
    $text = legalContent($path);

    expect($text)
        ->not->toContain('localhost:8080')
        ->not->toContain('Weekly booking limits')
        ->not->toContain('Weekly limits apply')
        ->not->toContain('Cancellations do not restore used credits')
        ->not->toContain('(2×, 3×, or 4× per week)')
        ->not->toContain('Cookie Settings')
        ->not->toContain('Back to Home')
        ->not->toMatch('~\b\d/\d\b~')
        ->not->toMatch('~\d{2}/\d{2}/\d{4}~')
        ->not->toContain('Amended for the new app');
})->with(['/privacy', '/terms']);

it('numbers the sections as the published documents do', function () {
    expect(legalContent('/terms'))->toContain('15. Severability')->toContain('4.3 Credit System')
        ->and(legalContent('/privacy'))->toContain('14. Contact Us')->toContain('2.2 Automatically Collected Information');
});

it('links the legal pages from the footer', function () {
    $this->get('/')->assertSee('href="'.route('privacy').'"', false)->assertSee('href="'.route('terms').'"', false);
});
