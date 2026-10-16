<?php

declare(strict_types=1);

namespace Moduref\Exceptions;

/**
 * Dilempar ketika nilai string bukan merupakan format UUID v1-v5 yang valid.
 */
class InvalidUuidException extends \InvalidArgumentException implements ModurefException
{
    public static function forField(string $label): self
    {
        return new self("{$label} must be a valid UUID string");
    }
}
