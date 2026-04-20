<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataRestoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage-permissions');
    }

    /**
     * صفحه اصلی — لیست جدول‌های قابل بازیابی
     */
    public function index()
    {
        $connected = false;
        $error = null;
        $backupTables = [];
        $currentTables = [];

        try {
            $backupDb = config('database.connections.backup.database');
            $currentDb = config('database.connections.mysql.database');

            // تست اتصال
            DB::connection('backup')->getPdo();
            $connected = true;

            // جدول‌های هر دو دیتابیس
            $bt = DB::connection('backup')->select('SHOW TABLES');
            $bcol = "Tables_in_" . $backupDb;
            foreach ($bt as $t) {
                $name = $t->$bcol;
                $count = DB::connection('backup')->table($name)->count();
                $backupTables[$name] = $count;
            }

            $ct = DB::select('SHOW TABLES');
            $ccol = "Tables_in_" . $currentDb;
            foreach ($ct as $t) {
                $name = $t->$ccol;
                $count = DB::table($name)->count();
                $currentTables[$name] = $count;
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        // جدول‌های مشترک
        $commonTables = array_intersect(array_keys($backupTables), array_keys($currentTables));
        sort($commonTables);

        return view('admin.restore.index', compact(
            'connected', 'error', 'backupTables', 'currentTables', 'commonTables'
        ));
    }

    /**
     * نمایش مقایسه داده‌های یک جدول
     */
    public function show(Request $request, string $table)
    {
        $this->validateTable($table);

        // ستون‌های جدول
        $columns = $this->getColumns($table);
        $primaryKey = $this->getPrimaryKey($table);

        // ستون‌های متنی (کاندیدای کاراکتر فارسی)
        $textColumns = $this->getTextColumns($table);

        $perPage = (int) $request->input('per_page', 20);
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        // فیلتر: فقط رکوردهایی که داده فارسی خراب دارن
        $onlyCorrupted = $request->boolean('only_corrupted', true);

        $currentQuery = DB::table($table);
        if ($onlyCorrupted && !empty($textColumns)) {
            $currentQuery->where(function ($q) use ($textColumns) {
                foreach ($textColumns as $col) {
                    $q->orWhere($col, 'LIKE', '%?%');
                }
            });
        }

        $totalCorrupted = (clone $currentQuery)->count();
        $currentRows = $currentQuery->orderBy($primaryKey)->offset($offset)->limit($perPage)->get();

        // پیدا کردن رکوردهای متناظر در بکاپ
        $ids = $currentRows->pluck($primaryKey)->toArray();
        $backupRows = collect();
        if (!empty($ids)) {
            $backupRows = DB::connection('backup')->table($table)
                ->whereIn($primaryKey, $ids)
                ->get()
                ->keyBy($primaryKey);
        }

        $totalPages = (int) ceil($totalCorrupted / $perPage);

        return view('admin.restore.show', compact(
            'table', 'columns', 'textColumns', 'primaryKey',
            'currentRows', 'backupRows', 'onlyCorrupted',
            'page', 'totalPages', 'perPage', 'totalCorrupted'
        ));
    }

    /**
     * بازیابی رکوردهای انتخاب شده
     */
    public function restore(Request $request, string $table)
    {
        $this->validateTable($table);

        $request->validate([
            'ids' => 'required|array|min:1',
            'columns' => 'required|array|min:1',
        ]);

        $ids = $request->input('ids');
        $columns = $request->input('columns');
        $primaryKey = $this->getPrimaryKey($table);
        $textColumns = $this->getTextColumns($table);

        // فقط ستون‌هایی که text هستن و انتخاب شدن
        $columnsToRestore = array_intersect($columns, $textColumns);
        if (empty($columnsToRestore)) {
            return back()->with('error', 'هیچ ستون متنی برای بازیابی انتخاب نشده');
        }

        $restored = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                $backupRow = DB::connection('backup')->table($table)
                    ->where($primaryKey, $id)
                    ->first();

                if (!$backupRow) {
                    $errors[] = "ID $id: یافت نشد در بکاپ";
                    continue;
                }

                $update = [];
                foreach ($columnsToRestore as $col) {
                    if (isset($backupRow->$col)) {
                        $update[$col] = $backupRow->$col;
                    }
                }

                if (!empty($update)) {
                    DB::table($table)->where($primaryKey, $id)->update($update);
                    $restored++;
                }
            } catch (\Exception $e) {
                $errors[] = "ID $id: " . $e->getMessage();
            }
        }

        $msg = "تعداد $restored رکورد بازیابی شد.";
        if (!empty($errors)) {
            $msg .= " خطاها: " . implode('; ', array_slice($errors, 0, 3));
        }

        return back()->with('success', $msg);
    }

    private function validateTable(string $table): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            abort(400, 'نام جدول نامعتبر');
        }

        // باید در هر دو دیتابیس باشه (بعد از regex امن برای inline)
        $exists = DB::select("SHOW TABLES LIKE '{$table}'");
        $backupExists = DB::connection('backup')->select("SHOW TABLES LIKE '{$table}'");
        if (empty($exists) || empty($backupExists)) {
            abort(404, 'جدول در یکی از دیتابیس‌ها وجود ندارد');
        }
    }

    private function getColumns(string $table): array
    {
        $cols = DB::select("SHOW COLUMNS FROM `{$table}`");
        return array_map(fn($c) => $c->Field, $cols);
    }

    private function getTextColumns(string $table): array
    {
        $cols = DB::select("SHOW COLUMNS FROM `{$table}`");
        $textTypes = ['varchar', 'text', 'mediumtext', 'longtext', 'tinytext', 'char'];
        $result = [];
        foreach ($cols as $c) {
            $type = strtolower(preg_replace('/\(.*$/', '', $c->Type));
            if (in_array($type, $textTypes)) {
                $result[] = $c->Field;
            }
        }
        return $result;
    }

    private function getPrimaryKey(string $table): string
    {
        $cols = DB::select("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        return $cols[0]->Column_name ?? 'id';
    }
}
