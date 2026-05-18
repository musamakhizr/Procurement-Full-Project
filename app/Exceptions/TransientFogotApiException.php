<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class TransientFogotApiException extends RuntimeException
{
    public function __construct(
        public readonly string $path,
        public readonly ?int $status = null,
        public readonly int $attempts = 1,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
