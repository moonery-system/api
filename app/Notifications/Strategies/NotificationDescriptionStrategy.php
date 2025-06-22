<?php

namespace App\Notifications\Strategies;

interface NotificationDescriptionStrategy
{
    public function getDescription(array $context): string;
}