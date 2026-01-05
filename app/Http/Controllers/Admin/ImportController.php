<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use Modules\Customer\Models\Customer;
use Modules\Product\Models\Product;
use Modules\Service\Models\Service;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    // Persian month names to number
    private array $persianMonths = [
        'فروردین' => 1,
        'اردیبهشت' => 2,
        'خرداد' => 3,
        'تیر' => 4,
        'مرداد' => 5,
        'شهریور' => 6,
        'مهر' => 7,
        'آبان' => 8,
        'آذر' => 9,
        'دی' => 10,
        'بهمن' => 11,
        'اسفند' => 12,
    ];

    // Column name mappings (different possible names for each field)
    private array $columnMappings = [
        'customer' => ['مشتری', 'نام مشتری', 'مشتري', 'customer', 'name'],
        'mobile' => ['موبایل', 'شماره موبایل', 'تلفن', 'mobile', 'phone', 'شماره تماس'],
        'domain' => ['نام دامنه', 'دامنه', 'domain', 'آدرس سایت'],
        'amount' => ['مبلغ', 'قیمت', 'price', 'amount'],
        'year' => ['سال', 'year'],
        'month' => ['ماه', 'month'],
        'day' => ['روز', 'day'],
        'server' => ['سرور', 'server', 'شماره سرور'],
        'billing_cycle' => ['مدت تمدید', 'مدت', 'دوره', 'cycle'],
    ];

    public function index()
    {
        $servers = Server::active()->get();
        $products = Product::active()->get();

        return view('admin.import.index', compact('servers', 'products'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (empty($rows)) {
            return back()->with('error', 'فایل خالی است');
        }

        // Parse header row to find column indexes
        $headers = $rows[0];
        $columnMap = $this->mapColumns($headers);

        // Debug: show what we found
        $debugInfo = [];
        foreach ($columnMap as $field => $index) {
            $debugInfo[$field] = $index !== null ? "ستون " . ($index + 1) . " ({$headers[$index]})" : "یافت نشد";
        }

        // Skip header row and parse data
        $data = [];
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Check if row has data
            if (empty(array_filter($row))) continue;

            try {
                $parsed = $this->parseRow($row, $i + 1, $columnMap);
                if ($parsed) {
                    $data[] = $parsed;
                }
            } catch (\Exception $e) {
                $errors[] = "ردیف " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        $servers = Server::active()->get();
        $products = Product::active()->with('prices')->get();

        // Add yearly price to each product
        foreach ($products as $product) {
            $yearlyPrice = $product->prices->where('billing_cycle', 'annually')->where('is_active', true)->first();
            $product->yearly_price = $yearlyPrice ? $yearlyPrice->final_price : ($product->base_price ?? 0);
        }

        return view('admin.import.preview', compact('data', 'errors', 'servers', 'products', 'debugInfo'));
    }

    private function mapColumns(array $headers): array
    {
        $columnMap = [
            'customer' => null,
            'mobile' => null,
            'domain' => null,
            'amount' => null,
            'year' => null,
            'month' => null,
            'day' => null,
            'server' => null,
            'billing_cycle' => null,
        ];

        foreach ($headers as $index => $header) {
            $header = trim((string)$header);
            $headerLower = mb_strtolower($header);

            foreach ($this->columnMappings as $field => $possibleNames) {
                foreach ($possibleNames as $name) {
                    if ($headerLower === mb_strtolower($name) || str_contains($headerLower, mb_strtolower($name))) {
                        if ($columnMap[$field] === null) {
                            $columnMap[$field] = $index;
                        }
                        break 2;
                    }
                }
            }
        }

        return $columnMap;
    }

    private function parseRow(array $row, int $rowNumber, array $columnMap): ?array
    {
        // Get values based on column mapping
        $customerName = $this->getValueByMap($row, $columnMap, 'customer');
        $mobile = $this->getValueByMap($row, $columnMap, 'mobile');
        $domain = $this->getValueByMap($row, $columnMap, 'domain');
        $amount = $this->getValueByMap($row, $columnMap, 'amount');
        $year = $this->getValueByMap($row, $columnMap, 'year');
        $month = $this->getValueByMap($row, $columnMap, 'month');
        $day = $this->getValueByMap($row, $columnMap, 'day');
        $server = $this->getValueByMap($row, $columnMap, 'server');
        $billingCycle = $this->getValueByMap($row, $columnMap, 'billing_cycle');

        // Skip empty rows
        if (empty($customerName) && empty($domain)) {
            return null;
        }

        // Convert Persian numbers to English
        $year = $this->persianToEnglish($year);
        $day = $this->persianToEnglish($day);
        $amount = $this->persianToEnglish(str_replace(',', '', $amount));
        $server = $this->persianToEnglish($server);
        $mobile = $this->persianToEnglish($mobile);

        // Convert month name to number (with better matching)
        $monthNumber = $this->getMonthNumber($month);

        // Parse renewal date (next_due_date)
        $nextDueDate = null;
        if ($year && $monthNumber && $day) {
            try {
                // Ensure proper padding
                $monthPadded = str_pad($monthNumber, 2, '0', STR_PAD_LEFT);
                $dayPadded = str_pad($day, 2, '0', STR_PAD_LEFT);

                $nextDueDate = Jalalian::fromFormat('Y/m/d', "{$year}/{$monthPadded}/{$dayPadded}")
                    ->toCarbon()
                    ->toDateString();
            } catch (\Exception $e) {
                // Try alternative format
                try {
                    $nextDueDate = Jalalian::fromFormat('Y-m-d', "{$year}-{$monthNumber}-{$day}")
                        ->toCarbon()
                        ->toDateString();
                } catch (\Exception $e2) {
                    // Invalid date - will show raw values
                }
            }
        }

        // Determine product based on amount
        $productId = $this->guessProductByAmount((float)$amount);

        // Parse customer name
        $customerName = trim($customerName);
        $nameParts = preg_split('/\s+/', $customerName, 2);
        $firstName = $nameParts[0] ?? $customerName;
        $lastName = $nameParts[1] ?? '';

        // Validate/format mobile
        if ($mobile && !preg_match('/^09\d{9}$/', $mobile)) {
            // Try to fix mobile format
            $mobile = preg_replace('/[^0-9]/', '', $mobile);
            if (strlen($mobile) === 10 && $mobile[0] === '9') {
                $mobile = '0' . $mobile;
            }
        }

        return [
            'row_number' => $rowNumber,
            'server_name' => $server,
            'year' => $year,
            'month' => $month,
            'month_number' => $monthNumber,
            'day' => $day,
            'amount' => (float)$amount,
            'billing_cycle' => $billingCycle,
            'domain' => $domain,
            'customer_name' => $customerName,
            'mobile' => $mobile,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'next_due_date' => $nextDueDate,
            'product_id' => $productId,
            'server_id' => null,
        ];
    }

    private function getValueByMap(array $row, array $columnMap, string $field): string
    {
        $index = $columnMap[$field] ?? null;
        if ($index === null || !isset($row[$index])) {
            return '';
        }
        return trim((string)$row[$index]);
    }

    private function cleanValue($value): string
    {
        if (is_null($value)) return '';
        return trim((string)$value);
    }

    private function persianToEnglish(string $str): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $str = str_replace($persian, $english, $str);
        $str = str_replace($arabic, $english, $str);

        return $str;
    }

    private function getMonthNumber(string $month): ?int
    {
        $month = trim($month);

        // Direct match
        if (isset($this->persianMonths[$month])) {
            return $this->persianMonths[$month];
        }

        // If it's already a number
        $monthNum = $this->persianToEnglish($month);
        if (is_numeric($monthNum)) {
            $num = (int)$monthNum;
            return ($num >= 1 && $num <= 12) ? $num : null;
        }

        // Fuzzy match - remove extra characters and try again
        $cleanMonth = preg_replace('/[^\p{Arabic}\p{L}]/u', '', $month);
        if (isset($this->persianMonths[$cleanMonth])) {
            return $this->persianMonths[$cleanMonth];
        }

        // Try partial match
        foreach ($this->persianMonths as $name => $num) {
            if (str_contains($month, $name) || str_contains($name, $month)) {
                return $num;
            }
        }

        return null;
    }

    private function guessProductByAmount(float $amount): ?int
    {
        $product = Product::where('base_price', $amount)->first();
        return $product?->id;
    }

    public function process(Request $request)
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.import' => 'boolean',
            'data.*.customer_name' => 'required|string',
            'data.*.mobile' => 'nullable|string',
            'data.*.domain' => 'nullable|string',
            'data.*.product_id' => 'required|exists:products,id',
            'data.*.server_id' => 'nullable|exists:servers,id',
            'data.*.amount' => 'required|numeric',
            'data.*.next_due_date' => 'nullable|date',
        ]);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->input('data') as $index => $row) {
                // Skip if not marked for import
                if (empty($row['import'])) {
                    $skipped++;
                    continue;
                }

                try {
                    // Find or create customer by name or mobile
                    $customerName = trim($row['customer_name']);
                    $mobile = trim($row['mobile'] ?? '');
                    $nameParts = preg_split('/\s+/', $customerName, 2);
                    $firstName = $nameParts[0] ?? $customerName;
                    $lastName = $nameParts[1] ?? '';

                    // First try to find by mobile if provided
                    $customer = null;
                    if ($mobile && preg_match('/^09\d{9}$/', $mobile)) {
                        $customer = Customer::where('mobile', $mobile)->first();
                    }

                    // If not found by mobile, try by name
                    if (!$customer) {
                        $customer = Customer::where('first_name', $firstName)
                            ->where('last_name', $lastName)
                            ->first();
                    }

                    if (!$customer) {
                        // Use provided mobile or generate unique placeholder
                        $customerMobile = ($mobile && preg_match('/^09\d{9}$/', $mobile))
                            ? $mobile
                            : '090' . str_pad(Customer::count() + 1, 8, '0', STR_PAD_LEFT);

                        $customer = Customer::create([
                            'first_name' => $firstName,
                            'last_name' => $lastName ?: 'نامشخص',
                            'mobile' => $customerMobile,
                            'is_active' => true,
                        ]);
                    }

                    // Calculate start_date from next_due_date (1 year before)
                    $nextDueDate = !empty($row['next_due_date'])
                        ? \Carbon\Carbon::parse($row['next_due_date'])
                        : now()->addYear();
                    $startDate = $nextDueDate->copy()->subYear();

                    // Create service
                    Service::create([
                        'customer_id' => $customer->id,
                        'product_id' => $row['product_id'],
                        'server_id' => $row['server_id'] ?: null,
                        'order_number' => Service::generateOrderNumber(),
                        'domain' => $row['domain'] ?? '',
                        'billing_cycle' => 'annually',
                        'price' => $row['amount'],
                        'setup_fee' => 0,
                        'discount_amount' => 0,
                        'start_date' => $startDate,
                        'next_due_date' => $nextDueDate,
                        'status' => 'active',
                        'auto_renew' => false,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "ردیف " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            return redirect()->route('admin.import.index')
                ->with('success', "وارد شد: {$imported} | رد شد: {$skipped}")
                ->with('import_errors', $errors);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'خطا در وارد کردن: ' . $e->getMessage());
        }
    }
}
