@php
    use Modules\CRM\Models\CrmSetting;

    $providerName    = CrmSetting::get('invoice_provider_name', 'تعمیرآنلاین');
    $providerTagline = CrmSetting::get('invoice_provider_tagline', 'تعمیرات تخصصی لوازم خانگی');
    $providerPhone   = CrmSetting::get('invoice_provider_phone', '۰۲۱-۴۵۳۹۶');
    $providerPostal  = CrmSetting::get('invoice_provider_postal_code', '');
    $providerAddress = CrmSetting::get('invoice_provider_address', '');

    $customer = $invoice->customer;
    $order = $invoice->order;

    $custName   = $order?->customer_name   ?: ($customer->display_name ?? '');
    $custMobile = $order?->customer_mobile ?: ($customer->mobile ?? '');
    $custPhone  = $order?->customer_phone  ?: ($customer->phone ?? '');

    $cityName     = optional($order?->city)->name ?? '';
    $provinceName = optional($order?->province ?? null)->name ?? '';
    $orderAddr    = $order?->address ?? '';
    $custAddr     = trim(implode('، ', array_filter([$provinceName, $cityName, $orderAddr])));

    $serviceTypeMap = ['repair' => 'تعمیر', 'service' => 'سرویس'];
    $serviceType = $serviceTypeMap[$order?->order_type] ?? ($order?->order_type ?? '');
    $deviceName  = optional($order?->device)->name ?: '';
    $brandName   = optional($order?->brand)->name ?: '';

    $customerDesc = trim((string) ($order->invoice_descripotion ?? ''));
    $grandTotal = (int) ($invoice->total_amount ?? 0);

    $rows = collect();
    if ($customerDesc !== '') {
        $rows->push(['title' => $customerDesc, 'total' => $grandTotal]);
    } elseif ($order && $order->items->isNotEmpty()) {
        foreach ($order->items as $item) {
            $rows->push(['title' => $item->title, 'total' => (int) $item->total_price]);
        }
    } elseif ($order) {
        $titles = is_array($order->piece_list) ? $order->piece_list : [];
        $sells  = is_array($order->customer_price_list) ? $order->customer_price_list : [];
        foreach ($titles as $i => $title) {
            if ($title === '' || $title === null) continue;
            $unit = (int) ($sells[$i] ?? 0);
            $rows->push(['title' => is_string($title) ? $title : (string) ($title['title'] ?? ''), 'total' => $unit]);
        }
    }
    if ($rows->isEmpty()) {
        $rows->push(['title' => 'انجام خدمات', 'total' => $grandTotal]);
    }
    if ($rows->sum('total') === 0 && $grandTotal > 0) {
        $combinedTitle = $rows->pluck('title')->filter()->implode('، ');
        $rows = collect([['title' => $combinedTitle !== '' ? $combinedTitle : 'انجام خدمات', 'total' => $grandTotal]]);
    }
    if ($grandTotal === 0 && $rows->sum('total') > 0) {
        $grandTotal = (int) $rows->sum('total');
    }

    $fa = fn($s) => strtr((string) $s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    $money = fn($n) => $fa(number_format((int) $n));
    $dateStr = $fa(\Morilog\Jalali\Jalalian::fromDateTime($invoice->issued_at ?? $invoice->created_at)->format('Y/m/d'));
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>دانلود صورتحساب - {{ $invoice->invoice_code }}</title>
<style>
  @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');

  :root{
    --navy:#1e2a78; --navy-2:#2b3a8c; --ink:#1b2340; --line:#9aa3c4;
    --line-soft:#c5cbe0; --head-bg:#dfe3f3; --head-bg-2:#eef0f9; --paper:#fff; --muted:#3a4160;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:#e9ebf2;font-family:'Vazirmatn',Tahoma,sans-serif;color:var(--ink);padding:18px;}

  /* نوار ابزار (در PDF نمی‌آید چون فقط .sheet کپچر می‌شود) */
  .toolbar{max-width:1000px;margin:0 auto 14px;display:flex;gap:10px;justify-content:flex-start;flex-wrap:wrap;}
  .btn{border:none;border-radius:10px;padding:11px 22px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;}
  .btn-primary{background:var(--navy);color:#fff;}
  .btn-primary:disabled{opacity:.6;cursor:default;}
  .btn-ghost{background:#fff;color:var(--navy);border:1.5px solid var(--navy);}

  .sheet{width:1000px;max-width:100%;margin:0 auto;background:var(--paper);position:relative;padding:26px 34px;border:1px solid #d7dae6;box-shadow:0 10px 40px rgba(20,28,80,.12);}

  /* نوار تذهیب — تک‌گرادیان (سازگار با html2canvas) */
  .deco{position:absolute;top:14px;bottom:14px;left:14px;width:54px;border:2px solid var(--navy);
    background:repeating-linear-gradient(45deg,rgba(30,42,120,.20) 0 5px,rgba(255,255,255,0) 5px 11px);}

  .inner{margin-left:64px;}

  .top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:6px;}
  .meta{display:flex;gap:10px;}
  .meta .box{border:1.5px solid var(--navy);border-radius:20px;padding:6px 14px;font-size:13px;min-width:120px;white-space:nowrap;}
  .meta .box.short{min-width:86px;}

  .brand{display:flex;align-items:center;gap:10px;color:var(--navy);text-align:right;}
  .brand .logo{width:42px;height:42px;border-radius:50%;border:3px solid var(--navy);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--navy);transform:rotate(-15deg);}
  .brand .logo span{transform:rotate(15deg);font-size:20px;}
  .brand .name b{font-size:20px;font-weight:800;}
  .brand .name small{display:block;font-size:10px;color:var(--muted);}

  .title-row{display:flex;align-items:center;gap:18px;margin:14px 0 18px;}
  .title-row .rule{flex:1;height:2px;background:var(--navy);}
  .title-row h1{margin:0;font-size:30px;font-weight:800;white-space:nowrap;}

  .info{border:1.5px solid var(--line);border-radius:6px;overflow:hidden;}
  .info .row{display:flex;border-bottom:1.5px solid var(--line);min-height:64px;}
  .info .row:last-child{border-bottom:none;}

  /* لیبل عمودی — transform:rotate (سازگار با html2canvas) */
  .label{flex:0 0 40px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;border-left:1.5px solid var(--line);}
  .label span{display:inline-block;transform:rotate(-90deg);white-space:nowrap;}
  .row.r2 .label, .row.r3 .label{background:var(--navy-2);}

  .content{flex:1;display:flex;align-items:center;gap:6px;padding:10px 16px;flex-wrap:wrap;}
  .field{display:flex;align-items:baseline;gap:6px;font-size:13.5px;padding:4px 0;}
  .field .k{color:var(--muted);font-weight:600;white-space:nowrap;}
  .field .v{font-weight:700;}
  .field.grow{flex:1;}
  .sep{width:1px;align-self:stretch;background:var(--line-soft);margin:0 10px;}

  .services{margin-top:18px;border:1.5px solid var(--line);border-radius:6px;overflow:hidden;}
  .services .bar{background:var(--head-bg);text-align:center;font-weight:800;font-size:15px;padding:9px;border-bottom:1.5px solid var(--line);}
  table{width:100%;border-collapse:collapse;}
  th,td{border:1px solid var(--line);padding:10px 12px;font-size:13.5px;}
  thead th{background:var(--head-bg-2);font-weight:700;}
  .col-no{width:64px;text-align:center;}
  .col-price{width:170px;text-align:center;}
  td.desc{text-align:right;}
  tbody td{height:46px;}
  .total-row td{background:var(--head-bg-2);font-weight:800;font-size:15px;text-align:center;}

  .footer{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-top:26px;}
  .qr{border:1.5px solid var(--line);border-radius:6px;width:150px;}
  .qr .cap{text-align:center;font-size:12px;font-weight:700;color:var(--muted);padding:6px;border-bottom:1.5px solid var(--line);}
  .qr .box{height:118px;display:flex;align-items:center;justify-content:center;padding:6px;}
  .qr .box img{max-width:100%;max-height:100%;}

  /* هولوگرام — radial-gradient (سازگار با html2canvas) */
  .holo{width:120px;height:120px;border-radius:50%;background:radial-gradient(circle at 35% 30%,#ffd1f0,#d1e6ff 45%,#d1fff0 70%,#fff6d1);opacity:.75;border:1px solid #e6e0f0;}

  .sign{display:flex;align-items:flex-end;gap:10px;}
  .sign .logo2 b{font-size:18px;font-weight:800;display:block;color:var(--navy);}
  .sign .logo2 small{font-size:10px;color:var(--muted);display:block;}
  .sign .logo2 .web{font-size:9px;letter-spacing:1px;direction:ltr;text-align:left;display:block;}
  .sign .mark{width:38px;height:38px;border-radius:50%;border:3px solid var(--navy);display:flex;align-items:center;justify-content:center;transform:rotate(-15deg);font-weight:800;color:var(--navy);margin-bottom:14px;}
  .sign .mark span{transform:rotate(15deg);font-size:18px;}
</style>
</head>
<body>
  <div class="toolbar">
    <button id="dl" class="btn btn-primary" onclick="downloadPdf()">دانلود فاکتور (PDF)</button>
    <button class="btn btn-ghost" onclick="window.print()">چاپ</button>
  </div>

  <div class="sheet" id="sheet">
    <div class="deco"></div>
    <div class="inner">

      <div class="top">
        <div class="meta">
          <div class="box short">تاریخ: {{ $dateStr }}</div>
          <div class="box">شماره فاکتور: <span dir="ltr">{{ $invoice->invoice_code }}</span></div>
        </div>
        <div class="brand">
          <div class="name"><b>{{ $providerName }}</b><small>{{ $providerTagline }}</small></div>
          <div class="logo"><span>ت</span></div>
        </div>
      </div>

      <div class="title-row">
        <div class="rule"></div><h1>صورتحساب خدمات</h1><div class="rule"></div>
      </div>

      <div class="info">
        <div class="row r1">
          <div class="label"><span>ارائه دهنده</span></div>
          <div class="content">
            <div class="field"><span class="k">نام :</span><span class="v">{{ $providerName }}</span></div>
            <div class="sep"></div>
            <div class="field"><span class="k">شماره تلفن:</span><span class="v" dir="ltr">{{ $fa($providerPhone) }}</span></div>
            <div class="sep"></div>
            <div class="field grow"><span class="k">آدرس:</span><span class="v">{{ $providerAddress }}</span></div>
            <div class="sep"></div>
            <div class="field"><span class="k">کد پستی:</span><span class="v" dir="ltr">{{ $fa($providerPostal) }}</span></div>
          </div>
        </div>

        <div class="row r2">
          <div class="label"><span>مشتری</span></div>
          <div class="content">
            <div class="field grow"><span class="k">نام :</span><span class="v">{{ $custName }}</span></div>
            <div class="sep"></div>
            <div class="field grow"><span class="k">شماره تلفن:</span><span class="v" dir="ltr">{{ $fa($custMobile ?: $custPhone) }}</span></div>
            <div class="sep"></div>
            <div class="field grow"><span class="k">آدرس:</span><span class="v">{{ $custAddr }}</span></div>
          </div>
        </div>

        <div class="row r3">
          <div class="label"><span>اطلاعات خدمات</span></div>
          <div class="content">
            <div class="field grow"><span class="k">نوع خدمت :</span><span class="v">{{ $serviceType }}</span></div>
            <div class="sep"></div>
            <div class="field grow"><span class="k">دستگاه:</span><span class="v">{{ $deviceName }}</span></div>
            <div class="sep"></div>
            <div class="field grow"><span class="k">برند:</span><span class="v">{{ $brandName }}</span></div>
          </div>
        </div>
      </div>

      <div class="services">
        <div class="bar">شرح خدمات صورت گرفته</div>
        <table>
          <thead><tr><th class="col-no">ردیف</th><th>شرح خدمات</th><th class="col-price">مبلغ کل (تومان)</th></tr></thead>
          <tbody>
            @foreach($rows as $i => $row)
            <tr><td class="col-no">{{ $fa($i + 1) }}</td><td class="desc">{{ $row['title'] }}</td><td class="col-price">{{ $money($row['total']) }}</td></tr>
            @endforeach
            <tr class="total-row"><td colspan="2">جمع کل</td><td>{{ $money($grandTotal) }} تومان</td></tr>
          </tbody>
        </table>
      </div>

      <div class="footer">
        <div class="sign">
          <div class="mark"><span>ت</span></div>
          <div class="logo2">
            <b>{{ $providerName }}</b>
            <small>چتری برای خانه‌ی تو!</small>
            <small>سامانه تخصصی سرویس و تعمیر در محل</small>
            <span class="web">www.tamironline.com</span>
          </div>
        </div>
        <div class="holo"></div>
        <div class="qr">
          <div class="cap">اعتبارسنجی QR-CODE</div>
          <div class="box">
            @if(! empty($qrDataUri))<img src="{{ $qrDataUri }}" alt="QR">@endif
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
  <script>
    const FILE_NAME = @json($invoice->invoice_code) + '.pdf';

    async function downloadPdf() {
      const btn = document.getElementById('dl');
      const original = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'در حال ساخت فایل...';
      try {
        // منتظر لود شدن فونت وزیر می‌مانیم تا متن در تصویر درست بیفتد؛ ولی
        // اگر فونت کند/مسدود بود حداکثر ۳ ثانیه صبر می‌کنیم تا معلق نماند.
        await Promise.race([
          (document.fonts && document.fonts.ready) || Promise.resolve(),
          new Promise(function (r) { setTimeout(r, 3000); }),
        ]);

        const el = document.getElementById('sheet');
        const canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
        const imgData = canvas.toDataURL('image/png');

        const { jsPDF } = window.jspdf;
        // طرح عریض است → A4 افقی بهترین برازش را می‌دهد.
        const orientation = canvas.width >= canvas.height ? 'landscape' : 'portrait';
        const pdf = new jsPDF({ orientation, unit: 'mm', format: 'a4' });

        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();

        // برازش با حفظ نسبت تصویر و مرکز‌چینی.
        let w = pageW, h = canvas.height * pageW / canvas.width;
        if (h > pageH) { h = pageH; w = canvas.width * pageH / canvas.height; }
        const x = (pageW - w) / 2, y = (pageH - h) / 2;

        pdf.addImage(imgData, 'PNG', x, y, w, h, undefined, 'FAST');
        pdf.save(FILE_NAME);
      } catch (e) {
        alert('خطا در ساخت PDF: ' + (e && e.message ? e.message : e));
      } finally {
        btn.disabled = false;
        btn.textContent = original;
      }
    }
  </script>
</body>
</html>
