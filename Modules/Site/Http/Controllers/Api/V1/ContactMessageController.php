<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Site\Http\Requests\Api\V1\StoreContactMessageRequest;
use Modules\Site\Models\ContactMessage;

class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $data = $request->validatedDto();

        $message = ContactMessage::create([
            'full_name'  => $data['fullName'],
            'mobile'     => $data['mobile'],
            'email'      => $data['email'] ?? null,
            'topic'      => $data['topic'],
            'message'    => $data['message'],
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'id'         => $message->id,
            'created_at' => $message->created_at->utc()->toIso8601ZuluString(),
        ], 201);
    }
}
