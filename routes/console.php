<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Penjadwalan
|--------------------------------------------------------------------------
| Jalankan `php artisan schedule:work` (lokal) atau cron `schedule:run` setiap menit (produksi).
*/

Schedule::command('monev:remind')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
Schedule::command('quality:remind')->dailyAt('07:30')->withoutOverlapping()->onOneServer();
Schedule::command('backup:database')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
