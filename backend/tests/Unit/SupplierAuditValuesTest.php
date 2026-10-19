<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Audit\SupplierAuditValues;
use PHPUnit\Framework\TestCase;

class SupplierAuditValuesTest extends TestCase
{
    public function test_changing_the_preferred_method_is_audited_even_when_values_match(): void
    {
        $supplier = new Supplier([
            'name' => 'Demo vendor',
            'additional_contacts' => [
                ['id' => 'first', 'method' => 'phone', 'label' => null, 'value' => '12345'],
                ['id' => 'second', 'method' => 'messenger', 'label' => null, 'value' => '12345'],
            ],
            'preferred_contact_id' => 'first',
        ]);
        $audit = new SupplierAuditValues;
        $before = $audit->values($supplier);
        $supplier->preferred_contact_id = 'second';
        $after = $audit->values($supplier);
        $this->assertContains('preferred_contact', $audit->changedFields($before, $after));
        $this->assertNotSame($before['preferred_contact'], $after['preferred_contact']);
    }
}
