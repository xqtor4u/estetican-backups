<?php

namespace Tests\Feature;

use App\Domain\MetaCatalog\Contracts\MetaCatalogSyncServiceInterface;
use App\Models\Item;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * BL-052: publicación de Artículos hacia el catálogo de Meta Commerce Manager.
 * Sin catálogo/token reales todavía (ver BITACORA 18/07/2026) — estas pruebas fijan
 * la forma del request saliente vía Http::fake(), no verifican contra la API real de Meta.
 */
class MetaCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    private function configureCatalog(): void
    {
        app(SystemSettings::class)->save('whatsapp_catalog', [
            'whatsapp_catalog_id' => '123456789',
            'whatsapp_catalog_access_token' => 'test-token',
        ]);
        app(SystemSettings::class)->saveFields('branding', ['brand_whatsapp_number' => '5215512345678']);
    }

    private function eligibleItem(array $overrides = []): Item
    {
        return Item::create(array_merge([
            'name' => 'Shampoo hipoalergénico',
            'brand' => 'PetClean',
            'presentation' => 'Frasco 500ml',
            'price' => 150,
            'stock_quantity' => 5,
            'is_active' => true,
            'ai_visible' => true,
            'photo_path' => 'items/original/shampoo.jpg',
        ], $overrides));
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Catalogo Test',
            'first_name' => 'Catalogo',
            'apellido_paterno' => 'Test',
            'email' => 'catalogo-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_sync_publishes_an_eligible_item_with_the_expected_payload(): void
    {
        $this->configureCatalog();
        $item = $this->eligibleItem();
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-product-1'], 200)]);

        $result = app(MetaCatalogSyncServiceInterface::class)->sync();

        $this->assertSame(['Shampoo hipoalergénico'], $result['published']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame([], $result['failed']);

        Http::assertSent(function ($request) use ($item) {
            return str_contains($request->url(), '123456789/products')
                && $request['retailer_id'] === "item-{$item->id}"
                && $request['name'] === 'Shampoo hipoalergénico'
                && $request['price'] === 15000
                && $request['availability'] === 'in stock'
                && str_contains($request['image_url'], 'shampoo.jpg')
                && str_contains($request['url'], 'wa.me/525512345678');
        });
    }

    public function test_sync_skips_items_without_photo(): void
    {
        $this->configureCatalog();
        $this->eligibleItem(['photo_path' => null]);
        Http::fake();

        $result = app(MetaCatalogSyncServiceInterface::class)->sync();

        $this->assertSame([], $result['published']);
        $this->assertSame([['item' => 'Shampoo hipoalergénico', 'reason' => 'sin foto']], $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_sync_skips_items_without_price(): void
    {
        $this->configureCatalog();
        $this->eligibleItem(['price' => null]);
        Http::fake();

        $result = app(MetaCatalogSyncServiceInterface::class)->sync();

        $this->assertSame([], $result['published']);
        $this->assertSame([['item' => 'Shampoo hipoalergénico', 'reason' => 'sin precio']], $result['skipped']);
    }

    public function test_sync_excludes_items_not_visible_to_ai_inactive_or_without_stock(): void
    {
        $this->configureCatalog();
        $this->eligibleItem(['ai_visible' => false, 'name' => 'No visible']);
        $this->eligibleItem(['is_active' => false, 'name' => 'Inactivo']);
        $this->eligibleItem(['stock_quantity' => 0, 'name' => 'Sin stock']);
        Http::fake();

        $result = app(MetaCatalogSyncServiceInterface::class)->sync();

        $this->assertSame([], $result['published']);
        $this->assertSame([], $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_sync_records_a_failed_item_when_meta_rejects_it(): void
    {
        $this->configureCatalog();
        $this->eligibleItem();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400)]);

        $result = app(MetaCatalogSyncServiceInterface::class)->sync();

        $this->assertSame([], $result['published']);
        $this->assertSame([['item' => 'Shampoo hipoalergénico', 'reason' => 'Invalid parameter']], $result['failed']);
    }

    public function test_sync_includes_category_when_set(): void
    {
        $this->configureCatalog();
        $this->eligibleItem(['meta_category' => 'Animals & Pet Supplies > Pet Supplies > Pet ID Tags']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-product-1'], 200)]);

        app(MetaCatalogSyncServiceInterface::class)->sync();

        Http::assertSent(fn ($request) => $request['category'] === 'Animals & Pet Supplies > Pet Supplies > Pet ID Tags');
    }

    public function test_sync_omits_category_when_not_set(): void
    {
        $this->configureCatalog();
        $this->eligibleItem();
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-product-1'], 200)]);

        app(MetaCatalogSyncServiceInterface::class)->sync();

        Http::assertSent(fn ($request) => ! array_key_exists('category', $request->data()));
    }

    public function test_sync_includes_variant_group_and_color_when_both_set(): void
    {
        $this->configureCatalog();
        $red = $this->eligibleItem(['name' => 'ID TAG Rojo', 'meta_variant_group' => 'id-tag-38mm', 'meta_color' => 'Rojo']);
        $blue = $this->eligibleItem(['name' => 'ID TAG Azul', 'meta_variant_group' => 'id-tag-38mm', 'meta_color' => 'Azul']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-product-1'], 200)]);

        app(MetaCatalogSyncServiceInterface::class)->sync();

        Http::assertSent(fn ($request) => $request['retailer_id'] === "item-{$red->id}"
            && $request['retailer_product_group_id'] === 'id-tag-38mm'
            && $request['color'] === 'Rojo');
        Http::assertSent(fn ($request) => $request['retailer_id'] === "item-{$blue->id}"
            && $request['retailer_product_group_id'] === 'id-tag-38mm'
            && $request['color'] === 'Azul');
    }

    public function test_sync_omits_variant_fields_when_group_set_without_color(): void
    {
        $this->configureCatalog();
        $this->eligibleItem(['meta_variant_group' => 'id-tag-38mm', 'meta_color' => null]);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-product-1'], 200)]);

        $result = app(MetaCatalogSyncServiceInterface::class)->sync();

        $this->assertSame(['Shampoo hipoalergénico'], $result['published']);
        Http::assertSent(fn ($request) => ! array_key_exists('retailer_product_group_id', $request->data())
            && ! array_key_exists('color', $request->data()));
    }

    public function test_sync_omits_variant_fields_when_color_set_without_group(): void
    {
        $this->configureCatalog();
        $this->eligibleItem(['meta_variant_group' => null, 'meta_color' => 'Rojo']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-product-1'], 200)]);

        $result = app(MetaCatalogSyncServiceInterface::class)->sync();

        $this->assertSame(['Shampoo hipoalergénico'], $result['published']);
        Http::assertSent(fn ($request) => ! array_key_exists('retailer_product_group_id', $request->data())
            && ! array_key_exists('color', $request->data()));
    }

    public function test_the_sync_button_requires_catalog_credentials_to_be_configured(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $this->eligibleItem();
        Http::fake();

        $response = $this->actingAs($user)->post(route('items.catalog-sync'));

        $response->assertRedirect(route('items.index'));
        $response->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_the_sync_endpoint_requires_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $this->configureCatalog();

        $this->actingAs($user)->post(route('items.catalog-sync'))->assertForbidden();
    }
}
