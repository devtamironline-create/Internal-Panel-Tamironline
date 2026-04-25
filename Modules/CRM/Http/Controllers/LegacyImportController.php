<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Console\Importers\AbstractImporter;
use Modules\CRM\Console\Importers\CustomerImporter;

/**
 * انتقال داده از CRM وردپرسی به CRM لاراولی، از طریق UI.
 *
 * صفحه index لیست بخش‌های قابل ایمپورت را نشان می‌دهد. کاربر بخش و اندازه
 * batch را انتخاب کرده، شروع می‌کند، و فرانت با چند درخواست AJAX پشت‌سرهم
 * متد batch را صدا می‌زند تا کار کامل شود.
 */
class LegacyImportController extends Controller
{
    public function index()
    {
        $sections = [];
        foreach ($this->importers() as $importer) {
            $sections[] = [
                'name' => $importer->name(),
                'label' => $importer->label(),
            ];
        }

        return view('crm::legacy-import.index', [
            'sections' => $sections,
            'connectionName' => 'legacy_wp',
        ]);
    }

    /** بررسی اتصال به دیتابیس قدیمی + شمارش کل بخش انتخابی. */
    public function status(Request $request): JsonResponse
    {
        $section = $request->string('section')->toString();
        $importer = $this->importer($section);
        if (! $importer) {
            return response()->json(['ok' => false, 'message' => 'بخش نامعتبر است.'], 422);
        }

        try {
            DB::connection('legacy_wp')->getPdo();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'خطا در اتصال به دیتابیس وردپرسی: ' . $e->getMessage(),
                'hint' => 'متغیرهای LEGACY_WP_DB_* در .env را بررسی کنید.',
            ], 500);
        }

        try {
            $total = $importer->totalCount();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'خطا در شمارش رکوردها: ' . $e->getMessage(),
                'hint' => 'پیشوند جدول‌ها (LEGACY_WP_DB_PREFIX) صحیح است؟',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'section' => $section,
            'total' => $total,
        ]);
    }

    /** اجرای یک batch از یک بخش (offset/limit). */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => 'required|string',
            'offset' => 'required|integer|min:0',
            'limit' => 'required|integer|min:1|max:5000',
        ]);

        $importer = $this->importer($validated['section']);
        if (! $importer) {
            return response()->json(['ok' => false, 'message' => 'بخش نامعتبر است.'], 422);
        }

        try {
            $result = $importer->processBatch($validated['offset'], $validated['limit']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'خطا در پردازش batch: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'offset' => $validated['offset'],
            'limit' => $validated['limit'],
            'result' => $result,
        ]);
    }

    /** @return AbstractImporter[] */
    protected function importers(): array
    {
        return [
            new CustomerImporter(),
            // در فازهای بعدی: orders, technicians, brands, devices, ...
        ];
    }

    protected function importer(string $name): ?AbstractImporter
    {
        foreach ($this->importers() as $importer) {
            if ($importer->name() === $name) {
                return $importer;
            }
        }

        return null;
    }
}
