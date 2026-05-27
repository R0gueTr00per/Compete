<?php

namespace App\Notifications;

class Notification extends \Filament\Notifications\Notification
{
    protected int|string|\Closure $duration = 3000;
}
