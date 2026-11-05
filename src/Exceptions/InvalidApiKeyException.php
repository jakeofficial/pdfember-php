<?php

namespace Pdfember\Exceptions;

/** No API key, or one that is not a pdfember key, was given to the client. */
class InvalidApiKeyException extends PdfemberException
{
    public static function missing(): self
    {
        return new self('A pdfember API key is required. Create one at https://pdfember.com/dashboard and pass it to the client (in Laravel: set PDFEMBER_API_KEY in .env).');
    }

    public static function malformed(): self
    {
        return new self('This is not a pdfember API key: keys start with "pdx_". Create one at https://pdfember.com/dashboard.');
    }
}
