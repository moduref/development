<?php

declare(strict_types=1);

namespace Moduref\Exceptions;

/**
 * Dilempar ketika nama identifier tabel atau kolom SQL tidak aman atau mengandung karakter berbahaya.
 */
class InvalidIdentifierException extends \InvalidArgumentException implements ModurefException
{
}
