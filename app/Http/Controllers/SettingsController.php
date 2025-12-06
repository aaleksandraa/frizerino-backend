<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Get all settings (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = SystemSetting::all()->groupBy('group');

        $formatted = [];
        foreach ($settings as $group => $groupSettings) {
            $formatted[$group] = [];
            foreach ($groupSettings as $setting) {
                $formatted[$group][$setting->key] = [
                    'value' => $this->castValue($setting->value, $setting->type),
                    'type' => $setting->type,
                    'description' => $setting->description,
                ];
            }
        }

        return response()->json($formatted);
    }

    /**
     * Get settings by group (admin only)
     */
    public function getByGroup(Request $request, string $group): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = SystemSetting::getByGroup($group);

        return response()->json($settings);
    }

    /**
     * Update settings (admin only)
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $settingData) {
            $setting = SystemSetting::where('key', $settingData['key'])->first();

            if ($setting) {
                $value = $settingData['value'];

                // Convert boolean values to string for storage
                if ($setting->type === 'boolean') {
                    $value = $value ? 'true' : 'false';
                }

                // Convert arrays to JSON
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                $setting->update(['value' => $value]);

                // Clear cache for this setting
                Cache::forget("setting_{$setting->key}");
            }
        }

        // Clear grouped caches
        Cache::forget('settings_analytics');

        return response()->json([
            'message' => 'Podešavanja su uspješno sačuvana',
            'settings' => SystemSetting::all()->keyBy('key'),
        ]);
    }

    /**
     * Get public analytics settings (for frontend to inject GA script)
     * This is a public endpoint - no auth required
     */
    public function getAnalytics(): JsonResponse
    {
        $analytics = SystemSetting::getAnalyticsSettings();

        return response()->json($analytics);
    }

    /**
     * Get public appearance settings (gradient, etc.)
     * This is a public endpoint - no auth required
     */
    public function getAppearance(): JsonResponse
    {
        $gradient = SystemSetting::get('homepage_gradient', [
            'preset' => 'beauty-rose',
            'from' => '#f43f5e',
            'via' => '#ec4899',
            'to' => '#a855f7',
            'direction' => 'br',
            'custom' => false
        ]);

        return response()->json([
            'gradient' => $gradient,
        ]);
    }

    /**
     * Get gradient presets (admin only)
     */
    public function getGradientPresets(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $presets = SystemSetting::get('gradient_presets', []);
        $currentGradient = SystemSetting::get('homepage_gradient', []);

        return response()->json([
            'presets' => $presets,
            'current' => $currentGradient,
        ]);
    }

    /**
     * Update gradient settings (admin only)
     */
    public function updateGradient(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'preset' => 'nullable|string',
            'from' => 'required|string',
            'via' => 'nullable|string',
            'to' => 'required|string',
            'direction' => 'required|string|in:r,l,t,b,tr,tl,br,bl',
            'custom' => 'boolean',
        ]);

        SystemSetting::set('homepage_gradient', $validated, 'json', 'appearance');

        return response()->json([
            'message' => 'Gradient postavke su uspješno sačuvane',
            'gradient' => $validated,
        ]);
    }

    /**
     * Cast value to appropriate type
     */
    private function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
