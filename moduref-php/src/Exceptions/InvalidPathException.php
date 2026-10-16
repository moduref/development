<?php

declare(strict_types=1);

namespace Moduref\Exceptions;

/**
 * Dilempar ketika format path URL lokal tidak valid (contoh: tidak diawali '/', mengandung '//', atau traversal).
 */
class InvalidPathException extends \InvalidArgumentException implements ModurefException
{
}
