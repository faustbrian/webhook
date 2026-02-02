<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Exceptions;

use RuntimeException;

/**
 * Exception thrown during test webhook processing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class TestProcessingException extends RuntimeException
{
    public static function simulatedFailure(): self
    {
        return new self('Test processing failure');
    }

    public static function withSpecialCharacters(): self
    {
        return new self('Error with special chars: <>&"\'');
    }
}
