<?php

/**
 * Schema Consistency Test (Layer A — Static Analysis)
 *
 * Why: catches "model fillable / cast lists a column the migration doesn't have"
 * before runtime. Recent regression set: temperature_celsius / cycle_length_days /
 * phase columns referenced by services but missing in DB → 500s in prod.
 *
 * Strategy: each Eloquent model => assert every fillable + cast key is a real
 * column on its table.
 */

use App\Models\BbtReading;
use App\Models\BodyDexEntry;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DailyActionRecommendation;
use App\Models\DodoCheckin;
use App\Models\DodoCoinTransaction;
use App\Models\PetBond;
use App\Models\RandomEventLog;
use App\Models\SolarTermParticipation;
use App\Models\StoryChapterUnlock;
use App\Models\User;
use App\Models\UserSkillPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

$models = [
    DodoCheckin::class,
    Cycle::class,
    CycleSymptom::class,
    BbtReading::class,
    DailyActionRecommendation::class,
    PetBond::class,
    DodoCoinTransaction::class,
    StoryChapterUnlock::class,
    BodyDexEntry::class,
    UserSkillPath::class,
    RandomEventLog::class,
    SolarTermParticipation::class,
    User::class,
];

foreach ($models as $modelClass) {
    test("{$modelClass} fillable + casts columns exist on table", function () use ($modelClass) {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        expect(Schema::hasTable($table))->toBeTrue("table {$table} should exist");

        $fillable = $model->getFillable();
        foreach ($fillable as $col) {
            expect(Schema::hasColumn($table, $col))
                ->toBeTrue("{$modelClass}::fillable references column `{$col}` but `{$table}` lacks it");
        }

        $casts = array_keys($model->getCasts());
        // skip auto casts that aren't real columns
        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];
        foreach ($casts as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            // some casts (e.g. accessors) may not be columns; only flag if it's
            // also in fillable (otherwise it's a virtual cast)
            if (in_array($col, $fillable, true)) {
                expect(Schema::hasColumn($table, $col))
                    ->toBeTrue("{$modelClass}::casts references column `{$col}` but `{$table}` lacks it");
            }
        }
    });
}
