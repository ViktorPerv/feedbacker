<?php

declare(strict_types=1);

use App\Http\Controllers\CollectionPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpaceAnalyticsController;
use App\Http\Controllers\SpaceBrandingController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\SpaceLogoController;
use App\Http\Controllers\TestimonialAvatarController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\WidgetController;
use App\Models\Widget;
use Illuminate\Support\Facades\Route;

// ── Public ──

Route::get('/', function () {
    // The landing page embeds a real widget, so a visitor sees the product
    // working rather than a screenshot of it.
    $slug = config('widget.demo_space');

    $widget = $slug === null ? null : Widget::query()
        ->where('is_active', true)
        ->whereHas('space', fn ($query) => $query
            ->where('slug', $slug)
            ->where('is_active', true))
        ->first();

    return Inertia\Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'demoWidget' => $widget === null ? null : [
            'token' => $widget->token,
            'script_url' => $widget->embedUrl(),
            'space_name' => $widget->space->name,
        ],
    ]);
})->name('home');

Route::get('c/{space}', [CollectionPageController::class, 'show'])->name('collect.show');
Route::post('c/{space}', [CollectionPageController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('collect.store');

// Language switching is open to guests: the collection page is public and
// should be readable in either language.
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// ── Authenticated ──

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('spaces/{space}/testimonials', [TestimonialController::class, 'index'])
        ->name('spaces.testimonials.index');

    Route::patch('testimonials/{testimonial}/approve', [TestimonialController::class, 'approve'])
        ->name('testimonials.approve');
    Route::patch('testimonials/{testimonial}/reject', [TestimonialController::class, 'reject'])
        ->name('testimonials.reject');
    Route::patch('testimonials/{testimonial}/feature', [TestimonialController::class, 'feature'])
        ->name('testimonials.feature');
    Route::patch('testimonials/{testimonial}/tags', [TestimonialController::class, 'tags'])
        ->name('testimonials.tags');
    Route::delete('testimonials/{testimonial}', [TestimonialController::class, 'destroy'])
        ->name('testimonials.destroy');

    // Uploads. Images are validated in UploadImageRequest; the files land on
    // whichever disk FILESYSTEM_DISK selects.
    Route::post('testimonials/{testimonial}/avatar', [TestimonialAvatarController::class, 'store'])
        ->name('testimonials.avatar.store');
    Route::delete('testimonials/{testimonial}/avatar', [TestimonialAvatarController::class, 'destroy'])
        ->name('testimonials.avatar.destroy');

    Route::post('spaces/{space}/logo', [SpaceLogoController::class, 'store'])
        ->name('spaces.logo.store');
    Route::delete('spaces/{space}/logo', [SpaceLogoController::class, 'destroy'])
        ->name('spaces.logo.destroy');

    Route::get('spaces/{space}/branding', [SpaceBrandingController::class, 'edit'])
        ->name('spaces.branding.edit');
    Route::put('spaces/{space}/branding', [SpaceBrandingController::class, 'update'])
        ->name('spaces.branding.update');

    Route::get('spaces/{space}/analytics', [SpaceAnalyticsController::class, 'show'])
        ->name('spaces.analytics');

    // Widget builder.
    Route::get('spaces/{space}/widgets', [WidgetController::class, 'index'])
        ->name('spaces.widgets.index');
    Route::get('spaces/{space}/widgets/create', [WidgetController::class, 'create'])
        ->name('spaces.widgets.create');
    Route::post('spaces/{space}/widgets', [WidgetController::class, 'store'])
        ->name('spaces.widgets.store');
    Route::get('spaces/{space}/widgets/{widget}/edit', [WidgetController::class, 'edit'])
        ->name('spaces.widgets.edit');
    Route::put('spaces/{space}/widgets/{widget}', [WidgetController::class, 'update'])
        ->name('spaces.widgets.update');
    Route::delete('spaces/{space}/widgets/{widget}', [WidgetController::class, 'destroy'])
        ->name('spaces.widgets.destroy');

    Route::resource('spaces', SpaceController::class);

    // ── Translation catalogue (admin + translator) ──
    //
    // Guarded by the `translations.manage` permission rather than a role check,
    // so a new role can be granted access without touching this file.
    Route::post('translations/import', [TranslationController::class, 'import'])
        ->name('translations.import');
    Route::resource('translations', TranslationController::class)
        ->except(['show']);

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
