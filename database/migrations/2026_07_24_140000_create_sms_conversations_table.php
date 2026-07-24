<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('peer_phone', 32);
            $table->string('our_number', 32)->nullable();
            $table->string('name')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('last_message_preview', 500)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'peer_phone']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->foreignId('sms_conversation_id')
                ->nullable()
                ->after('company_id')
                ->constrained('sms_conversations')
                ->nullOnDelete();
        });

        $this->backfillConversations();
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sms_conversation_id');
        });

        Schema::dropIfExists('sms_conversations');
    }

    private function backfillConversations(): void
    {
        $messages = DB::table('sms_messages')->orderBy('id')->get();
        if ($messages->isEmpty()) {
            return;
        }

        $conversationIds = [];

        foreach ($messages as $message) {
            $peer = $message->direction === 'outbound'
                ? $message->to_number
                : $message->from_number;
            $our = $message->direction === 'outbound'
                ? $message->from_number
                : $message->to_number;

            $key = $message->company_id.'|'.$peer;

            if (! isset($conversationIds[$key])) {
                $id = DB::table('sms_conversations')->insertGetId([
                    'company_id' => $message->company_id,
                    'peer_phone' => $peer,
                    'our_number' => $our,
                    'name' => $peer,
                    'unread_count' => 0,
                    'last_message_preview' => Str::limit((string) $message->body, 480),
                    'last_message_at' => $message->sent_at ?: $message->created_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $conversationIds[$key] = $id;
            } else {
                $update = [
                    'last_message_preview' => Str::limit((string) $message->body, 480),
                    'last_message_at' => $message->sent_at ?: $message->created_at,
                    'updated_at' => now(),
                ];
                if ($our) {
                    $update['our_number'] = $our;
                }
                DB::table('sms_conversations')->where('id', $conversationIds[$key])->update($update);
            }

            DB::table('sms_messages')
                ->where('id', $message->id)
                ->update(['sms_conversation_id' => $conversationIds[$key]]);
        }
    }
};
