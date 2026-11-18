<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Logging\LogRetention;
use App\Models\GatewayLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'retention' => LogRetention::current(),
            'options' => LogRetention::cases(),
            'logCount' => GatewayLog::count(),
            'oldestLog' => GatewayLog::min('created_at'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'log_retention' => ['required', Rule::enum(LogRetention::class)],
        ]);

        Setting::set(LogRetention::SETTING, $data['log_retention']);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }

    /** Apply the retention now instead of waiting for the daily prune. */
    public function prune(): RedirectResponse
    {
        $before = GatewayLog::count();
        Artisan::call('model:prune', ['--model' => [GatewayLog::class]]);
        $deleted = $before - GatewayLog::count();

        return redirect()->route('admin.settings.edit')->with('status', "Deleted $deleted expired log ".str('entry')->plural($deleted).'.');
    }
}
