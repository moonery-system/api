<?php

namespace App\Console\Commands;

use App\Messaging\DlqInspector;
use Illuminate\Console\Command;

class DlqListCommand extends Command
{
    protected $signature = 'customs:dlq:list {queue : Short name, emails or assistant} {--limit=20}';

    protected $description = 'Lists, without removing, the messages waiting in a dead-letter queue';

    public function __construct(
        private DlqInspector $inspector
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $resolved = DlqInspector::resolve($this->argument('queue'));
        $limit = (int) $this->option('limit');

        $depth = $this->inspector->depth($resolved['dlq']);
        $this->info("{$resolved['dlq']}: {$depth} message(s) total, showing up to {$limit}.");

        foreach ($this->inspector->peek($resolved['dlq'], $limit) as $envelope) {
            $this->line(sprintf(
                '- routing_key=%s attempts=%s failed_at=%s error=%s',
                $envelope['original_routing_key'] ?? 'n/a',
                $envelope['attempts'] ?? 'n/a',
                $envelope['failed_at'] ?? 'n/a',
                mb_substr((string) ($envelope['error'] ?? ''), 0, 120)
            ));
        }

        return Command::SUCCESS;
    }
}
