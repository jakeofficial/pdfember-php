<?php

namespace Pdfember;

use LogicException;
use Pdfember\Exceptions\PdfemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A PDF (or, for the split tool, a ZIP of PDFs) returned by the API, with the
 * details from its `X-Pdfember-*` headers.
 */
final class Document
{
    public function __construct(
        public readonly string $contents,
        public readonly string $contentType,
        public readonly string $filename,
        public readonly int $pages,
        public readonly int $files,
        public readonly ?string $renderId,
        public readonly ?int $renderMs,
        public readonly ?int $documentsLeft,
    ) {}

    public function isZip(): bool
    {
        return str_starts_with($this->contentType, 'application/zip');
    }

    public function size(): int
    {
        return strlen($this->contents);
    }

    public function toBase64(): string
    {
        return base64_encode($this->contents);
    }

    /** Writes the file and returns its path; missing directories are created. */
    public function save(string $path): string
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new PdfemberException("Could not create the directory \"{$directory}\".");
        }

        if (@file_put_contents($path, $this->contents) === false) {
            throw new PdfemberException("Could not write \"{$path}\".");
        }

        return $path;
    }

    /** A response that downloads the file (Laravel, Symfony). */
    public function download(?string $filename = null): Response
    {
        return $this->response('attachment', $filename);
    }

    /** A response that shows the file in the browser (Laravel, Symfony). */
    public function inline(?string $filename = null): Response
    {
        return $this->response('inline', $filename);
    }

    private function response(string $disposition, ?string $filename): Response
    {
        if (! class_exists(Response::class)) {
            throw new LogicException('Responses need symfony/http-foundation (included with Laravel and Symfony). Use $document->contents instead.');
        }

        $name = str_replace(['"', '\\', "\r", "\n"], '', $filename ?? $this->filename);

        return new Response($this->contents, 200, [
            'Content-Type' => $this->contentType,
            'Content-Disposition' => "{$disposition}; filename=\"{$name}\"",
            'Content-Length' => (string) $this->size(),
            'Cache-Control' => 'no-store',
        ]);
    }
}
