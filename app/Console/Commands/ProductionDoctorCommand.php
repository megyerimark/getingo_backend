<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionDoctorCommand extends Command
{
    protected $signature = 'app:doctor';
    protected $description = 'Ellenőrzi a Getingo éles futtatásához szükséges kritikus adatbázis-elemeket.';

    public function handle(): int
    {
        $failed = false;
        try {
            DB::select('select 1');
            $this->components->info('Adatbázis-kapcsolat: rendben');
        } catch (Throwable $exception) {
            $this->components->error('Adatbázis-kapcsolat: HIBA - '.$exception->getMessage());
            return self::FAILURE;
        }

        $checks = [
            ['projects', null, 'projects tábla'],
            ['projects', 'xp_reward', 'projects.xp_reward oszlop'],
            ['lesson_sections', null, 'lesson_sections tábla'],
            ['lessons', 'lesson_section_id', 'lessons.lesson_section_id oszlop'],
            ['lessons', 'sort_order', 'lessons.sort_order oszlop'],
        ];

        foreach ($checks as [$table, $column, $label]) {
            $ok = $column ? Schema::hasColumn($table, $column) : Schema::hasTable($table);
            $ok ? $this->components->info($label.': rendben') : $this->components->error($label.': HIÁNYZIK');
            $failed = $failed || ! $ok;
        }

        if ($failed) {
            $this->newLine();
            $this->components->warn('Futtasd: php artisan migrate --force');
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('A kritikus Getingo sémaellenőrzések sikeresek.');
        return self::SUCCESS;
    }
}
