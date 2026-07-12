<?php

namespace Tests\Support;

use App\Services\Audit\AuditRetentionService;
use Illuminate\Support\Facades\DB;
use LogicException;
use ReflectionMethod;

final class AuditFixture
{
    public static function delete(object $query): int
    {
        if (! app()->runningUnitTests()) {
            throw new LogicException('Audit fixture deletion is available only to the test runtime.');
        }

        $connection = DB::connection(config('activitylog.database_connection'));
        $scope = new ReflectionMethod(AuditRetentionService::class, 'withAuthorizedDeletion');

        return $scope->invoke(
            app(AuditRetentionService::class),
            $connection,
            fn (): int => $query->toBase()->delete(),
        );
    }
}
