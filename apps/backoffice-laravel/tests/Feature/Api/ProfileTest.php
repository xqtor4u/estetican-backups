<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(User $user): array
    {
        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'usertest'.uniqid(),
            'first_name' => 'Ana',
            'last_name' => 'Ruiz',
            'email' => 'profile-test'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
        ], $overrides));
    }

    public function test_update_changes_name_and_email(): void
    {
        $user = $this->makeUser();
        $headers = $this->loginAs($user);

        $response = $this->patchJson('/api/me', [
            'first_name' => 'Anita',
            'last_name'  => 'Ruiz Gómez',
            'email'      => 'anita.updated@example.com',
        ], $headers);

        $response->assertOk();
        $response->assertJsonFragment(['first_name' => 'Anita', 'last_name' => 'Ruiz Gómez', 'email' => 'anita.updated@example.com']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'anita.updated@example.com']);
    }

    public function test_update_rejects_email_already_used_by_another_user(): void
    {
        $this->makeUser(['email' => 'taken@example.com']);
        $user = $this->makeUser();
        $headers = $this->loginAs($user);

        $response = $this->patchJson('/api/me', [
            'first_name' => 'Ana',
            'email'      => 'taken@example.com',
        ], $headers);

        $response->assertStatus(422);
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = $this->makeUser();
        $headers = $this->loginAs($user);

        $response = $this->putJson('/api/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'nueva123',
            'password_confirmation' => 'nueva123',
        ], $headers);

        $response->assertStatus(422);
    }

    public function test_password_update_succeeds_with_correct_current_password(): void
    {
        $user = $this->makeUser();
        $headers = $this->loginAs($user);

        $response = $this->putJson('/api/me/password', [
            'current_password' => 'secret123',
            'password' => 'nueva123',
            'password_confirmation' => 'nueva123',
        ], $headers);

        $response->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nueva123', $user->fresh()->password));
    }

    public function test_verify_password_succeeds_with_correct_password_and_does_not_change_it(): void
    {
        $user = $this->makeUser();
        $headers = $this->loginAs($user);

        $response = $this->postJson('/api/me/verify-password', ['password' => 'secret123'], $headers);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret123', $user->fresh()->password));
    }

    public function test_verify_password_rejects_wrong_password(): void
    {
        $user = $this->makeUser();
        $headers = $this->loginAs($user);

        $response = $this->postJson('/api/me/verify-password', ['password' => 'wrong-one'], $headers);

        $response->assertStatus(422);
    }

    public function test_photo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $headers = $this->loginAs($user);

        $upload = $this->post('/api/me/photo', [
            'photo' => UploadedFile::fake()->image('foto.jpg', 800, 800),
        ], $headers);

        $upload->assertOk();
        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        $upload->assertJsonFragment(['photo_url' => $user->profile_photo_url]);

        $delete = $this->delete('/api/me/photo', [], $headers);
        $delete->assertOk();
        $this->assertNull($user->fresh()->profile_photo_path);
    }
}
