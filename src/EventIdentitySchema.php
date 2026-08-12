<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Illuminate\Database\Capsule\Manager as DB;

final class EventIdentitySchema
{
    public const CURRENT_VERSION = 2;
    public const TABLE_EQUIVALENCES = 'hh_historic_event_equivalences';
    public const TABLE_INDEX = 'hh_historic_event_index';

    public function ensureSchema(int $currentVersion): int
    {
        if ($currentVersion < 1) {
            $this->upgradeToVersion1();
            $currentVersion = 1;
        }
        if ($currentVersion < 2) {
            $this->upgradeToVersion2();
            $currentVersion = 2;
        }

        return $currentVersion;
    }

    private function upgradeToVersion1(): void
    {
        if (!DB::schema()->hasTable(self::TABLE_EQUIVALENCES)) {
            DB::schema()->create(self::TABLE_EQUIVALENCES, static function ($table): void {
                $table->increments('id');
                $table->string('identity_a', 128);
                $table->string('identity_b', 128);
                $table->text('external_references')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['identity_a', 'identity_b'], 'hh_hist_event_eq_pair');
                $table->index('identity_a', 'hh_hist_event_eq_a');
                $table->index('identity_b', 'hh_hist_event_eq_b');
            });
        }

        if (!DB::schema()->hasTable(self::TABLE_INDEX)) {
            DB::schema()->create(self::TABLE_INDEX, static function ($table): void {
                $table->increments('id');
                $table->string('event_identity', 128);
                $table->string('provider_id', 128);
                $table->string('collection_id', 255);
                $table->string('source_location', 255);
                $table->char('event_hash', 40);
                $table->string('event_label', 255);
                $table->string('event_date', 80)->nullable();
                $table->timestamp('indexed_at')->useCurrent();

                $table->unique(['event_identity', 'provider_id', 'collection_id', 'source_location'], 'hh_hist_event_idx_source');
                $table->index('event_identity', 'hh_hist_event_idx_identity');
            });
        }
    }

    private function upgradeToVersion2(): void
    {
        if (DB::schema()->hasTable(self::TABLE_INDEX) && !DB::schema()->hasColumn(self::TABLE_INDEX, 'event_date')) {
            DB::schema()->table(self::TABLE_INDEX, static function ($table): void {
                $table->string('event_date', 80)->nullable()->after('event_label');
            });
        }
    }
}
