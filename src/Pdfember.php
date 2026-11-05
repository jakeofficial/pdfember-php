<?php

namespace Pdfember;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Pdfember\Exceptions\ApiException;
use Pdfember\Exceptions\InvalidApiKeyException;
use Pdfember\Exceptions\PdfemberException;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

/**
 * Client of the pdfember API (https://pdfember.com/docs).
 *
 *     $pdfember = new Pdfember('pdx_live_...');
 *     $pdfember->html('<h1>Invoice</h1>', ['page' => ['size' => 'A4']])->save('invoice.pdf');
 *     $pdfember->tool(Tool::Watermark, 'contract.pdf', ['text' => 'DRAFT'])->save('draft.pdf');
 */
class Pdfember
{
    public const VERSION = '1.0.0';

    public const DEFAULT_BASE_URL = 'https://pdfember.com/api/v1';

    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly ClientInterface $http;

    /**
     * @param  string|null  $apiKey  a `pdx_live_...` key from https://pdfember.com/dashboard
     * @param  float  $timeout  seconds; a render may take up to 60
     * @param  ClientInterface|null  $http  a Guzzle client of your own (proxies, mocks in tests)
     */
    public function __construct(
        #[SensitiveParameter] ?string $apiKey,
        ?string $baseUrl = null,
        float $timeout = 70,
        ?ClientInterface $http = null,
    ) {
        $apiKey = trim((string) $apiKey);
        if ($apiKey === '') {
            throw InvalidApiKeyException::missing();
        }
        if (! str_starts_with($apiKey, 'pdx_')) {
            throw InvalidApiKeyException::malformed();
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl ?: self::DEFAULT_BASE_URL, '/');
        $this->http = $http ?? new Client(['timeout' => $timeout, 'connect_timeout' => 10]);
    }

    /**
     * Renders HTML to a PDF.
     *
     * @param  array<string, mixed>  $options  render options: layout, page, width, media, smartBreaks, css, filename, ...
     */
    public function html(string $html, array $options = []): Document
    {
        return $this->render(['html' => $html] + $options);
    }

    /**
     * Renders a public http(s) page to a PDF.
     *
     * @param  array<string, mixed>  $options  render options: layout, page, width, media, smartBreaks, css, filename, ...
     */
    public function url(string $url, array $options = []): Document
    {
        return $this->render(['url' => $url] + $options);
    }

    /**
     * POST /pdf with the options as they are; `html` or `url` is required.
     *
     * @param  array<string, mixed>  $options
     */
    public function render(array $options): Document
    {
        return $this->document($this->send('POST', '/pdf', ['json' => $options]));
    }

    /**
     * Runs a PDF tool. Answers with a PDF, or a ZIP of PDFs for Tool::Split.
     *
     * @param  mixed  $files  one file or a list: paths, SplFileInfo (UploadedFile), resources or Upload objects
     * @param  array<string, scalar|null>  $options  the tool's options, plus an optional `filename`
     */
    public function tool(Tool|string $tool, mixed $files, array $options = []): Document
    {
        $tool = $tool instanceof Tool ? $tool : Tool::tryFrom($tool)
            ?? throw new PdfemberException("Unknown tool \"{$tool}\". See Pdfember\\Tool for the list.");

        $multipart = [];
        foreach (is_array($files) ? $files : [$files] as $file) {
            $upload = Upload::from($file);
            $multipart[] = ['name' => 'files[]', 'contents' => $upload->contents, 'filename' => $upload->filename];
        }
        foreach ($options as $name => $value) {
            if ($value !== null) {
                $multipart[] = ['name' => (string) $name, 'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
            }
        }

        return $this->document($this->send('POST', '/tools/'.$tool->value, ['multipart' => $multipart]));
    }

    /**
     * Combines PDFs into one, in the order given.
     *
     * @param  array<int, mixed>  $files
     * @param  array<string, scalar|null>  $options
     */
    public function merge(array $files, array $options = []): Document
    {
        return $this->tool(Tool::Merge, $files, $options);
    }

    /** The plan and this month's documents. Also a cheap way to check the key. */
    public function usage(): Usage
    {
        $response = $this->send('GET', '/usage');

        return Usage::fromArray($this->json($response));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function send(string $method, string $path, array $options = []): ResponseInterface
    {
        try {
            $response = $this->http->request($method, $this->baseUrl.$path, $options + [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/json',
                    'User-Agent' => 'pdfember-php/'.self::VERSION.' PHP/'.PHP_VERSION,
                ],
                'http_errors' => false,
                'allow_redirects' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new PdfemberException('Could not reach pdfember: '.$e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() >= 300) {
            throw $this->error($response);
        }

        return $response;
    }

    private function document(ResponseInterface $response): Document
    {
        $contentType = $response->getHeaderLine('Content-Type');
        if (! str_starts_with($contentType, 'application/pdf') && ! str_starts_with($contentType, 'application/zip')) {
            throw new PdfemberException("Expected a PDF from pdfember, got \"{$contentType}\". Check the base URL.");
        }

        preg_match('/filename="([^"]*)"/', $response->getHeaderLine('Content-Disposition'), $match);

        return new Document(
            contents: (string) $response->getBody(),
            contentType: $contentType,
            filename: $match[1] ?? (str_starts_with($contentType, 'application/zip') ? 'document.zip' : 'document.pdf'),
            pages: (int) $response->getHeaderLine('X-Pdfember-Pages'),
            files: (int) ($response->getHeaderLine('X-Pdfember-Files') ?: 1),
            renderId: $response->getHeaderLine('X-Pdfember-Render-Id') ?: null,
            renderMs: $this->intHeader($response, 'X-Pdfember-Render-Ms'),
            documentsLeft: $this->intHeader($response, 'X-Pdfember-Documents-Left'),
        );
    }

    private function error(ResponseInterface $response): ApiException
    {
        $status = $response->getStatusCode();
        $error = json_decode((string) $response->getBody(), true)['error'] ?? null;

        if (! is_array($error)) {
            $message = $status === 429 ? 'Too many requests. Slow down and retry.' : "pdfember answered HTTP {$status}.";

            return ApiException::fromResponse($status, $status === 429 ? 'rate_limited' : "http_{$status}", $message, retryAfter: $this->intHeader($response, 'Retry-After'));
        }

        return ApiException::fromResponse(
            $status,
            (string) ($error['code'] ?? "http_{$status}"),
            (string) ($error['message'] ?? "pdfember answered HTTP {$status}."),
            is_array($error['fields'] ?? null) ? $error['fields'] : [],
            $this->intHeader($response, 'Retry-After'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        if (! is_array($data)) {
            throw new PdfemberException('pdfember answered with something other than JSON. Check the base URL.');
        }

        return $data;
    }

    private function intHeader(ResponseInterface $response, string $name): ?int
    {
        $value = $response->getHeaderLine($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
