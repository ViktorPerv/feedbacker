<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Space;
use App\Support\Analytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Embed performance for a single space.
 *
 * Read-only: the numbers are produced by the Go widget service and aggregated
 * on the fly, so there is nothing to write back.
 */
class SpaceAnalyticsController extends Controller
{
    public function show(Request $request, Space $space): Response
    {
        $this->authorize('view', $space);

        $days = (int) $request->integer('days', Analytics::DEFAULT_WINDOW_DAYS);
        $days = in_array($days, [7, 30, 90], true) ? $days : Analytics::DEFAULT_WINDOW_DAYS;

        $spaceIds = collect([$space->id]);

        return Inertia::render('Spaces/Analytics', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'collection_url' => $space->collectionUrl(),
                'embed_url' => url("/w/{$space->slug}.js"),
                // Built server-side: a literal `</script>` inside a Vue
                // <script setup> block terminates the SFC early.
                'embed_snippet' => sprintf(
                    "<div data-feedbacker=\"%s\"></div>\n<script async src=\"%s\"></script>",
                    $space->slug,
                    url("/w/{$space->slug}.js"),
                ),
            ],
            'summary' => Analytics::summary($spaceIds, $days),
            'series' => Analytics::dailySeries($spaceIds, $days),
            'topTestimonials' => Analytics::topTestimonials($spaceIds, $days),
            'window' => $days,
            'windows' => [7, 30, 90],
        ]);
    }
}
