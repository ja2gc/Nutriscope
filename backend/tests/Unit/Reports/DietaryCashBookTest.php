<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\Generators\DietaryCashBookGenerator;
use PHPUnit\Framework\TestCase;

class DietaryCashBookTest extends TestCase
{
    public function test_running_balance_adds_replenishment_and_subtracts_disbursement(): void
    {
        $entries = [
            ['date' => '2026-05-01', 'ref' => 'OR-1', 'payee' => 'Cash Advance', 'nature' => 'Replenishment', 'replenishment' => 50000, 'disbursement' => 0],
            ['date' => '2026-05-02', 'ref' => 'OR-2', 'payee' => 'MACMA Trading', 'nature' => 'meat', 'replenishment' => 0, 'disbursement' => 12000],
            ['date' => '2026-05-03', 'ref' => 'OR-3', 'payee' => 'RPDH-MPC', 'nature' => 'groceries', 'replenishment' => 0, 'disbursement' => 8000],
        ];

        $out = DietaryCashBookGenerator::ledger($entries, 1000.0);

        $this->assertSame(1000.0, $out['beginning_balance']);
        $this->assertEqualsWithDelta(51000.0, $out['rows'][0]['balance'], 0.001);
        $this->assertEqualsWithDelta(39000.0, $out['rows'][1]['balance'], 0.001);
        $this->assertEqualsWithDelta(31000.0, $out['rows'][2]['balance'], 0.001);

        $this->assertEqualsWithDelta(50000.0, $out['total_replenishment'], 0.001);
        $this->assertEqualsWithDelta(20000.0, $out['total_disbursement'], 0.001);
        $this->assertEqualsWithDelta(31000.0, $out['ending_balance'], 0.001);
    }

    public function test_empty_ledger_returns_beginning_as_ending(): void
    {
        $out = DietaryCashBookGenerator::ledger([], 2500.0);

        $this->assertSame([], $out['rows']);
        $this->assertSame(2500.0, $out['ending_balance']);
        $this->assertSame(0.0, $out['total_disbursement']);
    }
}
