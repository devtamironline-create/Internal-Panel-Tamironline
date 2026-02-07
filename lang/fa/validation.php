<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute باید پذیرفته شود.',
    'accepted_if' => ':attribute زمانی که :other برابر با :value است، باید پذیرفته شود.',
    'active_url' => ':attribute یک آدرس اینترنتی معتبر نیست.',
    'after' => ':attribute باید تاریخی بعد از :date باشد.',
    'after_or_equal' => ':attribute باید تاریخی برابر یا بعد از :date باشد.',
    'alpha' => ':attribute باید فقط شامل حروف باشد.',
    'alpha_dash' => ':attribute باید فقط شامل حروف، اعداد، خط تیره و زیرخط باشد.',
    'alpha_num' => ':attribute باید فقط شامل حروف و اعداد باشد.',
    'array' => ':attribute باید یک آرایه باشد.',
    'ascii' => ':attribute باید فقط شامل کاراکترهای تک‌بایتی و نمادهای حروف‌الفبایی باشد.',
    'before' => ':attribute باید تاریخی قبل از :date باشد.',
    'before_or_equal' => ':attribute باید تاریخی برابر یا قبل از :date باشد.',
    'between' => [
        'array' => ':attribute باید بین :min و :max آیتم داشته باشد.',
        'file' => ':attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => ':attribute باید بین :min و :max باشد.',
        'string' => ':attribute باید بین :min و :max کاراکتر باشد.',
    ],
    'boolean' => ':attribute باید true یا false باشد.',
    'can' => ':attribute شامل مقدار غیرمجاز است.',
    'confirmed' => 'تأییدیه :attribute مطابقت ندارد.',
    'contains' => ':attribute فاقد مقدار مورد نیاز است.',
    'current_password' => 'رمز عبور نادرست است.',
    'date' => ':attribute یک تاریخ معتبر نیست.',
    'date_equals' => ':attribute باید تاریخی برابر با :date باشد.',
    'date_format' => ':attribute با فرمت :format مطابقت ندارد.',
    'decimal' => ':attribute باید :decimal رقم اعشار داشته باشد.',
    'declined' => ':attribute باید رد شود.',
    'declined_if' => ':attribute زمانی که :other برابر با :value است، باید رد شود.',
    'different' => ':attribute و :other باید متفاوت باشند.',
    'digits' => ':attribute باید :digits رقم باشد.',
    'digits_between' => ':attribute باید بین :min و :max رقم باشد.',
    'dimensions' => ':attribute دارای ابعاد تصویر نامعتبر است.',
    'distinct' => ':attribute دارای مقدار تکراری است.',
    'doesnt_end_with' => ':attribute نباید با یکی از موارد زیر پایان یابد: :values.',
    'doesnt_start_with' => ':attribute نباید با یکی از موارد زیر شروع شود: :values.',
    'email' => ':attribute باید یک آدرس ایمیل معتبر باشد.',
    'ends_with' => ':attribute باید با یکی از موارد زیر پایان یابد: :values.',
    'enum' => ':attribute انتخاب شده نامعتبر است.',
    'exists' => ':attribute انتخاب شده نامعتبر است.',
    'extensions' => ':attribute باید یکی از پسوندهای زیر را داشته باشد: :values.',
    'file' => ':attribute باید یک فایل باشد.',
    'filled' => ':attribute باید دارای مقدار باشد.',
    'gt' => [
        'array' => ':attribute باید بیشتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید بزرگتر از :value کیلوبایت باشد.',
        'numeric' => ':attribute باید بزرگتر از :value باشد.',
        'string' => ':attribute باید بیشتر از :value کاراکتر داشته باشد.',
    ],
    'gte' => [
        'array' => ':attribute باید :value آیتم یا بیشتر داشته باشد.',
        'file' => ':attribute باید بزرگتر یا برابر با :value کیلوبایت باشد.',
        'numeric' => ':attribute باید بزرگتر یا برابر با :value باشد.',
        'string' => ':attribute باید بیشتر یا برابر با :value کاراکتر داشته باشد.',
    ],
    'hex_color' => ':attribute باید یک کد رنگ هگزادسیمال معتبر باشد.',
    'image' => ':attribute باید یک تصویر باشد.',
    'in' => ':attribute انتخاب شده نامعتبر است.',
    'in_array' => ':attribute در :other وجود ندارد.',
    'integer' => ':attribute باید یک عدد صحیح باشد.',
    'ip' => ':attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => ':attribute باید یک آدرس IPv4 معتبر باشد.',
    'ipv6' => ':attribute باید یک آدرس IPv6 معتبر باشد.',
    'json' => ':attribute باید یک رشته JSON معتبر باشد.',
    'list' => ':attribute باید یک لیست باشد.',
    'lowercase' => ':attribute باید با حروف کوچک باشد.',
    'lt' => [
        'array' => ':attribute باید کمتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید کوچکتر از :value کیلوبایت باشد.',
        'numeric' => ':attribute باید کوچکتر از :value باشد.',
        'string' => ':attribute باید کمتر از :value کاراکتر داشته باشد.',
    ],
    'lte' => [
        'array' => ':attribute نباید بیشتر از :value آیتم داشته باشد.',
        'file' => ':attribute باید کوچکتر یا برابر با :value کیلوبایت باشد.',
        'numeric' => ':attribute باید کوچکتر یا برابر با :value باشد.',
        'string' => ':attribute باید کمتر یا برابر با :value کاراکتر داشته باشد.',
    ],
    'mac_address' => ':attribute باید یک آدرس MAC معتبر باشد.',
    'max' => [
        'array' => ':attribute نباید بیشتر از :max آیتم داشته باشد.',
        'file' => ':attribute نباید بزرگتر از :max کیلوبایت باشد.',
        'numeric' => ':attribute نباید بزرگتر از :max باشد.',
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'max_digits' => ':attribute نباید بیشتر از :max رقم داشته باشد.',
    'mimes' => ':attribute باید فایلی از نوع :values باشد.',
    'mimetypes' => ':attribute باید فایلی از نوع :values باشد.',
    'min' => [
        'array' => ':attribute باید حداقل :min آیتم داشته باشد.',
        'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => ':attribute باید حداقل :min باشد.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
    ],
    'min_digits' => ':attribute باید حداقل :min رقم داشته باشد.',
    'missing' => ':attribute نباید موجود باشد.',
    'missing_if' => ':attribute زمانی که :other برابر با :value است، نباید موجود باشد.',
    'missing_unless' => ':attribute نباید موجود باشد مگر :other برابر با :value باشد.',
    'missing_with' => ':attribute زمانی که :values موجود است، نباید موجود باشد.',
    'missing_with_all' => ':attribute زمانی که :values موجود هستند، نباید موجود باشد.',
    'multiple_of' => ':attribute باید مضربی از :value باشد.',
    'not_in' => ':attribute انتخاب شده نامعتبر است.',
    'not_regex' => 'فرمت :attribute نامعتبر است.',
    'numeric' => ':attribute باید یک عدد باشد.',
    'password' => [
        'letters' => ':attribute باید حداقل یک حرف داشته باشد.',
        'mixed' => ':attribute باید حداقل یک حرف بزرگ و یک حرف کوچک داشته باشد.',
        'numbers' => ':attribute باید حداقل یک عدد داشته باشد.',
        'symbols' => ':attribute باید حداقل یک نماد داشته باشد.',
        'uncompromised' => ':attribute وارد شده در یک نشت داده ظاهر شده است. لطفاً یک :attribute دیگر انتخاب کنید.',
    ],
    'present' => ':attribute باید موجود باشد.',
    'present_if' => ':attribute زمانی که :other برابر با :value است، باید موجود باشد.',
    'present_unless' => ':attribute باید موجود باشد مگر :other برابر با :value باشد.',
    'present_with' => ':attribute زمانی که :values موجود است، باید موجود باشد.',
    'present_with_all' => ':attribute زمانی که :values موجود هستند، باید موجود باشد.',
    'prohibited' => ':attribute ممنوع است.',
    'prohibited_if' => ':attribute زمانی که :other برابر با :value است، ممنوع است.',
    'prohibited_unless' => ':attribute ممنوع است مگر :other در :values باشد.',
    'prohibits' => ':attribute ممنوع می‌کند که :other موجود باشد.',
    'regex' => 'فرمت :attribute نامعتبر است.',
    'required' => ':attribute الزامی است.',
    'required_array_keys' => ':attribute باید شامل ورودی‌هایی برای :values باشد.',
    'required_if' => ':attribute زمانی که :other برابر با :value است، الزامی است.',
    'required_if_accepted' => ':attribute زمانی که :other پذیرفته شده است، الزامی است.',
    'required_if_declined' => ':attribute زمانی که :other رد شده است، الزامی است.',
    'required_unless' => ':attribute الزامی است مگر :other در :values باشد.',
    'required_with' => ':attribute زمانی که :values موجود است، الزامی است.',
    'required_with_all' => ':attribute زمانی که :values موجود هستند، الزامی است.',
    'required_without' => ':attribute زمانی که :values موجود نیست، الزامی است.',
    'required_without_all' => ':attribute زمانی که هیچکدام از :values موجود نیستند، الزامی است.',
    'same' => ':attribute و :other باید مطابقت داشته باشند.',
    'size' => [
        'array' => ':attribute باید شامل :size آیتم باشد.',
        'file' => ':attribute باید :size کیلوبایت باشد.',
        'numeric' => ':attribute باید :size باشد.',
        'string' => ':attribute باید :size کاراکتر باشد.',
    ],
    'starts_with' => ':attribute باید با یکی از موارد زیر شروع شود: :values.',
    'string' => ':attribute باید یک رشته متنی باشد.',
    'timezone' => ':attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => ':attribute قبلاً استفاده شده است.',
    'uploaded' => 'آپلود :attribute ناموفق بود.',
    'uppercase' => ':attribute باید با حروف بزرگ باشد.',
    'url' => ':attribute باید یک آدرس اینترنتی معتبر باشد.',
    'ulid' => ':attribute باید یک ULID معتبر باشد.',
    'uuid' => ':attribute باید یک UUID معتبر باشد.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message a little cleaner.
    |
    */

    'attributes' => [
        'name' => 'نام',
        'username' => 'نام کاربری',
        'email' => 'ایمیل',
        'password' => 'رمز عبور',
        'password_confirmation' => 'تأیید رمز عبور',
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'phone' => 'تلفن',
        'mobile' => 'موبایل',
        'address' => 'آدرس',
        'city' => 'شهر',
        'province' => 'استان',
        'postal_code' => 'کد پستی',
        'country' => 'کشور',
        'title' => 'عنوان',
        'body' => 'متن',
        'content' => 'محتوا',
        'description' => 'توضیحات',
        'message' => 'پیام',
        'subject' => 'موضوع',
        'date' => 'تاریخ',
        'time' => 'زمان',
        'image' => 'تصویر',
        'file' => 'فایل',
        'price' => 'قیمت',
        'quantity' => 'تعداد',
        'amount' => 'مبلغ',
        'status' => 'وضعیت',
        'type' => 'نوع',
        'category' => 'دسته‌بندی',
        'tag' => 'برچسب',
        'role' => 'نقش',
        'permission' => 'دسترسی',
        'amadast_client_code' => 'کد کلاینت آمادست',
        'sender_name' => 'نام فرستنده',
        'sender_mobile' => 'موبایل فرستنده',
        'warehouse_title' => 'نام انبار',
        'warehouse_address' => 'آدرس انبار',
        'province_id' => 'استان',
        'city_id' => 'شهر',
        'store_title' => 'نام فروشگاه',
        'woocommerce_store_url' => 'آدرس فروشگاه',
        'woocommerce_consumer_key' => 'کلید مصرف‌کننده',
        'woocommerce_consumer_secret' => 'رمز مصرف‌کننده',
    ],

];
