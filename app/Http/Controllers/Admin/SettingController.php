<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::getMany([
            'site_name' => 'اتوماسیون اداری',
            'site_subtitle' => 'تعمیرآنلاین',
            'logo' => null,
            'favicon' => null,
            'notification_sound' => null,
        ]);

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
            'site_subtitle' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon' => 'nullable|mimes:ico,png,jpg,svg|max:1024',
            'notification_sound' => 'nullable|mimes:mp3,wav,ogg|max:1024',
        ]);

        Setting::set('site_name', $request->site_name);
        Setting::set('site_subtitle', $request->site_subtitle);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $oldLogo = Setting::get('logo');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
            $path = $request->file('logo')->store('settings', 'public');
            Setting::set('logo', $path);
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $oldFavicon = Setting::get('favicon');
            if ($oldFavicon) {
                Storage::disk('public')->delete($oldFavicon);
            }
            $path = $request->file('favicon')->store('settings', 'public');
            Setting::set('favicon', $path);
        }

        // Handle notification sound upload
        if ($request->hasFile('notification_sound')) {
            $oldSound = Setting::get('notification_sound');
            if ($oldSound) {
                Storage::disk('public')->delete($oldSound);
            }
            $path = $request->file('notification_sound')->store('settings', 'public');
            Setting::set('notification_sound', $path);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }

    public function deleteLogo()
    {
        $logo = Setting::get('logo');
        if ($logo) {
            Storage::disk('public')->delete($logo);
            Setting::set('logo', null);
        }
        return redirect()->route('admin.settings.index')
            ->with('success', 'لوگو حذف شد');
    }

    public function deleteFavicon()
    {
        $favicon = Setting::get('favicon');
        if ($favicon) {
            Storage::disk('public')->delete($favicon);
            Setting::set('favicon', null);
        }
        return redirect()->route('admin.settings.index')
            ->with('success', 'فاوآیکون حذف شد');
    }

    public function deleteSound()
    {
        $sound = Setting::get('notification_sound');
        if ($sound) {
            Storage::disk('public')->delete($sound);
            Setting::set('notification_sound', null);
        }
        return redirect()->route('admin.settings.index')
            ->with('success', 'صدای اعلان حذف شد');
    }
}
