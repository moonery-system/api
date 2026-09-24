<?php

namespace App\Assistant\Llm\Exceptions;

use RuntimeException;

/**
 * Anything that stops the model from answering. The message never carries the API key.
 */
class LlmException extends RuntimeException
{
}
