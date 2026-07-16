<?php

namespace Tests\Unit;

use App\Models\FsItem;
use App\Models\MenuCycleTemplate;
use App\Models\MenuCycleTemplateDay;
use App\Models\User;
use App\Services\Audit\Revisions\Serializers\MenuCycleTemplateRevisionSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MenuCycleTemplateRevisionSerializerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_captures_only_the_safe_complete_template_structure(): void
    {
        $user = User::factory()->rnd()->create();
        $item = FsItem::factory()->create(['name' => 'Brown rice', 'base_unit' => 'kg']);
        $template = MenuCycleTemplate::create([
            'rnd_user_id' => $user->id,
            'name' => 'Standard ward menu',
            'description' => 'DESCRIPTION-SENTINEL',
            'cycle_days' => 7,
        ]);
        MenuCycleTemplateDay::create([
            'template_id' => $template->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
            'quantity' => 2,
        ]);

        $serializer = new MenuCycleTemplateRevisionSerializer;
        $snapshot = $serializer->capture($template);
        $presented = $serializer->present($snapshot->payload)->toArray();

        $this->assertSame('menu_cycle_template', $snapshot->serializer);
        $this->assertSame($template->uuid, $snapshot->subjectPublicId);
        $this->assertSame('Brown rice', $snapshot->payload['slots'][0]['item']);
        $this->assertSame('kg', $snapshot->payload['slots'][0]['unit']);
        $this->assertSame('Brown rice', $presented['tables'][0]['rows'][0]['values']['item']['value']);
        $encoded = json_encode($snapshot->payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('DESCRIPTION-SENTINEL', $encoded);
        $this->assertStringNotContainsString('template_id', $encoded);
        $this->assertStringNotContainsString('rnd_user_id', $encoded);
    }

    public function test_serializer_rejects_wrong_model_and_malformed_payload(): void
    {
        $serializer = new MenuCycleTemplateRevisionSerializer;

        try {
            $serializer->capture(FsItem::factory()->create());
            $this->fail('Wrong model was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Menu cycle template serializer requires a menu cycle template.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid menu cycle template revision payload.');
        $serializer->present(['slots' => ['RAW-NESTED-SENTINEL']]);
    }
}
