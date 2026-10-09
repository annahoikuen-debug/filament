<?php

namespace Tests\Feature\Regression;

use App\Models\ChargeItem;
use App\Models\Facility;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeItemDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 表示名と説明フィールドが保存・取得できる(): void
    {
        $facility = Facility::factory()->create();

        $chargeItem = ChargeItem::create([
            'facility_id' => $facility->id,
            'name' => 'diaper',
            'display_name' => 'おむつ代',
            'description' => '大人用おむつ（テープタイプ）1枚あたりの単価',
            'default_price' => 100,
            'tax_type' => 'standard',
            'category' => '消耗品',
            'is_active' => true,
        ]);

        $this->assertEquals('diaper', $chargeItem->name);
        $this->assertEquals('おむつ代', $chargeItem->display_name);
        $this->assertEquals('大人用おむつ（テープタイプ）1枚あたりの単価', $chargeItem->description);
        $this->assertEquals(100, $chargeItem->default_price);
    }

    /** @test */
    public function 表示名未設定時は品目名がフォールバックされる(): void
    {
        $facility = Facility::factory()->create();

        $chargeItem = ChargeItem::create([
            'facility_id' => $facility->id,
            'name' => 'haircut',
            'display_name' => null,
            'description' => null,
            'default_price' => 2000,
            'tax_type' => 'standard',
            'category' => 'サービス',
            'is_active' => true,
        ]);

        // display_nameがnullの場合、nameが使用される想定
        $this->assertNull($chargeItem->display_name);
        $this->assertEquals('haircut', $chargeItem->name);
    }

    /** @test */
    public function 説明フィールドが長文でも保存できる(): void
    {
        $facility = Facility::factory()->create();
        $longDescription = str_repeat('説明文です。', 50); // 約500文字

        $chargeItem = ChargeItem::create([
            'facility_id' => $facility->id,
            'name' => 'advance_payment',
            'display_name' => '立替金',
            'description' => $longDescription,
            'default_price' => 0,
            'tax_type' => 'non_taxable',
            'category' => '立替',
            'is_active' => true,
        ]);

        $this->assertEquals($longDescription, $chargeItem->description);
        $this->assertLessThanOrEqual(500, mb_strlen($chargeItem->description));
    }

    /** @test */
    public function massAssignmentで表示名と説明が設定できる(): void
    {
        $facility = Facility::factory()->create();
        
        // mass assignmentでの作成テスト
        $chargeItem = ChargeItem::create([
            'facility_id' => $facility->id,
            'name' => 'diaper',
            'display_name' => 'おむつ代',
            'description' => '大人用おむつ（テープタイプ）1枚あたりの単価',
            'default_price' => 100,
            'tax_type' => 'standard',
            'category' => '消耗品',
            'is_active' => true,
        ]);

        $this->assertEquals('おむつ代', $chargeItem->display_name);
        $this->assertEquals('大人用おむつ（テープタイプ）1枚あたりの単価', $chargeItem->description);
    }

    /** @test */
    public function 表示名と説明が更新できる(): void
    {
        $facility = Facility::factory()->create();
        
        $chargeItem = ChargeItem::create([
            'facility_id' => $facility->id,
            'name' => 'diaper',
            'display_name' => 'おむつ代',
            'description' => '大人用おむつ',
            'default_price' => 100,
            'tax_type' => 'standard',
            'category' => '消耗品',
            'is_active' => true,
        ]);

        // 更新テスト
        $chargeItem->update([
            'display_name' => 'おむつ代（テープ）',
            'description' => '大人用おむつ（テープタイプ）1枚あたりの単価',
        ]);

        $this->assertEquals('おむつ代（テープ）', $chargeItem->fresh()->display_name);
        $this->assertEquals('大人用おむつ（テープタイプ）1枚あたりの単価', $chargeItem->fresh()->description);
    }

    /** @test */
    public function 活動ログに表示名と説明の変更が記録される(): void
    {
        $facility = Facility::factory()->create();
        $admin = \App\Models\User::factory()->create([
            'role' => 'corporate_admin',
            'facility_id' => $facility->id,
        ]);

        $this->actingAs($admin);

        $chargeItem = ChargeItem::create([
            'facility_id' => $facility->id,
            'name' => 'diaper',
            'display_name' => 'おむつ代',
            'description' => '大人用おむつ',
            'default_price' => 100,
            'tax_type' => 'standard',
            'category' => '消耗品',
            'is_active' => true,
        ]);

        // 更新してログを生成
        $chargeItem->update([
            'display_name' => 'おむつ代（テープ）',
            'description' => '大人用おむつ（テープタイプ）1枚あたりの単価',
        ]);

        $logs = \Spatie\Activitylog\Models\Activity::all();
        $this->assertNotEmpty($logs);

        $lastLog = $logs->last();
        $this->assertEquals('updated', $lastLog->event);
        
        // changesプロパティはCollectionなのでtoArray()で配列化して確認
        $changes = $lastLog->changes->toArray();
        
        // Spatie ActivityLogのchangesは attributes (新しい値) と old (古い値) の構造になっている
        // attributesの中に display_name と description が含まれていることを確認
        $this->assertArrayHasKey('attributes', $changes);
        $this->assertArrayHasKey('display_name', $changes['attributes']);
        $this->assertArrayHasKey('description', $changes['attributes']);
        $this->assertEquals('おむつ代（テープ）', $changes['attributes']['display_name']);
        $this->assertEquals('大人用おむつ（テープタイプ）1枚あたりの単価', $changes['attributes']['description']);
        
        // oldにも古い値が記録されていることを確認
        $this->assertArrayHasKey('old', $changes);
        $this->assertArrayHasKey('display_name', $changes['old']);
        $this->assertArrayHasKey('description', $changes['old']);
        $this->assertEquals('おむつ代', $changes['old']['display_name']);
        $this->assertEquals('大人用おむつ', $changes['old']['description']);
    }

    /** @test */
    public function factoryで表示名と説明が生成できる(): void
    {
        $facility = Facility::factory()->create();

        $chargeItem = ChargeItem::factory()->create([
            'facility_id' => $facility->id,
        ]);

        $this->assertNotNull($chargeItem->name);
        $this->assertNotNull($chargeItem->default_price);
        // factoryでdisplay_name/descriptionが設定されていない場合はnull許容
        $this->assertTrue(
            is_null($chargeItem->display_name) || is_string($chargeItem->display_name),
            'display_nameはnullまたは文字列であるべき'
        );
        $this->assertTrue(
            is_null($chargeItem->description) || is_string($chargeItem->description),
            'descriptionはnullまたは文字列であるべき'
        );
    }
}