<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('stop_processing')->default(false);
            $table->json('triggers')->nullable();
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'priority']);
        });

        if (! Schema::hasTable('inbox_rules')) {
            return;
        }

        $now = now();
        $rows = DB::table('inbox_rules')->orderBy('id')->get();
        foreach ($rows as $row) {
            $mapped = $this->mapInboxRule($row);
            if ($mapped === null) {
                continue;
            }

            DB::table('lead_rules')->insert([
                'company_id' => $row->company_id,
                'name' => $row->name,
                'priority' => $row->priority ?? 100,
                'is_active' => (bool) $row->is_active,
                'stop_processing' => (bool) ($row->stop_processing ?? false),
                'triggers' => json_encode($mapped['triggers']),
                'conditions' => json_encode($mapped['conditions']),
                'actions' => json_encode($mapped['actions']),
                'created_by' => $row->created_by,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array{triggers: list<string>, conditions: list<array<string, mixed>>, actions: list<array<string, mixed>>}|null
     */
    private function mapInboxRule(object $row): ?array
    {
        $triggerMap = [
            'inbound_message' => 'inbound_message',
            'inbound_message_new' => 'inbound_message_new',
            'outbound_message_new' => 'outbound_message_new',
            'outbound_reply' => 'outbound_reply',
            'conversation_assigned' => 'lead_assigned',
            'conversation_tagged' => 'lead_labeled',
        ];

        $rawTriggers = json_decode((string) ($row->triggers ?? '[]'), true);
        if (! is_array($rawTriggers) || $rawTriggers === []) {
            $rawTriggers = ['inbound_message_new'];
        }

        $triggers = [];
        foreach ($rawTriggers as $trigger) {
            $mapped = $triggerMap[(string) $trigger] ?? null;
            if ($mapped && ! in_array($mapped, $triggers, true)) {
                $triggers[] = $mapped;
            }
        }

        $rawConditions = json_decode((string) ($row->conditions ?? '[]'), true);
        if (! is_array($rawConditions)) {
            $rawConditions = [];
        }

        $conditions = [[
            'field' => 'channel',
            'operator' => 'in',
            'value' => ['inbox'],
        ]];

        $fieldMap = [
            'from_email' => 'email',
            'from_name' => 'contact_name',
            'subject' => 'subject',
            'snippet' => 'message',
        ];

        foreach ($rawConditions as $condition) {
            $field = (string) ($condition['field'] ?? '');
            if ($field === 'inbox' || $field === '') {
                continue;
            }
            $mappedField = $fieldMap[$field] ?? null;
            if (! $mappedField) {
                continue;
            }
            $conditions[] = [
                'field' => $mappedField,
                'operator' => in_array($condition['operator'] ?? '', ['contains', 'equals', 'starts_with'], true)
                    ? $condition['operator']
                    : 'contains',
                'value' => $condition['value'] ?? '',
            ];
        }

        $rawActions = json_decode((string) ($row->actions ?? '[]'), true);
        if (! is_array($rawActions)) {
            $rawActions = [];
        }

        $actions = [];
        foreach ($rawActions as $action) {
            $type = (string) ($action['type'] ?? '');
            if ($type === 'assign' || $type === 'notify_assignee') {
                $actions[] = ['type' => $type, 'value' => $action['value'] ?? null];
            }
            if ($type === 'tag') {
                $actions[] = ['type' => 'add_label', 'value' => $action['value'] ?? null];
            }
        }

        if ($triggers === [] || $actions === []) {
            return null;
        }

        return compact('triggers', 'conditions', 'actions');
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_rules');
    }
};
