<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تراکنش آرشیوی کیف‌پول — فقط برای نمایش تاریخی.
 *
 * ⚠️ این مدل و جدولش هرگز نباید در محاسبهٔ هیچ مقدار مالی استفاده شود.
 *
 * تأیید با grep:
 *   grep -rn "WalletArchiveTx" Modules/ app/
 *   باید فقط در WalletArchiveService و WalletController::show ظاهر شود.
 *
 * هیچ‌گونه relation (belongsTo / hasMany) به Technician / Order / Invoice
 * تعریف نمی‌شود — جدول کاملاً مستقل است و حذف رکورد در هیچ‌جای دیگر
 * تأثیر cascading ندارد.
 */
class WalletArchiveTx extends Model
{
    protected $table = 'crm_wallet_archive_txs';

    public $timestamps = false; // imported_at را خودمان مدیریت می‌کنیم

    protected $fillable = [
        'wp_post_id',
        'technician_id',
        'technician_wp_id',
        'order_wp_id',
        'type',
        'amount',
        'note',
        'refid',
        'payment_status',
        'wp_created_at',
        'imported_at',
    ];

    protected $casts = [
        'wp_post_id' => 'integer',
        'technician_id' => 'integer',
        'technician_wp_id' => 'integer',
        'order_wp_id' => 'integer',
        'amount' => 'integer',
        'payment_status' => 'integer',
        'wp_created_at' => 'datetime',
        'imported_at' => 'datetime',
    ];
}
