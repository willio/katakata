<?php

declare(strict_types=1);

namespace Katakata\Content;

use RuntimeException;

/**
 * Thrown when a content file fails validation.
 *
 * The Repository catches this internally: an invalid file is skipped
 * and recorded via Repository::errors() rather than crashing the
 * whole build, so one malformed post can't take down the archive.
 */
final class ContentValidationException extends RuntimeException
{
}
