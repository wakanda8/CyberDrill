<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PhishingScenario extends Model
{
    use HasFactory;

    /**
     * Fields that are safe to send to the browser before the user answers.
     */
    public const PUBLIC_FIELDS = ['id', 'title', 'type', 'sender_name', 'sender_email', 'subject', 'body'];

    /**
     * Default per-zone explanations, used when a scenario has no custom feedback_zoneN text.
     */
    private const DEFAULT_EXPLANATIONS = [
        'malicious' => [
            1 => 'This section uses urgent, alarming, or irregular language typical of social engineering.',
            2 => 'The sender details are forged, misspelled, or originate from an untrusted external domain.',
            3 => 'The content contains suspicious links, unexpected attachments, or requests for sensitive data.',
        ],
        'safe' => [
            1 => 'The context and language used here match normal, expected communication patterns.',
            2 => "The sender's address and domain match the verified organization's official records.",
            3 => 'The message body is standard and links/destinations safely match their display text.',
        ],
    ];

    protected $fillable = [
        'key',
        'title',
        'type',
        'sender_name',
        'sender_email',
        'subject',
        'body',
        'is_phishing',
        'malicious_zone',
        'feedback',
    ];

    protected $casts = [
        'is_phishing' => 'boolean', // Ensures JS receives true/false, not 1/0
    ];

    /**
     * Grade a user's answer. Reporting is correct for phishing messages; any other
     * action (reply, forward, open link, leave) means the user trusted the message.
     *
     * @param  array<string, string|null>  $flags  zone key ('zone1'...) => 'red' | 'green'
     * @return array{status: string, zones: list<array{name: string, marked: string|null, should_be: string, correct: bool, explanation: string}>, feedback: string}
     */
    public function grade(string $action, array $flags): array
    {
        $maliciousZones = $this->maliciousZones();
        $zones = [];

        for ($i = 1; $i <= $this->zoneCount(); $i++) {
            $zone = 'zone'.$i;
            $isMalicious = in_array($zone, $maliciousZones, true);
            $shouldBe = $isMalicious ? 'red' : 'green';
            $marked = $flags[$zone] ?? null;

            $zones[] = [
                'name' => $this->zoneName($i),
                'marked' => $marked,
                'should_be' => $shouldBe,
                'correct' => $marked === $shouldBe,
                'explanation' => $this->{'feedback_'.$zone} ?: self::DEFAULT_EXPLANATIONS[$isMalicious ? 'malicious' : 'safe'][$i],
            ];
        }

        return [
            'status' => ($action === 'report') === $this->is_phishing ? 'correct' : 'incorrect',
            'zones' => $zones,
            'feedback' => $this->feedback ?: 'Review the detailed analysis tags above for more insight on this communication.',
        ];
    }

    /**
     * Emails have three zones (subject, sender, body); SMS messages have two (header, body).
     */
    private function zoneCount(): int
    {
        return $this->type === 'email' ? 3 : 2;
    }

    /**
     * Zones that hold the threat, parsed from malicious_zone (e.g. "zone1, zone3" or "sender, link").
     *
     * @return list<string>
     */
    private function maliciousZones(): array
    {
        $raw = Str::lower((string) $this->malicious_zone);
        $zones = [];

        if (Str::contains($raw, ['subject', 'header', 'zone1'])) {
            $zones[] = 'zone1';
        }
        if (Str::contains($raw, ['sender', 'email', 'zone2'])) {
            $zones[] = 'zone2';
        }
        if (Str::contains($raw, ['body', 'link', 'content', 'zone3'])) {
            $zones[] = 'zone3';
        }

        if ($this->is_phishing && $zones === []) {
            $zones[] = $this->type === 'email' ? 'zone3' : 'zone2';
        }

        return $zones;
    }

    private function zoneName(int $zoneNumber): string
    {
        return match ($zoneNumber) {
            1 => $this->type === 'email' ? 'Subject / Header' : 'Message Header',
            2 => $this->type === 'email' ? 'Sender Identity' : 'Message Body',
            default => 'Message Body / Links',
        };
    }
}