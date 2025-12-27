<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\RequestLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    /**
     * Display the admin usage statistics page.
     */
    public function index(Request $request): Response
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $dateRangeQuery = RequestLog::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalStats = (clone $dateRangeQuery)->selectRaw('
            SUM(total_cost) as total_cost,
            SUM(total_tokens) as total_tokens,
            SUM(prompt_tokens) as prompt_tokens,
            SUM(completion_tokens) as completion_tokens,
            SUM(input_images_count) as input_images,
            SUM(output_images_count) as output_images,
            COUNT(*) as total_requests
        ')->first();

        $textStats = (clone $dateRangeQuery)
            ->where('type', 'text')
            ->selectRaw('
                SUM(total_cost) as total_cost,
                COUNT(*) as total_requests
            ')->first();

        $imageStats = (clone $dateRangeQuery)
            ->where('type', 'image')
            ->selectRaw('
                SUM(total_cost) as total_cost,
                COUNT(*) as total_requests
            ')->first();

        $uniqueBooksInPeriod = (clone $dateRangeQuery)
            ->whereNotNull('book_id')
            ->distinct('book_id')
            ->count('book_id');

        $uniqueChaptersInPeriod = (clone $dateRangeQuery)
            ->whereNotNull('chapter_id')
            ->distinct('chapter_id')
            ->count('chapter_id');

        $avgCostPerBook = $uniqueBooksInPeriod > 0
            ? (float) $totalStats->total_cost / $uniqueBooksInPeriod
            : 0;

        $avgCostPerChapter = $uniqueChaptersInPeriod > 0
            ? (float) $totalStats->total_cost / $uniqueChaptersInPeriod
            : 0;

        $dailyCosts = RequestLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total_cost) as cost')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'cost' => (float) $row->cost,
            ]);

        $allDates = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $allDates[$current->format('Y-m-d')] = 0;
            $current->addDay();
        }

        foreach ($dailyCosts as $day) {
            $allDates[$day['date']] = $day['cost'];
        }

        $chartData = collect($allDates)->map(fn ($cost, $date) => [
            'date' => $date,
            'cost' => $cost,
        ])->values();

        $costByType = RequestLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('item_type, SUM(total_cost) as cost, COUNT(*) as count')
            ->groupBy('item_type')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->item_type,
                'cost' => (float) $row->cost,
                'count' => (int) $row->count,
            ]);

        $totalBooks = Book::count();
        $totalChapters = Chapter::count();

        return Inertia::render('admin/Usage', [
            'stats' => [
                'total_cost' => (float) ($totalStats->total_cost ?? 0),
                'total_tokens' => (int) ($totalStats->total_tokens ?? 0),
                'prompt_tokens' => (int) ($totalStats->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($totalStats->completion_tokens ?? 0),
                'total_images' => (int) (($totalStats->input_images ?? 0) + ($totalStats->output_images ?? 0)),
                'output_images' => (int) ($totalStats->output_images ?? 0),
                'total_requests' => (int) ($totalStats->total_requests ?? 0),
                'text_cost' => (float) ($textStats->total_cost ?? 0),
                'text_requests' => (int) ($textStats->total_requests ?? 0),
                'image_cost' => (float) ($imageStats->total_cost ?? 0),
                'image_requests' => (int) ($imageStats->total_requests ?? 0),
                'avg_cost_per_book' => $avgCostPerBook,
                'avg_cost_per_chapter' => $avgCostPerChapter,
                'unique_books' => $uniqueBooksInPeriod,
                'unique_chapters' => $uniqueChaptersInPeriod,
                'total_books' => $totalBooks,
                'total_chapters' => $totalChapters,
            ],
            'chartData' => $chartData,
            'costByType' => $costByType,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }
}
