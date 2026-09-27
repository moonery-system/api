<?php

namespace App\Console\Commands;

use App\Messaging\DlqInspector;
use App\Services\RabbitMQPublisher;
use Illuminate\Console\Command;

class DlqReplayCommand extends Command
{
    protected $signature = 'customs:dlq:replay {queue : Short name, emails or assistant} {--limit=1} {--all}';

    protected $description = 'Republishes messages waiting in a dead-letter queue back onto their original routing key';

    public function __construct(
        private DlqInspector $inspector,
        private RabbitMQPublisher $publisher
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $resolved = DlqInspector::resolve($this->argument('queue'));
        $limit = $this->option('all') ? PHP_INT_MAX : (int) $this->option('limit');

        $outcomes = $this->inspector->replay($resolved['dlq'], $limit, $this->publisher, $resolved['default_routing_key']);

        foreach ($outcomes as $outcome) {
            $this->line($outcome);
        }

        $this->info(count($outcomes) . ' message(s) processed.');

        return Command::SUCCESS;
    }
}
