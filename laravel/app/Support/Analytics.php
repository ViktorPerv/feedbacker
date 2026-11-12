<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Testimonial;
use App\Models\TestimonialClick;
use App\Models\WidgetView;
use Illuminate\Support\Collection;

/**
 * Агрегация аналитики, записываемой сервисом виджетов на Go.
 *
 * Каждый метод принимает коллекцию идентификаторов пространств: обычный
 * пользователь передаёт свои пространства, администратор — все.
 */
final class Analytics
{
    public const DEFAULT_WINDOW_DAYS = 30;

    /**
     * Возвращает основные показатели для набора пространств.
     *
     * @param  Collection<int, string>  $spaceIds
     * @return array{views: int, clicks: int, click_rate: float, days: int}
     */
    public static function summary(Collection $spaceIds, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        if ($spaceIds->isEmpty()) {
            return ['views' => 0, 'clicks' => 0, 'click_rate' => 0.0, 'days' => $days];
        }

        $views = WidgetView::whereIn('space_id', $spaceIds)->since($days)->count();
        $clicks = TestimonialClick::whereIn('space_id', $spaceIds)->since($days)->count();

        return [
            'views' => $views,
            'clicks' => $clicks,
            // Guard against a division by zero when nothing has been served.
            'click_rate' => $views > 0 ? round($clicks / $views * 100, 1) : 0.0,
            'days' => $days,
        ];
    }

    /**
     * Возвращает показы и клики по дням от старых к новым, включая дни без
     * активности для непрерывной оси графика.
     *
     * @param  Collection<int, string>  $spaceIds
     * @return array<int, array{date: string, views: int, clicks: int}>
     */
    public static function dailySeries(Collection $spaceIds, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $views = collect();
        $clicks = collect();

        if ($spaceIds->isNotEmpty()) {
            $views = WidgetView::whereIn('space_id', $spaceIds)
                ->where('viewed_at', '>=', $start)
                ->get(['viewed_at'])
                ->countBy(fn (WidgetView $view) => $view->viewed_at->toDateString());

            $clicks = TestimonialClick::whereIn('space_id', $spaceIds)
                ->where('clicked_at', '>=', $start)
                ->get(['clicked_at'])
                ->countBy(fn (TestimonialClick $click) => $click->clicked_at->toDateString());
        }

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();

            $series[] = [
                'date' => $date,
                'views' => (int) $views->get($date, 0),
                'clicks' => (int) $clicks->get($date, 0),
            ];
        }

        return $series;
    }

    /**
     * Возвращает отзывы с наибольшим количеством кликов.
     *
     * @param  Collection<int, string>  $spaceIds
     * @return array<int, array{id: string, author_name: string, content: string, clicks: int}>
     */
    public static function topTestimonials(
        Collection $spaceIds,
        int $days = self::DEFAULT_WINDOW_DAYS,
        int $limit = 5,
    ): array {
        if ($spaceIds->isEmpty()) {
            return [];
        }

        $rows = TestimonialClick::query()
            ->whereIn('space_id', $spaceIds)
            ->since($days)
            ->selectRaw('testimonial_id, count(*) as clicks')
            ->groupBy('testimonial_id')
            ->orderByDesc('clicks')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // One query for every referenced testimonial instead of one per row.
        $testimonials = Testimonial::whereIn('id', $rows->pluck('testimonial_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($testimonials) {
                $testimonial = $testimonials->get($row->testimonial_id);

                if ($testimonial === null) {
                    return null;
                }

                return [
                    'id' => $testimonial->id,
                    'author_name' => $testimonial->author_name,
                    'content' => str($testimonial->content)->limit(120)->toString(),
                    'clicks' => (int) $row->clicks,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Возвращает показы и клики по пространствам с ключом по идентификатору.
     *
     * @param  Collection<int, string>  $spaceIds
     * @return array<string, array{views: int, clicks: int}>
     */
    public static function perSpace(Collection $spaceIds, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        if ($spaceIds->isEmpty()) {
            return [];
        }

        $views = WidgetView::whereIn('space_id', $spaceIds)
            ->since($days)
            ->selectRaw('space_id, count(*) as total')
            ->groupBy('space_id')
            ->pluck('total', 'space_id');

        $clicks = TestimonialClick::whereIn('space_id', $spaceIds)
            ->since($days)
            ->selectRaw('space_id, count(*) as total')
            ->groupBy('space_id')
            ->pluck('total', 'space_id');

        $result = [];

        foreach ($spaceIds as $id) {
            $result[$id] = [
                'views' => (int) $views->get($id, 0),
                'clicks' => (int) $clicks->get($id, 0),
            ];
        }

        return $result;
    }
}
