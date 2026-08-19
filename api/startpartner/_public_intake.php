<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';

/*
 * Öffentlicher Self-Service-Adapter.
 *
 * BE_STARTPARTNER_PUBLIC_OPERATIONAL_REVIEW_DAYS ist ausschließlich ein operativer
 * Wiedervorlagepunkt für neue Anfragen. Die Konstante definiert keine Lösch- oder
 * Aufbewahrungsfrist und ersetzt keine spätere rechtliche Retention-Entscheidung.
 */
const BE_STARTPARTNER_PUBLIC_OPERATIONAL_REVIEW_DAYS = 30;
const BE_STARTPARTNER_PUBLIC_FORM_VERSION = 'startpartner-public-first-party-v1';
const BE_STARTPARTNER_PUBLIC_PRIVACY_VERSION = 'startpartner-public-consent-v1';

function be_startpartner_public_scope(mixed $value): string
{
    $scope = strtolower(trim((string)$value));
    if ($scope === 'unsure') {
        return 'unknown';
    }
    if (!in_array($scope, ['events', 'activities', 'both', 'unknown'], true)) {
        throw new InvalidArgumentException('Bitte wähle aus, was du testen möchtest.');
    }
    return $scope;
}

function be_startpartner_public_bool(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
}

function be_startpartner_public_is_honeypot(array $input): bool
{
    return trim((string)($input['website_confirm'] ?? '')) !== '';
}

function be_startpartner_public_review_at(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return $now
        ->modify('+' . BE_STARTPARTNER_PUBLIC_OPERATIONAL_REVIEW_DAYS . ' days')
        ->format('Y-m-d H:i:s');
}

function be_startpartner_public_prepare_input(array $input, string $idempotencyKey): array
{
    $organization = be_startpartner_clean_text(
        $input['organization'] ?? $input['organization_name'] ?? null,
        190,
        'organization',
        true
    );
    if (be_startpartner_string_length((string)$organization) < 2) {
        throw new InvalidArgumentException('Bitte gib deine Organisation an.');
    }

    $contactName = be_startpartner_clean_text(
        $input['contact_name'] ?? null,
        190,
        'contact_name',
        true
    );
    if (be_startpartner_string_length((string)$contactName) < 2) {
        throw new InvalidArgumentException('Bitte gib eine Ansprechperson an.');
    }

    $description = be_startpartner_clean_text(
        $input['description'] ?? $input['description_text'] ?? null,
        5000,
        'description',
        true
    );
    if (be_startpartner_string_length((string)$description) < 8) {
        throw new InvalidArgumentException('Bitte beschreibe dein Angebot kurz.');
    }

    if (!be_startpartner_public_bool($input['privacy_confirmed'] ?? false)) {
        throw new InvalidArgumentException('Bitte bestätige die Verarbeitung deiner Angaben.');
    }

    $cleanIdempotencyKey = trim($idempotencyKey);
    if ($cleanIdempotencyKey !== '' && (strlen($cleanIdempotencyKey) < 16 || strlen($cleanIdempotencyKey) > 191)) {
        throw new InvalidArgumentException('Ungültige Anfrage-ID.');
    }

    return [
        'source' => 'self_service',
        'source_reference' => $cleanIdempotencyKey !== '' ? $cleanIdempotencyKey : null,
        'organization_name' => $organization,
        'contact_name' => $contactName,
        'email' => trim((string)($input['email'] ?? '')),
        'website_url' => trim((string)($input['website'] ?? $input['website_url'] ?? '')),
        'description_text' => $description,
        'desired_content_scope' => be_startpartner_public_scope($input['desired_content_scope'] ?? null),
        'privacy_confirmed' => true,
        'privacy_policy_version' => BE_STARTPARTNER_PUBLIC_PRIVACY_VERSION,
        'form_version' => BE_STARTPARTNER_PUBLIC_FORM_VERSION,
        'retention_review_at' => be_startpartner_public_review_at(),
        'idempotency_key' => $cleanIdempotencyKey,
    ];
}

function be_startpartner_public_scope_label(string $scope): string
{
    return match ($scope) {
        'events' => 'Veranstaltungen',
        'activities' => 'Aktivitäten',
        'both' => 'Veranstaltungen und Aktivitäten',
        default => 'Noch nicht festgelegt',
    };
}

function be_startpartner_public_primary_contact(array $candidate): ?array
{
    $contacts = is_array($candidate['contacts'] ?? null) ? $candidate['contacts'] : [];
    foreach ($contacts as $contact) {
        if (is_array($contact) && !empty($contact['is_primary'])) {
            return $contact;
        }
    }
    return isset($contacts[0]) && is_array($contacts[0]) ? $contacts[0] : null;
}

function be_startpartner_public_mail_payload(array $candidate): array
{
    $contact = be_startpartner_public_primary_contact($candidate) ?? [];
    $contactName = trim((string)($contact['contact_name'] ?? ''));
    $organization = trim((string)($candidate['organization_name'] ?? ''));
    $scope = be_startpartner_public_scope_label((string)($candidate['desired_content_scope'] ?? 'unknown'));

    return [
        'subject' => 'Deine Startpartner-Anfrage ist angekommen',
        'to_address' => trim((string)($contact['email'] ?? '')),
        'to_name' => $contactName !== '' ? $contactName : null,
        'mail_data' => [
            'title' => 'Startpartner-Anfrage erhalten',
            'preheader' => 'Wir haben deine Anfrage erhalten und prüfen jetzt, ob Startpartner zu deinem Angebot passt.',
            'greeting' => be_mail_greeting($contactName),
            'intro' => 'Vielen Dank für deine Anfrage. Wir prüfen jetzt deine Angaben und – wenn vorhanden – deine Website oder Quelle.',
            'details' => [
                ['label' => 'Organisation', 'value' => $organization],
                ['label' => 'Bereich', 'value' => $scope],
            ],
            'body' => 'Falls für die Einordnung noch Informationen fehlen, melden wir uns per E-Mail. Danach bekommst du eine klare Rückmeldung, ob und wie es weitergeht.',
            'notice_title' => 'Wichtig',
            'notice_text' => 'Die Anfrage ist noch keine Aufnahmezusage. Es wird keine Zahlungsart hinterlegt und kein kostenpflichtiger Tarif gestartet.',
        ],
    ];
}

function be_startpartner_public_send_received_mail(array $candidate): bool
{
    $payload = be_startpartner_public_mail_payload($candidate);
    $toAddress = (string)$payload['to_address'];
    if ($toAddress === '' || !filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mailData = (array)$payload['mail_data'];

    try {
        be_send_mail(
            $toAddress,
            (string)$payload['subject'],
            be_render_system_mail_text($mailData),
            $payload['to_name'] !== null ? (string)$payload['to_name'] : null,
            be_render_system_mail_html($mailData)
        );
        return true;
    } catch (Throwable $error) {
        error_log('Startpartner intake confirmation mail failed: ' . $error->getMessage());
        return false;
    }
}
