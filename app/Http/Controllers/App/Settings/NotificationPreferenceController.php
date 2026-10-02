<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Http\Controllers\App\Controller;
use App\Http\Requests\App\Settings\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $preferences = NotificationPreference::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'post_published' => true,
                'post_failed' => true,
                'account_disconnected' => true,
                'post_note_added' => true,
                'collaboration' => true,
            ],
        );

        return Inertia::render('settings/profile/Notifications', [
            'preferences' => $preferences,
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        NotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validated(),
        );

        session()->flash('flash.banner', __('settings.flash.notifications_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
