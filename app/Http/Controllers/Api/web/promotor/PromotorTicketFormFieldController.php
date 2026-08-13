<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\Ticket;
use App\Models\TicketFormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromotorTicketFormFieldController extends Controller
{
    use AuthorizesEventAccess;

    public function index(string $ticketId)
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($ticket->event_id)) {
            return $denied;
        }

        return response()->json([
            'ticket' => $ticket,
            'fields' => $ticket->formFields,
        ]);
    }

    public function store(Request $request, string $ticketId)
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($ticket->event_id)) {
            return $denied;
        }

        $data = $this->validatedField($request, $ticket);

        $maxOrder = (int) $ticket->formFields()->max('sort_order');
        $data['sort_order'] = $data['sort_order'] ?? ($maxOrder + 1);
        $data['ticket_id'] = $ticket->id;
        $data['field_key'] = $this->uniqueFieldKey($ticket, $data['label'], $data['field_key'] ?? null);

        $field = TicketFormField::create($data);

        return response()->json(['field' => $field], 201);
    }

    public function update(Request $request, string $ticketId, string $fieldId)
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($ticket->event_id)) {
            return $denied;
        }

        $field = TicketFormField::where('ticket_id', $ticket->id)->find($fieldId);
        if (!$field) {
            return response()->json(['message' => 'Campo não encontrado.'], 404);
        }

        $data = $this->validatedField($request, $ticket, $field);
        if (array_key_exists('label', $data) && empty($data['field_key'])) {
            // keep existing key unless explicitly provided
            unset($data['field_key']);
        }
        if (!empty($data['field_key'])) {
            $data['field_key'] = $this->uniqueFieldKey($ticket, $data['label'] ?? $field->label, $data['field_key'], $field->id);
        }

        $field->update($data);

        return response()->json(['field' => $field->fresh()]);
    }

    public function destroy(string $ticketId, string $fieldId)
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($ticket->event_id)) {
            return $denied;
        }

        $field = TicketFormField::where('ticket_id', $ticket->id)->find($fieldId);
        if (!$field) {
            return response()->json(['message' => 'Campo não encontrado.'], 404);
        }

        $field->delete();

        return response()->noContent();
    }

    private function validatedField(Request $request, Ticket $ticket, ?TicketFormField $existing = null): array
    {
        $data = $request->validate([
            'label' => [$existing ? 'sometimes' : 'required', 'string', 'max:255'],
            'field_key' => ['nullable', 'string', 'max:100'],
            'type' => [$existing ? 'sometimes' : 'required', Rule::in(TicketFormField::TYPES)],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'terms_text' => ['nullable', 'string'],
            'required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = $data['type'] ?? $existing?->type;
        if ($type === 'select') {
            $options = $data['options'] ?? $existing?->options ?? [];
            if (!is_array($options) || count(array_filter($options, fn ($o) => filled($o))) < 1) {
                abort(422, 'Campos do tipo select precisam de pelo menos uma opção.');
            }
            $data['options'] = array_values(array_filter($options, fn ($o) => filled($o)));
        } else {
            $data['options'] = $data['options'] ?? null;
        }

        if ($type === 'terms') {
            $terms = $data['terms_text'] ?? $existing?->terms_text;
            if (!filled($terms)) {
                abort(422, 'Campos do tipo terms precisam do texto dos termos.');
            }
            $data['required'] = true;
        }

        if (array_key_exists('required', $data)) {
            $data['required'] = (bool) $data['required'];
        }

        return $data;
    }

    private function uniqueFieldKey(Ticket $ticket, string $label, ?string $preferred = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($preferred ?: $label, '_');
        if ($base === '') {
            $base = 'campo';
        }
        $base = Str::limit($base, 80, '');

        $key = $base;
        $i = 2;
        while (
            TicketFormField::where('ticket_id', $ticket->id)
                ->where('field_key', $key)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $key = $base . '_' . $i;
            $i++;
        }

        return $key;
    }
}
