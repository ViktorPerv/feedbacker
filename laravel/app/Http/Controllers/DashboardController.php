<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Enums\TestimonialStatusEnum;
use App\Models\Space;
use App\Models\Testimonial;
use App\Support\Analytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewAll = $user->checkPermissionTo(PermissionEnum::SpacesViewAny->value);

        // Accounts with oversight permissions get platform-wide counters;
        // everyone else only ever sees their own data.
        $spaceIds = ($canViewAll ? Space::query() : $user->spaces())->pluck('id');

        $testimonials = Testimonial::whereIn('space_id', $spaceIds);

        return Inertia::render('Dashboard', [
            'spacesCount' => $spaceIds->count(),
            'testimonialsCount' => (clone $testimonials)->count(),
            'pendingCount' => (clone $testimonials)
                ->where('status', TestimonialStatusEnum::Pending->value)
                ->count(),
            'analytics' => Analytics::summary($spaceIds),
            'canViewAll' => $canViewAll,
        ]);
    }
}
