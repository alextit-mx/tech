<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    public function test_owner_uploads_each_logo_and_they_are_used_everywhere(): void
    {
        Storage::fake('uploads');
        $owner = $this->owner();

        $this->actingAs($owner)->post('/admin/settings/logos/site', ['image' => UploadedFile::fake()->image('logo.png', 1600, 600)])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/admin/settings/logos/pdf', ['image' => UploadedFile::fake()->image('pdf.jpg', 900, 300)])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/admin/settings/logos/icon', ['image' => UploadedFile::fake()->image('icon.png', 512, 512)])->assertRedirect()->assertSessionHasNoErrors();

        $site = setting('logo_site');
        $this->assertMatchesRegularExpression('~^branding/[a-f0-9-]+\.png$~', $site);
        Storage::disk('uploads')->assertExists($site);
        $this->assertSame(800, getimagesize(Storage::disk('uploads')->path($site))[0]);

        $this->assertStringEndsWith('.png', Branding::pdfLogoPath());
        $this->assertStringContainsString('branding/', Branding::pdfLogoPath());

        $icon = setting('logo_icon');
        foreach ([32, 180, 512] as $size) {
            $variant = str_replace('-512.png', "-{$size}.png", $icon);
            Storage::disk('uploads')->assertExists($variant);
            $this->assertSame([$size, $size], array_slice(getimagesize(Storage::disk('uploads')->path($variant)), 0, 2));
        }

        $this->get('/contacto')->assertOk()
            ->assertSee(basename($site), false)
            ->assertSee(basename(str_replace('-512.png', '-32.png', $icon)), false)
            ->assertSee(basename(str_replace('-512.png', '-180.png', $icon)), false);
    }

    public function test_invalid_images_are_rejected(): void
    {
        Storage::fake('uploads');
        $owner = $this->owner();

        $cases = [
            'site' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            'pdf' => UploadedFile::fake()->image('big.png', 900, 300)->size(3000),
            'icon' => UploadedFile::fake()->image('wide.png', 512, 400),
        ];
        foreach ($cases as $type => $file) {
            $this->actingAs($owner)->post("/admin/settings/logos/{$type}", ['image' => $file])->assertSessionHasErrors('image');
        }
        $this->actingAs($owner)->post('/admin/settings/logos/site', ['image' => UploadedFile::fake()->image('tiny.png', 120, 60)])->assertSessionHasErrors('image');
        $this->actingAs($owner)->post('/admin/settings/logos/icon', ['image' => UploadedFile::fake()->image('small.png', 128, 128)])->assertSessionHasErrors('image');

        $this->assertNull(setting('logo_site'));
        $this->assertSame([], Storage::disk('uploads')->allFiles('branding'));
    }

    public function test_restoring_the_default_removes_the_uploaded_files(): void
    {
        Storage::fake('uploads');
        $owner = $this->owner();
        $this->actingAs($owner)->post('/admin/settings/logos/icon', ['image' => UploadedFile::fake()->image('icon.png', 300, 300)]);
        $this->assertCount(3, Storage::disk('uploads')->allFiles('branding'));

        $this->actingAs($owner)->delete('/admin/settings/logos/icon')->assertRedirect();

        $this->assertNull(setting('logo_icon'));
        $this->assertSame([], Storage::disk('uploads')->allFiles('branding'));
        $this->assertStringContainsString('img/favicon.png', Branding::iconUrl(32));
    }

    public function test_only_settings_managers_can_change_logos(): void
    {
        Storage::fake('uploads');
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller)->post('/admin/settings/logos/site', ['image' => UploadedFile::fake()->image('logo.png', 600, 200)])->assertForbidden();
        $this->actingAs($seller)->delete('/admin/settings/logos/site')->assertForbidden();
        $this->actingAs($this->owner())->post('/admin/settings/logos/otro', ['image' => UploadedFile::fake()->image('logo.png', 600, 200)])->assertNotFound();
    }
}
