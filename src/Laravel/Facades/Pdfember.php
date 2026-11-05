<?php

namespace Pdfember\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Pdfember\Document html(string $html, array $options = [])
 * @method static \Pdfember\Document url(string $url, array $options = [])
 * @method static \Pdfember\Document render(array $options)
 * @method static \Pdfember\Document tool(\Pdfember\Tool|string $tool, mixed $files, array $options = [])
 * @method static \Pdfember\Document merge(array $files, array $options = [])
 * @method static \Pdfember\Usage usage()
 *
 * @see \Pdfember\Pdfember
 */
class Pdfember extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Pdfember\Pdfember::class;
    }
}
