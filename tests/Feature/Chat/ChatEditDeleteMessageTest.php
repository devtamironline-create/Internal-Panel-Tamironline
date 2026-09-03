<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * ویرایشِ پیام توسط فرستنده + حذفِ پیام توسط مدیرِ کل (موردِ امنیتی).
 */
class ChatEditDeleteMessageTest extends TestCase
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
            'database/migrations/2026_02_03_140854_create_settings_table.php',
            'database/migrations/2026_02_02_174221_create_message_reactions_table.php',
            'database/migrations/2026_09_03_120000_add_edited_at_to_messages.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        $this->me = $this->user('من', '09120000010');
        $this->other = $this->user('دیگری', '09120000011');

        $this->conversation = Conversation::create(['type' => 'group', 'created_by' => $this->me->id]);
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

    public function test_sender_can_edit_own_message(): void
    {
        $msg = $this->message($this->me, 'متنِ اول');

        $this->actingAs($this->me)
            ->putJson("/admin/chat/messages/{$msg->id}", ['body' => 'متنِ ویرایش‌شده'])
            ->assertOk()
            ->assertJson(['success' => true, 'content' => 'متنِ ویرایش‌شده', 'edited' => true]);

        $msg->refresh();
        $this->assertSame('متنِ ویرایش‌شده', $msg->body);
        $this->assertNotNull($msg->edited_at);
    }

    public function test_user_cannot_edit_someone_elses_message(): void
    {
        $msg = $this->message($this->other, 'مالِ دیگری');

        $this->actingAs($this->me)
            ->putJson("/admin/chat/messages/{$msg->id}", ['body' => 'دستکاری'])
            ->assertForbidden();

        $this->assertSame('مالِ دیگری', $msg->refresh()->body);
    }

    public function test_non_admin_cannot_delete_message(): void
    {
        $msg = $this->message($this->other);

        $this->actingAs($this->me)
            ->deleteJson("/admin/chat/messages/{$msg->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $msg->id, 'deleted_at' => null]);
    }

    public function test_super_admin_can_delete_any_message(): void
    {
        Permission::findOrCreate('manage-permissions', 'web');
        $this->me->givePermissionTo('manage-permissions');

        $msg = $this->message($this->other, 'محتوای نامناسب');

        $this->actingAs($this->me)
            ->deleteJson("/admin/chat/messages/{$msg->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('messages', ['id' => $msg->id]);
    }
}
