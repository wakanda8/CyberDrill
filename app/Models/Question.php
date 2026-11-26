<?php

namespace App\Models;

use App\Models\Concerns\DecodesOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use DecodesOptions, HasFactory;
    
    protected $fillable = [
    'module', 'key', 'title', 'type', 'sender_name', 
    'sender_email', 'subject', 'body', 'is_phishing', 
    'malicious_zone', 'feedback', 'options'
];

    // THIS PART IS CRITICAL
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * Fields of a spam email that are safe to send to the browser before the user answers.
     */
    public const PUBLIC_SPAM_FIELDS = ['id', 'sender_name', 'sender_email', 'subject', 'body'];

    /**
     * Grade a spam triage decision ('junk', 'inbox' or 'timeout').
     *
     * @return array{status: string, feedback: string}
     */
    public function gradeSpamDecision(string $decision): array
    {
        if ($decision === 'timeout') {
            return ['status' => 'incorrect', 'feedback' => 'TIME EXPIRED! Threat bypassed your filter.'];
        }

        $label = $decision === 'junk' ? 'Junk' : 'Inbox';
        $chosen = collect($this->decodedOptions())
            ->first(fn (array $option): bool => str_contains((string) ($option['text'] ?? ''), $label));

        return [
            'status' => ($chosen['result'] ?? null) === 'correct' ? 'correct' : 'incorrect',
            'feedback' => (string) ($chosen['feedback'] ?? ''),
        ];
    }
}