<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ضربانِ بلادرنگ چت: مبنای تشخیصِ «پیام جدید آمده؟» و عددِ بجِ منو.
 */
class ChatTickTest extends TestCase
{
    private User $me;

    private User $other;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_195120_create_permission_tables.php',
            'database/migrations/2026_01_04_100000_create_chat_tables.php',
            // رندرِ صفحهٔ پیام‌رسان و سایدبار به این‌ها نیاز دارد.
            'database/migrations/2026_02_03_140854_create_settings_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        $this->me = $this->user('من', '09120000010');
        $this->other = $this->user('دیگری', '09120000011');

        $this->conversation = Conversation::create(['type' => 'private', 'created_by' => $this->me->id]);
        foreach ([$this->me->id, $this->other->id] as $id) {
            DB::table('conversation_participants')->insert([
                'conversation_id' => $this->conversation->id,
                'user_id' => $id,
                'joined_at' => now(),
            ]);
        }
    }

    private function user(string $name, string $mobile): User
    {
        return User::create([
            'first_name' => $name, 'last_name' => 'تست',
            'mobile' => $mobile, 'password' => bcrypt('secret'),
            // گروهِ /admin با verified.mobile محافظت می‌شود.
            'mobile_verified_at' => now(),
        ]);
    }

    private function message(User $from, string $body = 'سلام'): Message
    {
        return Message::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $from->id,
            'body' => $body,
            'type' => 'text',
        ]);
    }

    public function test_tick_reports_new_messages_from_the_other_side(): void
    {
        $m = $this->message($this->other);

        $response = $this->actingAs($this->me)->getJson('/admin/chat/tick');

        $response->assertOk()
            ->assertJsonPath('max_id', $m->id)
            ->assertJsonPath('unread', 1);
    }

    public function test_my_own_messages_do_not_count(): void
    {
        $this->message($this->me);

        $this->actingAs($this->me)->getJson('/admin/chat/tick')
            ->assertOk()
            ->assertJsonPath('max_id', 0)
            ->assertJsonPath('unread', 0);
    }

    public function test_messages_before_last_read_are_not_unread(): void
    {
        $this->message($this->other);

        DB::table('conversation_participants')
            ->where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->me->id)
            ->update(['last_read_at' => now()->addMinute()]);

        $this->actingAs($this->me)->getJson('/admin/chat/tick')
            ->assertOk()
            ->assertJsonPath('unread', 0);
    }

    public function test_deleted_messages_are_excluded(): void
    {
        $m = $this->message($this->other);
        $m->delete();

        $this->actingAs($this->me)->getJson('/admin/chat/tick')
            ->assertOk()
            ->assertJsonPath('unread', 0);
    }

    public function test_conversation_max_id_tracks_the_open_conversation(): void
    {
        $mine = $this->message($this->me);

        // پیامِ خودم در max_id کلی نمی‌آید ولی در گفتگوی باز باید دیده شود،
        // وگرنه بعد از ارسال، صفحه فکر می‌کند چیزی عوض نشده.
        $this->actingAs($this->me)
            ->getJson('/admin/chat/tick?conversation_id='.$this->conversation->id)
            ->assertOk()
            ->assertJsonPath('conversation_max_id', $mine->id);
    }

    public function test_a_user_outside_the_conversation_sees_nothing(): void
    {
        $this->message($this->other);
        $stranger = $this->user('غریبه', '09120000012');

        $this->actingAs($stranger)->getJson('/admin/chat/tick')
            ->assertOk()
            ->assertJsonPath('max_id', 0)
            ->assertJsonPath('unread', 0);
    }

    public function test_realtime_endpoints_are_never_http_cached(): void
    {
        $this->actingAs($this->me)->getJson('/admin/chat/tick')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');
    }

    public function test_the_messenger_page_itself_is_not_cacheable(): void
    {
        $this->actingAs($this->me)->get('/admin/messenger')
            ->assertOk()
            ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');
    }

    public function test_unread_helper_matches_the_tick_endpoint(): void
    {
        $this->message($this->other);
        $this->message($this->other, 'دومی');

        $this->assertSame(2, Message::unreadCountFor($this->me->id));
        $this->assertSame(0, Message::unreadCountFor(null));
    }
}
