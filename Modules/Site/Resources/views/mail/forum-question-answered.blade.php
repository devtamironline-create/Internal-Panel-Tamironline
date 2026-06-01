@component('mail::message')
# سلام {{ $question->author_name }} 👋

به سوال شما در انجمن تعمیرآنلاین پاسخ داده شد:

> **{{ $question->title }}**

---

## پاسخ:

{!! strip_tags($answer->body, ['<p>', '<br>', '<strong>', '<em>', '<ul>', '<ol>', '<li>', '<a>']) !!}

---

@if($answer->is_expert_reply)
👨‍🔧 این پاسخ توسط **{{ $answer->author_name }}** (کارشناس تعمیرآنلاین) ثبت شده است.
@else
این پاسخ توسط **{{ $answer->author_name }}** ثبت شده است.
@endif

@component('mail::button', ['url' => $publicUrl])
مشاهده پاسخ کامل
@endcomponent

اگر پاسخ مشکل شما را حل کرد، در صفحه‌ی سوال آن را به‌عنوان «حل‌شده» علامت بزنید.

با تشکر،
تیم {{ config('app.name', 'تعمیرآنلاین') }}
@endcomponent
