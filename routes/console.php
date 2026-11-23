<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('generations:reconcile')->everyMinute()->withoutOverlapping();
