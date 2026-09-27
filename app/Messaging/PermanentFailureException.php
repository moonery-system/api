<?php

namespace App\Messaging;

use RuntimeException;

/**
 * Thrown deliberately by consumer code for a failure retrying cannot fix: a payload that
 * is not valid JSON, or one that names a routing key nothing handles. RetryPolicy sends it
 * straight to the dead-letter queue, spending no retry tier on it.
 */
class PermanentFailureException extends RuntimeException
{
}
