# pdfember for PHP

PHP client for [pdfember](https://pdfember.com): HTML and URLs to PDF with smart
page breaks, plus 19 PDF tools (merge, split, watermark, page numbers, …).
Works in any PHP 8.1+ project; in Laravel 10 to 13 it registers itself.

```sh
composer require pdfember/pdfember-php
```

You need an API key (`pdx_live_…`): create one on
[pdfember.com/dashboard](https://pdfember.com/dashboard). The client refuses to
start without one.

## Laravel

Add the key to `.env`:

```dotenv
PDFEMBER_API_KEY=pdx_live_...
```

```php
use Pdfember\Laravel\Facades\Pdfember;
use Pdfember\Tool;

// A PDF from a Blade view, downloaded by the browser
return Pdfember::html(view('invoices.show', ['invoice' => $invoice])->render(), [
    'page' => ['size' => 'A4', 'margin' => 15],
])->download("invoice-{$invoice->number}.pdf");

// A PDF tool on an upload, stored on a disk
$pdf = Pdfember::tool(Tool::Watermark, $request->file('contract'), ['text' => 'DRAFT', 'opacity' => 0.2]);
Storage::put('contracts/draft.pdf', $pdf->contents);
```

Or inject `Pdfember\Pdfember` into a controller, job or service. To change the
base URL or the timeout, publish the config:

```sh
php artisan vendor:publish --tag=pdfember-config
```

| Variable | Default |
|---|---|
| `PDFEMBER_API_KEY` | – (required) |
| `PDFEMBER_BASE_URL` | `https://pdfember.com/api/v1` |
| `PDFEMBER_TIMEOUT` | `70` (seconds) |

## Plain PHP (Symfony, WordPress, scripts…)

```php
require 'vendor/autoload.php';

use Pdfember\Pdfember;
use Pdfember\Tool;

$pdfember = new Pdfember(getenv('PDFEMBER_API_KEY'));

$pdfember->html('<h1>Hello</h1>')->save(__DIR__.'/hello.pdf');
$pdfember->url('https://example.com', ['layout' => 'single-page'])->save('page.pdf');
$pdfember->merge(['a.pdf', 'b.pdf'])->save('merged.pdf');
$pdfember->tool(Tool::Split, 'book.pdf', ['every' => 10])->save('parts.zip');
```

`new Pdfember($key, $baseUrl, $timeout, $guzzleClient)`: pass your own Guzzle
client for proxies or for mocked responses in tests.

## Rendering

`html()` and `url()` take the render options of
[the docs](https://pdfember.com/docs): `layout` (`pages`, `single-page`),
`page` (`size`, `orientation`, `margin`, `firstPageMarginTop`), `width`,
`media`, `smartBreaks`, `keepTogetherMax`, `background`, `javascript`, `css`,
`waitForSelector`, `delayMs`, `timeoutMs` and `filename`.

## Tools

`tool(Tool $tool, $files, array $options = [])`. Files may be paths,
`SplFileInfo` objects (Laravel's `UploadedFile` included), stream resources
or `Upload::fromContents($bytes, 'name.pdf')`; pass one or a list.

| Tool | Options |
|---|---|
| `Merge` (2–20 PDFs) | – |
| `AlternateMix` (2–20 PDFs) | `reverse` |
| `Organize` | `pages` (required) |
| `Split` (answers with a ZIP) | `ranges` or `every` |
| `ExtractPages`, `DeletePages` | `pages` (required) |
| `SplitInHalf` | `direction`, `pages` |
| `Rotate` | `angle` (90, 180, 270), `pages` |
| `Flip` | `direction`, `pages` |
| `Crop` | `top`, `right`, `bottom`, `left` (mm), `pages` |
| `Resize` | `size`, `orientation`, `margin` |
| `NUp` | `pagesPerSheet`, `size`, `orientation`, `margin` |
| `Watermark` | `text` (required), `fontSize`, `color`, `opacity`, `angle`, `pages` |
| `PageNumbers` | `format`, `position`, `fontSize`, `color`, `margin`, `startAt`, `pages` |
| `HeaderFooter` | `headerLeft` … `footerRight`, `fontSize`, `color`, `margin`, `startAt`, `pages` |
| `Metadata` | `title`, `author`, `subject`, `keywords`, `creator` |
| `Flatten` | – |
| `RemoveAnnotations` | `keepLinks` |
| `ImagesToPdf` (1–20 JPG/PNG) | `size`, `orientation`, `margin` |

All files together may be up to 25 MB.

## The result

Every call returns a `Pdfember\Document`:

```php
$pdf->contents;       // the bytes
$pdf->pages;          // page count
$pdf->documentsLeft;  // left this month on your plan
$pdf->isZip();        // true for Tool::Split
$pdf->save($path);    // writes the file, creates directories
$pdf->download();     // Laravel/Symfony response; inline() shows it in the browser
$pdf->toBase64();     // e.g. for an email attachment
```

`$pdfember->usage()` returns the plan and this month's documents; it is also
a quick check that the key works.

## Errors

Everything extends `Pdfember\Exceptions\PdfemberException`. API errors are an
`ApiException` with `$status`, `$errorCode` and, for refused options,
`$fields`:

| Exception | When |
|---|---|
| `InvalidApiKeyException` | no key, or not a `pdx_` key |
| `AuthenticationException` | 401: wrong or revoked key |
| `QuotaExceededException` | 402: this month's documents are used up |
| `ValidationException` | 422: an option or a file was refused (`$fields`) |
| `RateLimitException` | 429: too many requests (`$retryAfter`, seconds) |
| `ApiException` | anything else, e.g. 504 `render_timeout` |

```php
try {
    $pdf = Pdfember::html($html);
} catch (\Pdfember\Exceptions\RateLimitException $e) {
    $this->release($e->retryAfter ?? 10); // in a queued job
}
```

## Tests

```sh
composer install && composer test
```

## License

MIT
