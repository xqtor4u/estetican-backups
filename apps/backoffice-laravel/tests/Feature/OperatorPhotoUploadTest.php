<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Operator;
use App\Models\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperatorPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_be_created_with_profile_photo(): void
    {
        Storage::fake('public');

        $branch = Branch::create([
            'code' => 'MTY-CEN',
            'name' => 'Monterrey Centro',
            'is_active' => true,
        ]);

        $role = OperatorRole::create([
            'code' => 'GROOM',
            'name' => 'Groomer',
            'is_active' => true,
        ]);

        $response = $this->post(route('operators.store'), [
            'code' => 'OP-100',
            'first_name' => 'Marina',
            'apellido_paterno' => 'Soto',
            'role_ids' => [$role->id],
            'branch_id' => $branch->id,
            'profile_photo' => UploadedFile::fake()->image('marina.jpg', 600, 600),
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('operators.index'));

        $operator = Operator::query()->where('code', 'OP-100')->firstOrFail();

        $this->assertNotNull($operator->profile_photo_path);
        Storage::disk('public')->assertExists($operator->profile_photo_path);
        Storage::disk('public')->assertExists($operator->profile_photo_thumbnail_path);

        $indexResponse = $this->get(route('operators.index'));
        $showResponse = $this->get(route('operators.show', $operator));

        $indexResponse->assertOk();
        $indexResponse->assertSee('/storage/' . $operator->profile_photo_thumbnail_path);

        $showResponse->assertOk();
        $showResponse->assertSee('/storage/' . $operator->profile_photo_path);
    }

    public function test_operator_photo_can_be_removed_on_update(): void
    {
        Storage::fake('public');

        $branch = Branch::create([
            'code' => 'CDMX-NTE',
            'name' => 'CDMX Norte',
            'is_active' => true,
        ]);

        $role = OperatorRole::create([
            'code' => 'BATH',
            'name' => 'Bañado',
            'is_active' => true,
        ]);

        $operator = Operator::create([
            'code' => 'OP-101',
            'first_name' => 'Nora',
            'apellido_paterno' => 'Peña',
            'name' => 'Nora Peña',
            'profile_photo_path' => 'operator-photos/2026/03/original/nora.jpg',
            'is_active' => true,
        ]);

        $operator->roleAssignments()->create([
            'operator_role_id' => $role->id,
            'is_primary' => true,
            'starts_at' => now(),
        ]);

        $operator->branchAssignments()->create([
            'branch_id' => $branch->id,
            'is_primary' => true,
            'starts_at' => now(),
        ]);

        Storage::disk('public')->put($operator->profile_photo_path, 'fake-main');
        Storage::disk('public')->put($operator->profile_photo_thumbnail_path, 'fake-thumb');

        $response = $this->put(route('operators.update', $operator), [
            'code' => 'OP-101',
            'first_name' => 'Nora',
            'apellido_paterno' => 'Peña',
            'role_ids' => [$role->id],
            'branch_id' => $branch->id,
            'remove_profile_photo' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('operators.edit', $operator));

        $operator->refresh();

        $this->assertNull($operator->profile_photo_path);
        Storage::disk('public')->assertMissing('operator-photos/2026/03/original/nora.jpg');
        Storage::disk('public')->assertMissing('operator-photos/2026/03/thumbs/nora.jpg');
    }
}