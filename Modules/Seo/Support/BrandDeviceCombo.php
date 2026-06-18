<?php

namespace Modules\Seo\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;

/**
 * نمایندهٔ در-حافظهٔ صفحهٔ «برند + دستگاه» (مثلاً /services/washing-machine/lg).
 *
 * این ترکیب یک ردیفِ DB ندارد؛ از روی دو موجودیتِ Device و Brand ساخته می‌شود
 * تا کلِ ماشینِ سئو (MetaResolver / VariableRenderer / SchemaGenerator) بدونِ
 * تغییر، متا و Service-schema تولید کند. چون متدِ seoMeta() ندارد، resolver
 * به‌درستی از قالب‌ها (نه override) استفاده می‌کند.
 */
class BrandDeviceCombo extends Model
{
    /** بدونِ persist — فقط حاملِ attribute در حافظه. */
    protected $guarded = [];

    public $timestamps = false;

    public $exists = false;

    public ?Device $deviceModel = null;

    public ?Brand $brandModel = null;
}
