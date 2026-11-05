<?php

namespace Pdfember\Exceptions;

/** Too many requests or renders at once (429); see `$retryAfter`. */
class RateLimitException extends ApiException {}
