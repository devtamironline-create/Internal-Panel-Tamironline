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
<title>صورتحساب خدمات - {{ $providerName }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<style>
  @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');

  :root{
    --navy:#1e2a78;
    --navy-2:#2b3a8c;
    --ink:#1b2340;
    --line:#9aa3c4;
    --line-soft:#c5cbe0;
    --head-bg:#dfe3f3;
    --head-bg-2:#eef0f9;
    --paper:#ffffff;
    --muted:#3a4160;
  }

  *{box-sizing:border-box;}

  body{
    margin:0;
    background:#e9ebf2;
    font-family:'Vazirmatn',Tahoma,sans-serif;
    color:var(--ink);
    padding:24px;
  }

  .sheet{
    width:1000px;
    max-width:100%;
    margin:0 auto;
    background:var(--paper);
    position:relative;
    padding:26px 34px 26px 34px;
    border:1px solid #d7dae6;
    box-shadow:0 10px 40px rgba(20,28,80,.12);
  }

  /* نوار تزئینی سمت چپ */
  .deco{
    position:absolute;
    top:14px;
    bottom:14px;
    left:14px;
    width:54px;
    border:2px solid var(--navy);
    background:
      repeating-linear-gradient(45deg,#c9cfe6 0 6px,transparent 6px 12px),
      repeating-linear-gradient(-45deg,#c9cfe6 0 6px,transparent 6px 12px);
    opacity:.85;
  }

  .inner{margin-left:64px;}

  /* هدر */
  .top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    margin-bottom:6px;
  }
  .meta{display:flex;gap:10px;}
  .meta .box{
    border:1.5px solid var(--navy);
    border-radius:20px;
    padding:6px 14px;
    font-size:13px;
    color:var(--ink);
    min-width:120px;
    white-space:nowrap;
  }
  .meta .box.short{min-width:86px;}

  .brand{
    display:flex;
    align-items:center;
    gap:10px;
    color:var(--navy);
    text-align:right;
  }
  .brand .logo{
    width:42px;height:42px;border-radius:50%;
    border:3px solid var(--navy);
    display:flex;align-items:center;justify-content:center;
    font-weight:800;color:var(--navy);
    transform:rotate(-15deg);
  }
  .brand .logo span{transform:rotate(15deg);font-size:20px;}
  .brand .name{line-height:1.2;}
  .brand .name b{font-size:20px;font-weight:800;letter-spacing:.5px;}
  .brand .name small{display:block;font-size:10px;color:var(--muted);font-weight:500;}

  /* عنوان */
  .title-row{
    display:flex;align-items:center;gap:18px;
    margin:14px 0 18px;
  }
  .title-row .rule{flex:1;height:2px;background:var(--navy);}
  .title-row h1{
    margin:0;font-size:30px;font-weight:800;color:var(--ink);
    white-space:nowrap;
  }

  /* بلوک اطلاعات */
  .info{
    border:1.5px solid var(--line);
    border-radius:6px;
    overflow:hidden;
  }
  .info .row{
    display:flex;
    border-bottom:1.5px solid var(--line);
    min-height:64px;
  }
  .info .row:last-child{border-bottom:none;}

  .label{
    flex:0 0 40px;
    background:var(--navy);
    color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:13px;
    border-left:1.5px solid var(--line);
  }
  .label span{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;}
  .row.r2 .label, .row.r3 .label{background:var(--navy-2);}

  .content{
    flex:1;
    display:flex;
    align-items:center;
    gap:6px;
    padding:10px 16px;
    flex-wrap:wrap;
  }
  .field{
    display:flex;align-items:baseline;gap:6px;
    font-size:13.5px;color:var(--ink);
    padding:4px 0;
  }
  .field .k{color:var(--muted);font-weight:600;white-space:nowrap;}
  .field .v{font-weight:700;}
  .field.grow{flex:1;}
  .sep{width:1px;align-self:stretch;background:var(--line-soft);margin:0 10px;}

  /* جدول خدمات */
  .services{margin-top:18px;border:1.5px solid var(--line);border-radius:6px;overflow:hidden;}
  .services .bar{
    background:var(--head-bg);
    text-align:center;font-weight:800;font-size:15px;color:var(--ink);
    padding:9px;border-bottom:1.5px solid var(--line);
  }
  table{width:100%;border-collapse:collapse;}
  th,td{border:1px solid var(--line);padding:10px 12px;font-size:13.5px;}
  thead th{background:var(--head-bg-2);font-weight:700;}
  .col-no{width:64px;text-align:center;}
  .col-price{width:170px;text-align:center;}
  td.desc{text-align:right;}
  tbody td{height:46px;}
  .total-row td{
    background:var(--head-bg-2);
    font-weight:800;font-size:15px;text-align:center;
  }
  .total-row .lbl{text-align:center;}

  /* فوتر */
  .footer{
    display:flex;align-items:flex-end;justify-content:space-between;
    gap:20px;margin-top:26px;
  }
  .qr{
    border:1.5px solid var(--line);border-radius:6px;
    width:150px;
  }
  .qr .cap{
    text-align:center;font-size:12px;font-weight:700;color:var(--muted);
    padding:6px;border-bottom:1.5px solid var(--line);
  }
  .qr .box{height:118px;display:flex;align-items:center;justify-content:center;padding:6px;}
  .qr .box img{max-width:100%;max-height:100%;}

  .holo{
    width:120px;height:120px;border-radius:50%;
    background:
      conic-gradient(from 0deg,#ffd1f0,#d1fff0,#d1e6ff,#fff6d1,#ffd1f0);
    opacity:.65;
    box-shadow:inset 0 0 18px rgba(255,255,255,.6);
  }

  .sign{display:flex;align-items:flex-end;gap:10px;}
  .sign .logo2{color:var(--navy);line-height:1.15;text-align:right;}
  .sign .logo2 b{font-size:18px;font-weight:800;display:block;}
  .sign .logo2 small{font-size:10px;color:var(--muted);display:block;}
  .sign .logo2 .web{font-size:9px;letter-spacing:1px;direction:ltr;text-align:left;display:block;}
  .sign .mark{
    width:38px;height:38px;border-radius:50%;border:3px solid var(--navy);
    display:flex;align-items:center;justify-content:center;
    transform:rotate(-15deg);font-weight:800;color:var(--navy);
    margin-bottom:14px;
  }
  .sign .mark span{transform:rotate(15deg);font-size:18px;}

  @media print{
    body{background:#fff;padding:0;}
    .sheet{box-shadow:none;border:none;width:100%;}
  }
</style>
</head>
<body>
  <div class="sheet">
    <div class="deco"></div>
    <div class="inner">

      <!-- هدر -->
      <div class="top">
        <div class="meta">
          <div class="box short">تاریخ: {{ $dateStr }}</div>
          <div class="box">شماره فاکتور: <span dir="ltr">{{ $invoice->invoice_code }}</span></div>
        </div>
        <div class="brand">
          <div class="name">
            <b>{{ $providerName }}</b>
            <small>{{ $providerTagline }}</small>
          </div>
          <div class="logo"><span>ت</span></div>
        </div>
      </div>

      <!-- عنوان -->
      <div class="title-row">
        <div class="rule"></div>
        <h1>صورتحساب خدمات</h1>
        <div class="rule"></div>
      </div>

      <!-- اطلاعات -->
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

      <!-- جدول خدمات -->
      <div class="services">
        <div class="bar">شرح خدمات صورت گرفته</div>
        <table>
          <thead>
            <tr>
              <th class="col-no">ردیف</th>
              <th>شرح خدمات</th>
              <th class="col-price">مبلغ کل (تومان)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($rows as $i => $row)
            <tr>
              <td class="col-no">{{ $fa($i + 1) }}</td>
              <td class="desc">{{ $row['title'] }}</td>
              <td class="col-price">{{ $money($row['total']) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
              <td class="lbl" colspan="2">جمع کل</td>
              <td>{{ $money($grandTotal) }} تومان</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- فوتر -->
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
</body>
</html>
