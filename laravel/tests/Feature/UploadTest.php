<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\UploadImageRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

/**
 * Загрузка файлов с подменённым диском.
 *
 * Набор тестов не обращается к MinIO: `Storage::fake()` подменяет диск, поэтому
 * здесь проверяется логика приложения, а настоящее хранилище покрывается
 * сквозным тестом.
 */
class UploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
    }

    /**
     * Создаёт настоящий PNG, проходящий правила `image` и `dimensions`.
     */
    private function png(int $width = 64, int $height = 64, string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    // ── Space logo ──

    public function test_the_owner_can_upload_a_logo(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()])
            ->assertRedirect();

        $path = Uploads::spaceDirectory($space->id).'/logo.png';

        Storage::disk(config('filesystems.default'))->assertExists($path);
        $this->assertSame(
            Uploads::url($path),
            $space->fresh()->branding->logo_url,
        );
    }

    public function test_an_uploaded_logo_is_publicly_visible(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()]);

        // The branding page and the collection page both read this prop.
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.branding.edit', $space->slug))
            ->assertInertia(fn ($page) => $page
                ->where('branding.logo_url', fn ($url) => str_contains($url, 'logo.png'))
            );
    }

    public function test_uploading_a_new_logo_replaces_the_old_file(): void
    {
        $space = $this->fixtureSpace();
        $owner = $this->fixtureOwner();
        $disk = Storage::disk(config('filesystems.default'));
        $directory = Uploads::spaceDirectory($space->id);

        $this->actingAs($owner)
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()]);

        $disk->assertExists($directory.'/logo.png');

        // A different extension must not leave the old file behind.
        $this->actingAs($owner)
            ->post(route('spaces.logo.store', $space->slug), [
                'logo' => $this->png(name: 'logo.webp'),
            ]);

        $disk->assertMissing($directory.'/logo.png');
        $disk->assertExists($directory.'/logo.webp');
        $this->assertSame(1, count($disk->files($directory)));
    }

    public function test_the_owner_can_remove_a_logo(): void
    {
        $space = $this->fixtureSpace();
        $owner = $this->fixtureOwner();
        $disk = Storage::disk(config('filesystems.default'));
        $directory = Uploads::spaceDirectory($space->id);

        $this->actingAs($owner)
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()]);

        $this->actingAs($owner)
            ->delete(route('spaces.logo.destroy', $space->slug))
            ->assertRedirect();

        $disk->assertMissing($directory.'/logo.png');
        $this->assertNull($space->fresh()->branding->logo_url);
    }

    public function test_a_stranger_cannot_upload_a_logo(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureStranger())
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()])
            ->assertForbidden();

        $this->assertNull($space->fresh()->branding->logo_url);
    }

    public function test_an_admin_can_upload_a_logo_to_any_space(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureAdmin())
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()])
            ->assertRedirect();

        $this->assertNotNull($space->fresh()->branding->logo_url);
    }

    public function test_guests_are_redirected_from_the_logo_endpoint(): void
    {
        $space = $this->fixtureSpace();

        $this->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()])
            ->assertRedirect(route('login'));
    }

    // ── Validation ──

    public function test_a_non_image_is_rejected(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), [
                'logo' => UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertNull($space->fresh()->branding->logo_url);
    }

    public function test_svg_is_rejected_because_it_can_carry_script(): void
    {
        $space = $this->fixtureSpace();

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $svg])
            ->assertSessionHasErrors('logo');
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $space = $this->fixtureSpace();

        // Just over the 2 MB cap.
        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), [
                'logo' => UploadedFile::fake()->image('big.png')->size(
                    UploadImageRequest::MAX_KILOBYTES + 100,
                ),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_an_image_beyond_the_dimension_cap_is_rejected(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), [
                'logo' => $this->png(
                    UploadImageRequest::MAX_DIMENSION + 200,
                    1,
                ),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_a_missing_file_is_rejected(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), [])
            ->assertSessionHasErrors('logo');
    }

    // ── Testimonial avatar ──

    public function test_the_owner_can_upload_an_avatar(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureOwner())
            ->post(route('testimonials.avatar.store', $testimonial->id), [
                'avatar' => $this->png(name: 'avatar.png'),
            ])
            ->assertRedirect();

        $path = Uploads::testimonialDirectory($testimonial->id).'/avatar.png';

        Storage::disk(config('filesystems.default'))->assertExists($path);
        $this->assertSame(Uploads::url($path), $testimonial->fresh()->author_avatar_url);
    }

    public function test_the_owner_can_remove_an_avatar(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);
        $owner = $this->fixtureOwner();

        $this->actingAs($owner)
            ->post(route('testimonials.avatar.store', $testimonial->id), [
                'avatar' => $this->png(name: 'avatar.png'),
            ]);

        $this->actingAs($owner)
            ->delete(route('testimonials.avatar.destroy', $testimonial->id))
            ->assertRedirect();

        $this->assertNull($testimonial->fresh()->author_avatar_url);
    }

    public function test_a_stranger_cannot_upload_an_avatar(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureStranger())
            ->post(route('testimonials.avatar.store', $testimonial->id), [
                'avatar' => $this->png(name: 'avatar.png'),
            ])
            ->assertForbidden();

        $this->assertNull($testimonial->fresh()->author_avatar_url);
    }

    public function test_an_admin_can_upload_an_avatar_to_any_space(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureAdmin())
            ->post(route('testimonials.avatar.store', $testimonial->id), [
                'avatar' => $this->png(name: 'avatar.png'),
            ])
            ->assertRedirect();

        $this->assertNotNull($testimonial->fresh()->author_avatar_url);
    }

    public function test_an_invalid_avatar_is_rejected(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureOwner())
            ->post(route('testimonials.avatar.store', $testimonial->id), [
                'avatar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('avatar');
    }

    // ── Storage helper ──

    public function test_uploads_use_the_configured_disk(): void
    {
        // The whole point of the helper: no caller names a disk, so switching
        // FILESYSTEM_DISK between local and s3 changes nothing in the code.
        $this->assertSame('local', config('filesystems.default'));

        $space = $this->fixtureSpace();
        $path = Uploads::replace(
            $this->png(),
            Uploads::spaceDirectory($space->id),
            'logo',
        );

        Storage::disk(config('filesystems.default'))->assertExists($path);
    }

    public function test_deleting_a_space_leaves_its_objects_in_place(): void
    {
        $space = $this->fixtureSpace();
        $disk = Storage::disk(config('filesystems.default'));
        $path = Uploads::spaceDirectory($space->id).'/logo.png';

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.logo.store', $space->slug), ['logo' => $this->png()]);

        $disk->assertExists($path);

        $space->delete();

        // Known gap: the row cascades away but the object does not. Deleting
        // storage inside a database transaction is worse than leaving an
        // orphan, so cleanup belongs in a job that sweeps objects with no
        // owning row.
        $disk->assertExists($path);
    }

    public function test_the_directory_layout_is_per_owner(): void
    {
        $space = $this->fixtureSpace();
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->assertSame("spaces/{$space->id}", Uploads::spaceDirectory($space->id));
        $this->assertSame(
            "testimonials/{$testimonial->id}",
            Uploads::testimonialDirectory($testimonial->id),
        );
    }

    public function test_the_extension_helper_normalises_jpeg(): void
    {
        $this->assertSame(
            'jpg',
            Uploads::extension(UploadedFile::fake()->image('photo.jpeg')),
        );
    }

    public function test_forget_removes_every_extension_of_a_stem(): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $directory = 'spaces/test-space';

        $disk->put("{$directory}/logo.png", 'png');
        $disk->put("{$directory}/logo.webp", 'webp');
        $disk->put("{$directory}/other.png", 'other');

        Uploads::forget($directory, 'logo');

        $disk->assertMissing("{$directory}/logo.png");
        $disk->assertMissing("{$directory}/logo.webp");
        // A different stem must survive.
        $disk->assertExists("{$directory}/other.png");
    }
}
