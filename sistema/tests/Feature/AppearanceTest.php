<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preference_is_validated_saved_for_current_user_and_sets_readable_cookie(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $other = User::factory()->create();
        foreach (['light', 'dark', 'system'] as $appearance) {
            $this->actingAs($user)->patchJson('/admin/settings/appearance', compact('appearance'))
                ->assertOk()->assertJsonPath('appearance', $appearance)->assertPlainCookie('appearance', $appearance);
            $this->assertSame($appearance, $user->fresh()->appearance);
            $this->assertSame('system', $other->fresh()->appearance);
        }
        $this->patchJson('/admin/settings/appearance', ['appearance' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('appearance');
    }

    public function test_saved_theme_wins_over_device_cookie_on_initial_load_and_system_has_early_script(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $user->forceFill(['appearance' => 'dark'])->save();
        $this->actingAs($user)->withUnencryptedCookie('appearance', 'light')->get('/admin/settings/appearance')
            ->assertOk()->assertSee('data-appearance="dark"', false)->assertSee('class="dark"', false);
        $user->forceFill(['appearance' => 'system'])->save();
        $this->get('/admin/settings/appearance')->assertSee('data-appearance="system"', false)
            ->assertSee("window.matchMedia('(prefers-color-scheme: dark)')", false);
    }

    public function test_guests_cannot_save_and_login_is_forced_dark(): void
    {
        $this->patchJson('/admin/settings/appearance', ['appearance' => 'light'])->assertUnauthorized();
        $this->withUnencryptedCookie('appearance', 'light')->get('/login')->assertOk()->assertSee('data-theme-forced-dark="true"', false)->assertSee('class="dark"', false);
    }
}
