<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('events:remind')->everyFiveMinutes()->withoutOverlapping();
