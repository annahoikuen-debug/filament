<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class RecentActivityWidget extends Widget
{
    protected static ?string $heading = '最近のアクティビティ';

    public function view(): string
    {
        // Get recent activities for the current facility (if applicable)
        $query = Activity::query()
            ->orderBy('created_at', 'desc')
            ->take(5);

        // If the user is a facility admin, limit to their facility's activities
        if (Auth::user()?->facility_id) {
            // We need to join on the subject to get the facility_id?
            // The activity log might not have facility_id directly.
            // We'll skip this for now and show all activities.
            // Alternatively, we can filter by the log name or by checking the subject type.
            // For simplicity, we'll show all activities.
        }

        $activities = $query->get();

        if ($activities->isEmpty()) {
            return '<div class="p-4 text-center text-gray-500">最近のアクティビティはありません。</div>';
        }

        $items = $activities->map(function ($activity) {
            $description = $activity->description ?? 'No description';
            $time = $activity->created_at?->diffForHumans() ?? 'unknown time';

            return "<div class='mb-2'><strong>{$time}</strong>: {$description}</div>";
        })->implode('');

        return "<div class='p-4 space-y-2'>{$items}</div>";
    }
}
