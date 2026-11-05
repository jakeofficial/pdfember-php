<?php

namespace Pdfember;

use Pdfember\Exceptions\PdfemberException;
use SplFileInfo;

/**
 * A file for a PDF tool. Tools take paths, SplFileInfo objects (Laravel's
 * UploadedFile included), stream resources or uploads made here from bytes.
 */
final class Upload
{
    /**
     * @param  string|resource  $contents
     */
    private function __construct(
        public readonly mixed $contents,
        public readonly string $filename,
    ) {}

    public static function fromPath(string $path, ?string $filename = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new PdfemberException("The file \"{$path}\" does not exist or cannot be read.");
        }

        return new self(fopen($path, 'rb'), $filename ?? basename($path));
    }

    public static function fromContents(string $contents, string $filename): self
    {
        return new self($contents, $filename);
    }

    /**
     * @param  resource  $stream
     */
    public static function fromStream(mixed $stream, ?string $filename = null): self
    {
        if (! is_resource($stream)) {
            throw new PdfemberException('Expected an open stream resource.');
        }

        $uri = stream_get_meta_data($stream)['uri'] ?? '';

        return new self($stream, $filename ?? (basename((string) $uri) ?: 'document.pdf'));
    }

    /** Turns anything a tool accepts into an upload. */
    public static function from(mixed $file): self
    {
        return match (true) {
            $file instanceof self => $file,
            $file instanceof SplFileInfo => self::fromPath(
                $file->getPathname(),
                method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null,
            ),
            is_string($file) => self::fromPath($file),
            is_resource($file) => self::fromStream($file),
            default => throw new PdfemberException('A file must be a path, an SplFileInfo, a stream resource or a Pdfember\Upload, got '.get_debug_type($file).'.'),
        };
    }
}
