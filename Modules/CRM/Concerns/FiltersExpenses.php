<?php

namespace Modules\CRM\Concerns;

use Illuminate\Http\Request;
use Modules\CRM\Models\ExpenseCategory;

/**
 * فیلترهای مشترک لیست هزینه‌ها و گزارش‌ها — طبق فاز ۱:
 * بازهٔ تاریخ شمسی، دسته اصلی، زیر دسته، حساب پرداخت، پرداخت‌کننده،
 * تگ، بازهٔ مبلغ.
 */
trait FiltersExpenses
{
    /** خواندن فیلترها از request به‌صورت آرایهٔ نرمال‌شده. */
    protected function expenseFilters(Request $request): array
    {
        return [
            'from_date'   => $request->string('from_date')->toString(),
            'to_date'     => $request->string('to_date')->toString(),
            'category_id' => $request->integer('category_id') ?: null,      // دسته اصلی
            'child_id'    => $request->integer('child_id') ?: null,         // زیر دسته
            'account_id'  => $request->integer('account_id') ?: null,
            'payer'       => trim($request->string('payer')->toString()),
            'tag_id'      => $request->integer('tag_id') ?: null,
            'amount_min'  => $this->normalizeAmount($request->input('amount_min')),
            'amount_max'  => $this->normalizeAmount($request->input('amount_max')),
        ];
    }

    /** اعمال فیلترها روی کوئری crm_expenses. */
    protected function applyExpenseFilters($query, array $f)
    {
        $fromG = $this->expenseJalaliToGregorian($f['from_date']);
        $toG   = $this->expenseJalaliToGregorian($f['to_date']);
        if ($fromG) $query->whereDate('paid_at', '>=', $fromG);
        if ($toG)   $query->whereDate('paid_at', '<=', $toG);

        if ($f['child_id']) {
            $query->where('category_id', $f['child_id']);
        } elseif ($f['category_id']) {
            // دسته اصلی → همهٔ زیر دسته‌هایش
            $childIds = ExpenseCategory::where('parent_id', $f['category_id'])->pluck('id');
            $query->whereIn('category_id', $childIds->isEmpty() ? [-1] : $childIds);
        }

        if ($f['account_id']) $query->where('payment_account_id', $f['account_id']);
        if ($f['payer'] !== '') $query->where('payer', 'like', '%' . $f['payer'] . '%');

        if ($f['tag_id']) {
            $query->whereHas('tags', fn ($q) => $q->where('crm_expense_tags.id', $f['tag_id']));
        }

        if ($f['amount_min'] !== null) $query->where('amount', '>=', $f['amount_min']);
        if ($f['amount_max'] !== null) $query->where('amount', '<=', $f['amount_max']);

        return $query;
    }

    /** آیا فیلتری فعال است؟ (برای باز نگه داشتن پنل فیلتر در UI) */
    protected function hasActiveExpenseFilter(array $f): bool
    {
        return $f['from_date'] !== '' || $f['to_date'] !== ''
            || $f['category_id'] || $f['child_id'] || $f['account_id']
            || $f['payer'] !== '' || $f['tag_id']
            || $f['amount_min'] !== null || $f['amount_max'] !== null;
    }

    /** مبلغ ورودی با جداکننده/ارقام فارسی → integer تومان (یا null). */
    protected function normalizeAmount($value): ?int
    {
        if ($value === null) return null;
        $latin = strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $clean = preg_replace('/[^\d]/', '', $latin);

        return $clean === '' ? null : (int) $clean;
    }

    /** تاریخ شمسی Y/m/d (ارقام فارسی هم مجاز) → Y-m-d میلادی یا null. */
    protected function expenseJalaliToGregorian(?string $jalaliDate): ?string
    {
        if (! $jalaliDate || trim($jalaliDate) === '') {
            return null;
        }

        $latin = strtr($jalaliDate, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $latin = str_replace('-', '/', trim($latin));

        try {
            return \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $latin)
                ->toCarbon()
                ->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
