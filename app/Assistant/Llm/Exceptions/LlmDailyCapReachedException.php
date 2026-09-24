<?php

namespace App\Assistant\Llm\Exceptions;

/**
 * The provider was not called at all: today's budget is already spent.
 */
class LlmDailyCapReachedException extends LlmException
{
}
