<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

// docs/04 § /privacy, /terms: the policies are Markdown files, amended as docs/legal/README.md says.
class LegalController
{
    public function privacy(): View
    {
        return $this->page('Privacy Policy', 'privacy');
    }

    public function terms(): View
    {
        return $this->page('Terms of Service', 'terms');
    }

    private function page(string $title, string $file): View
    {
        $html = Str::markdown((string) file_get_contents(resource_path("markdown/{$file}.md")), [
            // The amendment note at the top of each file is a comment, not content.
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // The files keep the printed line breaks, such as the contact lines under a heading.
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        return view('pages.legal', ['title' => $title, 'html' => $html]);
    }
}
