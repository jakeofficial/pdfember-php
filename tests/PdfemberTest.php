<?php

namespace Pdfember\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pdfember\Exceptions\ApiException;
use Pdfember\Exceptions\AuthenticationException;
use Pdfember\Exceptions\InvalidApiKeyException;
use Pdfember\Exceptions\PdfemberException;
use Pdfember\Exceptions\QuotaExceededException;
use Pdfember\Exceptions\RateLimitException;
use Pdfember\Exceptions\ValidationException;
use Pdfember\Pdfember;
use Pdfember\Tool;
use Pdfember\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class PdfemberTest extends TestCase
{
    private const KEY = 'pdx_live_0123456789012345678901234567890123456789';

    /** @var array<int, array{request: RequestInterface}> */
    private array $sent = [];

    public function test_it_requires_an_api_key(): void
    {
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('API key is required');

        new Pdfember(null);
    }

    public function test_it_refuses_a_key_that_is_not_a_pdfember_key(): void
    {
        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('start with "pdx_"');

        new Pdfember('sk_live_something');
    }

    public function test_it_renders_html_with_the_key_and_options(): void
    {
        $pdfember = $this->client(new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice.pdf"',
            'X-Pdfember-Render-Id' => '42',
            'X-Pdfember-Pages' => '3',
            'X-Pdfember-Render-Ms' => '812',
            'X-Pdfember-Documents-Left' => '97',
        ], '%PDF-1.7 fake'));

        $document = $pdfember->html('<h1>Invoice</h1>', ['page' => ['size' => 'A4'], 'filename' => 'invoice']);

        $request = $this->sent[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://pdfember.com/api/v1/pdf', (string) $request->getUri());
        $this->assertSame('Bearer '.self::KEY, $request->getHeaderLine('Authorization'));
        $this->assertSame(
            ['html' => '<h1>Invoice</h1>', 'page' => ['size' => 'A4'], 'filename' => 'invoice'],
            json_decode((string) $request->getBody(), true),
        );

        $this->assertSame('%PDF-1.7 fake', $document->contents);
        $this->assertSame('invoice.pdf', $document->filename);
        $this->assertSame(3, $document->pages);
        $this->assertSame('42', $document->renderId);
        $this->assertSame(812, $document->renderMs);
        $this->assertSame(97, $document->documentsLeft);
        $this->assertFalse($document->isZip());
    }

    public function test_it_renders_a_url_against_a_custom_base_url(): void
    {
        $pdfember = $this->clientAt('http://localhost:8000/api/v1/', new Response(200, ['Content-Type' => 'application/pdf'], '%PDF'));

        $pdfember->url('https://example.com');

        $request = $this->sent[0]['request'];
        $this->assertSame('http://localhost:8000/api/v1/pdf', (string) $request->getUri());
        $this->assertSame(['url' => 'https://example.com'], json_decode((string) $request->getBody(), true));
    }

    public function test_it_uploads_files_and_options_to_a_tool(): void
    {
        $pdfember = $this->client(new Response(200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="contract.zip"',
            'X-Pdfember-Pages' => '8',
            'X-Pdfember-Files' => '2',
        ], 'PK zip'));
        $path = $this->temporaryFile('%PDF contract');

        $document = $pdfember->tool(Tool::Split, $path, ['ranges' => '1-3, 4-last', 'every' => null, 'keepLinks' => true]);

        $request = $this->sent[0]['request'];
        $body = (string) $request->getBody();
        $this->assertSame('https://pdfember.com/api/v1/tools/split', (string) $request->getUri());
        $this->assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('name="files[]"; filename="'.basename($path).'"', $body);
        $this->assertStringContainsString('%PDF contract', $body);
        $this->assertStringContainsString("name=\"ranges\"\r\nContent-Length: 11\r\n\r\n1-3, 4-last", $body);
        $this->assertStringContainsString("name=\"keepLinks\"\r\nContent-Length: 4\r\n\r\ntrue", $body);
        $this->assertStringNotContainsString('name="every"', $body);

        $this->assertTrue($document->isZip());
        $this->assertSame('contract.zip', $document->filename);
        $this->assertSame(2, $document->files);
    }

    public function test_merge_sends_every_file_in_order(): void
    {
        $pdfember = $this->client(new Response(200, ['Content-Type' => 'application/pdf'], '%PDF'));

        $pdfember->merge([Upload::fromContents('%PDF first', 'first.pdf'), Upload::fromContents('%PDF second', 'second.pdf')]);

        $body = (string) $this->sent[0]['request']->getBody();
        $this->assertStringEndsWith('/tools/merge', (string) $this->sent[0]['request']->getUri());
        $this->assertLessThan(strpos($body, 'filename="second.pdf"'), strpos($body, 'filename="first.pdf"'));
    }

    public function test_it_refuses_an_unknown_tool_and_a_missing_file(): void
    {
        $pdfember = $this->client();

        try {
            $pdfember->tool('compress', 'a.pdf');
            $this->fail('An unknown tool was accepted.');
        } catch (PdfemberException $e) {
            $this->assertStringContainsString('Unknown tool "compress"', $e->getMessage());
        }

        $this->expectExceptionMessage('does not exist');
        $pdfember->tool(Tool::Rotate, '/nope/missing.pdf');
    }

    public function test_api_errors_become_typed_exceptions(): void
    {
        $error = fn (int $status, string $code, array $extra = []) => new Response($status, ['Content-Type' => 'application/json'], json_encode(['error' => ['code' => $code, 'message' => "Message {$code}"] + $extra]));

        $pdfember = $this->client(
            $error(401, 'unauthorized'),
            $error(402, 'quota_exceeded'),
            $error(422, 'invalid_request', ['fields' => ['page.size' => ['The selected page.size is invalid.']]]),
            new Response(429, ['Retry-After' => '17'], 'Too Many Attempts.'),
            $error(504, 'render_timeout'),
        );

        $this->assertThrowsApi(AuthenticationException::class, 401, 'unauthorized', fn () => $pdfember->usage());
        $this->assertThrowsApi(QuotaExceededException::class, 402, 'quota_exceeded', fn () => $pdfember->html('<p>'));
        $validation = $this->assertThrowsApi(ValidationException::class, 422, 'invalid_request', fn () => $pdfember->html('<p>', ['page' => ['size' => 'B9']]));
        $this->assertSame(['page.size' => ['The selected page.size is invalid.']], $validation->fields);
        $rateLimit = $this->assertThrowsApi(RateLimitException::class, 429, 'rate_limited', fn () => $pdfember->html('<p>'));
        $this->assertSame(17, $rateLimit->retryAfter);
        $this->assertThrowsApi(ApiException::class, 504, 'render_timeout', fn () => $pdfember->html('<p>'));
    }

    public function test_a_connection_failure_is_wrapped(): void
    {
        $pdfember = $this->client(new ConnectException('Connection refused', new Request('POST', 'https://pdfember.com/api/v1/pdf')));

        $this->expectException(PdfemberException::class);
        $this->expectExceptionMessage('Could not reach pdfember: Connection refused');

        $pdfember->html('<p>');
    }

    public function test_a_response_that_is_not_a_pdf_is_refused(): void
    {
        $pdfember = $this->client(new Response(200, ['Content-Type' => 'text/html'], '<html>'));

        $this->expectExceptionMessage('Expected a PDF');

        $pdfember->html('<p>');
    }

    public function test_it_reads_the_usage(): void
    {
        $pdfember = $this->client(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'plan' => 'pro', 'documents_used' => 12, 'documents_included' => 1000, 'documents_left' => 988, 'period_start' => '2026-11-01T00:00:00+00:00',
        ])));

        $usage = $pdfember->usage();

        $this->assertSame('GET', $this->sent[0]['request']->getMethod());
        $this->assertSame('pro', $usage->plan);
        $this->assertSame(988, $usage->documentsLeft);
    }

    public function test_a_document_saves_and_becomes_a_response(): void
    {
        $document = $this->client(new Response(200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="a.pdf"'], '%PDF body'))->html('<p>');
        $path = sys_get_temp_dir().'/pdfember-test-'.uniqid().'/nested/a.pdf';

        $this->assertSame($path, $document->save($path));
        $this->assertSame('%PDF body', file_get_contents($path));

        $response = $document->download('report "final".pdf');
        $this->assertSame('attachment; filename="report final.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF body', $response->getContent());
        $this->assertSame('inline; filename="a.pdf"', $document->inline()->headers->get('Content-Disposition'));
    }

    private function client(mixed ...$responses): Pdfember
    {
        return $this->clientAt(null, ...$responses);
    }

    private function clientAt(?string $baseUrl, mixed ...$responses): Pdfember
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        return new Pdfember(self::KEY, $baseUrl, http: new Client(['handler' => $stack]));
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pdfember').'.pdf';
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @template T of ApiException
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function assertThrowsApi(string $class, int $status, string $code, callable $call): ApiException
    {
        try {
            $call();
        } catch (ApiException $e) {
            $this->assertInstanceOf($class, $e);
            $this->assertSame($status, $e->status);
            $this->assertSame($code, $e->errorCode);

            return $e;
        }

        $this->fail("Expected {$class}.");
    }
}
