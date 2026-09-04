<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\MysqlDatabaseRestoreManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MysqlDatabasePromotionTest extends TestCase
{
    #[Test]
    public function it_transactionally_promotes_application_data_while_preserving_recovery_control_rows(): void
    {
        $original = config('database.connections.mysql.database');
        $live = 'nutriscope_switch_test_live';
        $candidate = 'nutriscope_recovery_abcdef012345';
        $connection = DB::connection('mysql');

        try {
            $connection->statement("DROP DATABASE IF EXISTS `{$live}`");
            $connection->statement("DROP DATABASE IF EXISTS `{$candidate}`");
            $connection->statement("CREATE DATABASE `{$live}`");
            $connection->statement("CREATE DATABASE `{$candidate}`");
            foreach ([$live, $candidate] as $database) {
                $connection->statement("CREATE TABLE `{$database}`.`users` (id BIGINT UNSIGNED PRIMARY KEY)");
                $connection->statement("CREATE TABLE `{$database}`.`stored_objects` (id BIGINT UNSIGNED PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE)");
                $connection->statement("CREATE TABLE `{$database}`.`recovery_requests` (id BIGINT UNSIGNED PRIMARY KEY, requested_by BIGINT UNSIGNED NOT NULL)");
                $connection->statement("CREATE TABLE `{$database}`.`backup_runs` (id BIGINT UNSIGNED PRIMARY KEY, requested_by BIGINT UNSIGNED NULL)");
                $connection->statement("CREATE TABLE `{$database}`.`backup_manifest_objects` (id BIGINT UNSIGNED PRIMARY KEY, stored_object_id BIGINT UNSIGNED NULL, stored_object_uuid CHAR(36) NOT NULL)");
                $connection->statement("CREATE TABLE `{$database}`.`domain_rows` (id BIGINT UNSIGNED PRIMARY KEY, label VARCHAR(32) NOT NULL)");
                $connection->statement("INSERT INTO `{$database}`.`users` VALUES (1)");
            }
            $uuid = '11111111-1111-1111-1111-111111111111';
            $connection->statement("INSERT INTO `{$live}`.`domain_rows` VALUES (1, 'current')");
            $connection->statement("INSERT INTO `{$candidate}`.`domain_rows` VALUES (1, 'restored')");
            $connection->statement("INSERT INTO `{$candidate}`.`stored_objects` VALUES (7, '{$uuid}')");
            $connection->statement("INSERT INTO `{$live}`.`backup_runs` VALUES (1, 999)");
            $connection->statement("INSERT INTO `{$live}`.`backup_manifest_objects` VALUES (1, 999, '{$uuid}')");
            Config::set('database.connections.mysql.database', $live);

            app(MysqlDatabaseRestoreManager::class)->promoteTemporary([
                'name' => $candidate,
                'disposable' => true,
                'promotable' => true,
                'connection' => 'recovery_candidate',
            ]);

            $this->assertSame('restored', DB::connection('mysql')->table('domain_rows')->value('label'));
            $this->assertNull(DB::connection('mysql')->table('backup_runs')->value('requested_by'));
            $this->assertSame(7, DB::connection('mysql')->table('backup_manifest_objects')->value('stored_object_id'));
        } finally {
            Config::set('database.connections.mysql.database', $original);
            DB::purge('mysql');
            DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$live}`");
            DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$candidate}`");
        }
    }
}
