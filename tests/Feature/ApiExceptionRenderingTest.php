<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * هندلرِ سراسریِ استثنا برای مسیرهای /v1/*.
 *
 * چرا این تست وجود دارد: آن هندلر هر استثنایی را که ValidationException یا
 * HttpException نبود، به «500 Server error» تبدیل می‌کرد —
 * AuthenticationException هم همین سرنوشت را داشت. یعنی درخواستِ بدونِ
 * توکن (یا با توکنِ منقضی) به‌جای 401، پاسخِ 500 می‌گرفت؛ اپ کاربر را به
 * صفحهٔ ورود نمی‌بُرد و تیمِ عملیات دنبالِ خرابی‌ای می‌گشت که وجود نداشت.
 * چون این استثنا در فهرستِ dontReport است، هیچ لاگی هم نمی‌ماند — یک 500
 * کاملاً بی‌ردپا.
 */
class ApiExceptionRenderingTest extends TestCase
{
    public function test_a_request_without_a_token_gets_401_not_500(): void
    {
        $response = $this->getJson('/v1/technician/me');

        $response->assertStatus(401);
        // و پیامش «Server error» نیست که اپ را به تعمیرکارِ اشتباه بفرستد.
        $this->assertNotSame('Server error', $response->json('message'));
    }

    public function test_an_unknown_v1_path_still_gets_404_json(): void
    {
        $this->getJson('/v1/definitely-not-a-real-route')
            ->assertStatus(404);
    }

    public function test_validation_errors_still_come_back_as_422(): void
    {
        // send-otp بدونِ موبایل — نباید 500 شود.
        $this->postJson('/v1/technician/auth/send-otp', [])
            ->assertStatus(422);
    }
}
