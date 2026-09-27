<?php

namespace App\Console\Commands;

use App\Messaging\DlqInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The "simple alert": a non-zero exit code a cron job or health check can act on, plus a
 * warning in the structured log. The RabbitMQ management panel (:15672, already enabled)
 * shows the same depth for a human looking at Queues -> <name>.dlq, with no code needed.
 */
class DlqCheckCommand extends Command
{
    protected $signature = 'customs:dlq:check {queue : Short name, emails or assistant}';

    protected $description = 'Reports how many messages are waiting in a dead-letter queue; exits non-zero if any are';

    public function __construct(
        private DlqInspector $inspector
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $resolved = DlqInspector::resolve($this->argument('queue'));
        $depth = $this->inspector->depth($resolved['dlq']);

        if ($depth === 0) {
            $this->info("{$resolved['dlq']} is empty.");

            return Command::SUCCESS;
        }

        $this->warn("{$resolved['dlq']} has {$depth} message(s) waiting.");

        Log::warning('Dead-letter queue is not empty', ['queue' => $resolved['dlq'], 'depth' => $depth]);

        return Command::FAILURE;
    }
}
