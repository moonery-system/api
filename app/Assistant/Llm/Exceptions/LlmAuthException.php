<?php

namespace App\Assistant\Llm\Exceptions;

/**
 * The provider refused the credentials (400/401/403). Retrying cannot fix it.
 */
class LlmAuthException extends LlmException
{
}
