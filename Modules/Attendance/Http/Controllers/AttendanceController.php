<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceSetting;
use Modules\Attendance\Models\EmployeeSetting;
use Morilog\Jalali\Jalalian;

class AttendanceController extends Controller
{
    /**
     * Dashboard - Show today's status and check-in/out buttons
     */
    public function index()
    {
        $user = auth()->user();
        $today = Attendance::getTodayForUser($user->id);
        $settings = AttendanceSetting::get();
        $employeeSettings = EmployeeSetting::getOrCreate($user->id);

        // Get this month's attendances
        $jalaliNow = Jalalian::now();
        $startOfMonth = (new Jalalian($jalaliNow->getYear(), $jalaliNow->getMonth(), 1))->toCarbon();
        $endOfMonth = (new Jalalian($jalaliNow->getYear(), $jalaliNow->getMonth(), $jalaliNow->getMonthDays()))->toCarbon();

        $monthlyAttendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date', 'desc')
            ->get();

        // Calculate monthly stats
        $stats = [
            'present_days' => $monthlyAttendances->where('status', Attendance::STATUS_PRESENT)->count(),
            'absent_days' => $monthlyAttendances->where('status', Attendance::STATUS_ABSENT)->count(),
            'total_work_hours' => round($monthlyAttendances->sum('work_minutes') / 60, 1),
            'total_late_minutes' => $monthlyAttendances->sum('late_minutes'),
            'total_overtime_minutes' => $monthlyAttendances->sum('overtime_minutes'),
        ];

        return view('attendance::attendance.index', compact(
            'today',
            'settings',
            'employeeSettings',
            'monthlyAttendances',
            'stats'
        ));
    }

    /**
     * Check-in
     */
    public function checkIn(Request $request)
    {
        try {
            $settings = AttendanceSetting::get();

            $data = [
                'ip' => $request->ip(),
            ];

            // GPS Location
            if ($request->has('latitude') && $request->has('longitude')) {
                $data['location'] = [
                    'lat' => $request->latitude,
                    'lng' => $request->longitude,
                ];

                // Verify location if required
                if ($settings->isVerificationRequired('gps') && $settings->allowed_location_lat) {
                    $distance = $this->calculateDistance(
                        $request->latitude,
                        $request->longitude,
                        $settings->allowed_location_lat,
                        $settings->allowed_location_lng
                    );

                    if ($distance > $settings->allowed_location_radius) {
                        return response()->json([
                            'success' => false,
                            'message' => 'موقعیت شما خارج از محدوده مجاز است'
                        ], 422);
                    }
                }
            }

            // Verify IP if required
            if ($settings->isVerificationRequired('ip') && !empty($settings->allowed_ips)) {
                if (!in_array($request->ip(), $settings->allowed_ips)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'آدرس IP شما مجاز نیست'
                    ], 422);
                }
            }

            // Handle selfie upload
            if ($request->hasFile('selfie')) {
                $path = $request->file('selfie')->store('attendance/selfies/' . date('Y/m'), 'public');
                $data['selfie'] = $path;
            }

            $attendance = Attendance::checkIn(auth()->id(), $data);

