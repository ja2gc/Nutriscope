<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Audit\Revisions\Serializers\BudgetRevisionSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BudgetRevisionSerializerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_captures_safe_fiscal_year_totals_and_immutable_ledger_rows(): void
    {
        $actor = User::factory()->rnd()->create();
        $budget = Budget::factory()->create([
            'fiscal_year' => 2026,
            'allocated_amount' => 100000,
            'per_head_day_limit' => 250,
            'created_by' => $actor->id,
        ]);
        BudgetLedger::create([
            'fiscal_year' => 2026,
            'type' => 'manual_addition',
            'source' => 'manual',
            'amount' => 5000,
            'reason' => 'Supplemental allocation',
            'reference' => 'BUR-2026-01',
            'created_by' => $actor->id,
        ]);
        $po = PurchaseOrder::factory()->create(['po_number' => 'PO-2026-001']);
        BudgetLedger::create([
            'fiscal_year' => 2026,
            'type' => 'po_deduction',
            'source' => 'system',
            'amount' => 30000,
            'reference' => 'PO-2026-001',
            'purchase_order_id' => $po->id,
        ]);

        $serializer = new BudgetRevisionSerializer;
        $snapshot = $serializer->capture($budget);
        $presented = $serializer->present($snapshot->payload)->toArray();
        $encoded = json_encode($snapshot->payload, JSON_THROW_ON_ERROR);

        $this->assertSame('budget', $snapshot->serializer);
        $this->assertSame($budget->uuid, $snapshot->subjectPublicId);
        $this->assertSame(75000.0, $snapshot->payload['totals']['remaining_balance']);
        $this->assertSame('Supplemental allocation', $presented['tables'][0]['rows'][0]['values']['reason']['value']);
        $this->assertSame($po->uuid, $snapshot->payload['ledger'][1]['purchase_order_reference']);
        $this->assertSame(75000.0, $snapshot->payload['ledger'][1]['balance_after']);
        foreach (['created_by', 'purchase_order_id', 'po_deduction_guard', 'planning_payload', 'execution_payload'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_serializer_rejects_wrong_model_and_malformed_payload(): void
    {
        $serializer = new BudgetRevisionSerializer;
        try {
            $serializer->capture(FsItem::factory()->create());
            $this->fail('Wrong model unexpectedly serialized.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Budget serializer requires a budget.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid budget revision payload.');
        $serializer->present(['ledger' => ['RAW-LEDGER-SENTINEL']]);
    }
}