            $message = 'ورود ثبت شد';
            if ($attendance->late_minutes > 0) {
                $message .= ' - تاخیر: ' . $attendance->late_time;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'attendance' => $attendance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Check-out
     */
    public function checkOut(Request $request)
    {
        try {
            $data = [
                'ip' => $request->ip(),
            ];

            if ($request->has('latitude') && $request->has('longitude')) {
                $data['location'] = [
                    'lat' => $request->latitude,
                    'lng' => $request->longitude,
                ];
            }

            if ($request->hasFile('selfie')) {
                $path = $request->file('selfie')->store('attendance/selfies/' . date('Y/m'), 'public');
                $data['selfie'] = $path;
            }

            $attendance = Attendance::checkOut(auth()->id(), $data);

            $message = 'خروج ثبت شد - کارکرد: ' . $attendance->work_hours;
            if ($attendance->early_leave_minutes > 0) {
                $message .= ' - زودرفت: ' . $attendance->early_leave_minutes . ' دقیقه';
            }
            if ($attendance->overtime_minutes > 0) {
                $message .= ' - اضافه‌کاری: ' . $attendance->overtime_minutes . ' دقیقه';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'attendance' => $attendance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * History - Show attendance history
     */
    public function history(Request $request)
    {
        $user = auth()->user();

        // Default to current Jalali month
        $jalaliYear = $request->get('year', Jalalian::now()->getYear());
        $jalaliMonth = $request->get('month', Jalalian::now()->getMonth());

        $jalaliStart = new Jalalian($jalaliYear, $jalaliMonth, 1);
        $startDate = $jalaliStart->toCarbon();
        $endDate = (new Jalalian($jalaliYear, $jalaliMonth, $jalaliStart->getMonthDays()))->toCarbon();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        // Stats
        $stats = [
            'present_days' => $attendances->where('status', Attendance::STATUS_PRESENT)->count(),
            'absent_days' => $attendances->where('status', Attendance::STATUS_ABSENT)->count(),
            'leave_days' => $attendances->where('status', Attendance::STATUS_LEAVE)->count(),
            'total_work_hours' => round($attendances->sum('work_minutes') / 60, 1),
            'total_late_minutes' => $attendances->sum('late_minutes'),
            'total_overtime_minutes' => $attendances->sum('overtime_minutes'),
        ];

        // Generate month options
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return view('attendance::attendance.history', compact(
            'attendances',
            'stats',
            'jalaliYear',
            'jalaliMonth',
            'months'
        ));
    }

    /**
     * Admin - List all employees attendance
     */
    public function adminIndex(Request $request)
    {
        $date = $request->get('date', today()->toDateString());

        $attendances = Attendance::with('user')
            ->where('date', $date)
            ->orderBy('check_in')
            ->get();

        // Get all staff
        $allStaff = User::staff()->get();
        $presentIds = $attendances->pluck('user_id')->toArray();

        return view('attendance::attendance.admin-index', compact(
            'attendances',
            'allStaff',
            'presentIds',
            'date'
        ));
    }

    /**
     * Admin - Settings
     */
    public function settings()
    {
        $settings = AttendanceSetting::get();
        return view('attendance::attendance.settings', compact('settings'));
    }

    /**
     * Admin - Update settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'work_start_time' => 'required|date_format:H:i',
            'work_end_time' => 'required|date_format:H:i',
            'late_tolerance_minutes' => 'required|integer|min:0',
        ]);

        $settings = AttendanceSetting::get();
        $settings->update([
            'work_start_time' => $request->work_start_time,
            'work_end_time' => $request->work_end_time,
            'late_tolerance_minutes' => $request->late_tolerance_minutes,
            'verification_methods' => $request->verification_methods ?? ['trust'],
            'allowed_ips' => $request->allowed_ips ? array_filter(explode("\n", str_replace("\r", "", $request->allowed_ips))) : null,
            'allowed_location_lat' => $request->allowed_location_lat,
            'allowed_location_lng' => $request->allowed_location_lng,
            'allowed_location_radius' => $request->allowed_location_radius ?? 100,
            'working_days' => $request->working_days ?? [0, 1, 2, 3, 4],
            'salary_type' => $request->salary_type ?? 'monthly',
            'overtime_rate' => $request->overtime_rate ?? 1.40,
            'late_deduction_per_minute' => $request->late_deduction_per_minute ?? 0,
            'absence_deduction_per_day' => $request->absence_deduction_per_day ?? 0,
        ]);

        return redirect()->back()->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }

    /**
     * Calculate distance between two coordinates in meters
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000; // meters

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;

        $a = sin($latDelta / 2) ** 2 + cos($lat1) * cos($lat2) * sin($lonDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
