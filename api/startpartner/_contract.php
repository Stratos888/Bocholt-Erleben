<?php
declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once dirname(__DIR__) . '/control-center/_domain.php';

const BE_STARTPARTNER_SOURCES = ['self_service', 'targeted_outreach'];
const BE_STARTPARTNER_CONTENT_SCOPES = ['events', 'activities', 'both', 'unknown'];
const BE_STARTPARTNER_STATUSES = [
    'new',
    'needs_information',
    'qualified',
    'waitlisted',
    'routed_to_regular_product',
    'rejected',
    'withdrawn',
    'expired',
];
const BE_STARTPARTNER_TERMINAL_STATUSES = [
    'routed_to_regular_product',
    'rejected',
    'withdrawn',
    'expired',
];
const BE_STARTPARTNER_ALLOWED_ENVIRONMENTS = ['staging', 'development', 'dev', 'local', 'test'];
const BE_STARTPARTNER_MAX_CONTACTS = 5;
const BE_STARTPARTNER_RETENTION_REVIEW_DAYS = 180;

function be_startpartner_require_gate1_environment(): void
{
    if (!in_array(be_app_env_value(), BE_STARTPARTNER_ALLOWED_ENVIRONMENTS, true)) {
        be_json_response(404, [
            'status' => 'error',
            'code' => 'STARTPARTNER_GATE1_NOT_ACTIVE',
            'message' => 'Startpartner Gate 1 is not active in this environment.',
        ]);
    }
}

function be_startpartner_string_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function be_startpartner_clean_text(mixed $value, int $maxLength, string $field, bool $required = false): ?string
{
    $text = trim((string)$value);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    if ($text === '') {
        if ($required) {
            throw new InvalidArgumentException("{$field} is required.");
        }
        return null;
    }

    if (be_startpartner_string_length($text) > $maxLength) {
        throw new InvalidArgumentException("{$field} is too long.");
    }

    return $text;
}

function be_startpartner_normalize_organization(string $organizationName): string
{
    $value = function_exists('mb_strtolower')
        ? mb_strtolower($organizationName, 'UTF-8')
        : strtolower($organizationName);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

    if ($value === '') {
        throw new InvalidArgumentException('organization_name cannot be normalized.');
    }

    return $value;
}

function be_startpartner_normalize_email(mixed $value): string
{
    $email = strtolower(trim((string)$value));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid contact email is required.');
    }
    if (strlen($email) > 190) {
        throw new InvalidArgumentException('Contact email is too long.');
    }
    return $email;
}

function be_startpartner_normalize_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('website_url is invalid.');
    }
    if (strlen($url) > 2048) {
        throw new InvalidArgumentException('website_url is too long.');
    }
    return $url;
}

function be_startpartner_validate_enum_value(string $value, array $allowed, string $field): string
{
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException("Invalid {$field}.");
    }
    return $value;
}

function be_startpartner_normalize_contacts(array $input): array
{
    $rawContacts = $input['contacts'] ?? null;
    if (!is_array($rawContacts) || $rawContacts === []) {
        $rawContacts = [[
            'contact_name' => $input['contact_name'] ?? null,
            'contact_role' => $input['contact_role'] ?? null,
            'email' => $input['email'] ?? null,
            'phone' => $input['phone'] ?? null,
            'is_primary' => true,
        ]];
    }

    if (count($rawContacts) > BE_STARTPARTNER_MAX_CONTACTS) {
        throw new InvalidArgumentException('Too many contacts.');
    }

    $contacts = [];
    $emails = [];
    $primaryCount = 0;

    foreach (array_values($rawContacts) as $index => $rawContact) {
        if (!is_array($rawContact)) {
            throw new InvalidArgumentException('Each contact must be an object.');
        }

        $emailNormalized = be_startpartner_normalize_email($rawContact['email'] ?? null);
        if (isset($emails[$emailNormalized])) {
            throw new InvalidArgumentException('Duplicate contact email.');
        }
        $emails[$emailNormalized] = true;

        $isPrimary = !empty($rawContact['is_primary']);
        if ($isPrimary) {
            $primaryCount++;
        }

        $contacts[] = [
            'contact_name' => be_startpartner_clean_text($rawContact['contact_name'] ?? null, 190, 'contact_name'),
            'contact_role' => be_startpartner_clean_text($rawContact['contact_role'] ?? null, 190, 'contact_role'),
            'email' => trim((string)$rawContact['email']),
            'email_normalized' => $emailNormalized,
            'phone' => be_startpartner_clean_text($rawContact['phone'] ?? null, 64, 'phone'),
            'is_primary' => $isPrimary,
            'position' => $index,
        ];
    }

    if ($primaryCount === 0 && isset($contacts[0])) {
        $contacts[0]['is_primary'] = true;
        $primaryCount = 1;
    }
    if ($primaryCount !== 1) {
        throw new InvalidArgumentException('Exactly one primary contact is required.');
    }

    usort($contacts, static function(array $left, array $right): int {
        if ($left['is_primary'] === $right['is_primary']) {
            return $left['position'] <=> $right['position'];
        }
        return $left['is_primary'] ? -1 : 1;
    });

    foreach ($contacts as &$contact) {
        unset($contact['position']);
    }
    unset($contact);

    return $contacts;
}

function be_startpartner_normalize_intake(array $input): array
{
    $source = be_startpartner_validate_enum_value(
        trim((string)($input['source'] ?? 'self_service')),
        BE_STARTPARTNER_SOURCES,
        'source'
    );
    $organizationName = be_startpartner_clean_text(
        $input['organization_name'] ?? $input['organization'] ?? null,
        190,
        'organization_name',
        true
    );
    if (be_startpartner_string_length((string)$organizationName) < 2) {
        throw new InvalidArgumentException('organization_name is too short.');
    }

    $contacts = be_startpartner_normalize_contacts($input);
    $primaryContact = $contacts[0];
    $desiredContentScope = be_startpartner_validate_enum_value(
        trim((string)($input['desired_content_scope'] ?? 'unknown')),
        BE_STARTPARTNER_CONTENT_SCOPES,
        'desired_content_scope'
    );
    $formVersion = be_startpartner_clean_text($input['form_version'] ?? 'gate1-v1', 64, 'form_version', true);
    $privacyPolicyVersion = be_startpartner_clean_text(
        $input['privacy_policy_version'] ?? null,
        64,
        'privacy_policy_version'
    );

    if ($source === 'self_service') {
        if (empty($input['privacy_confirmed'])) {
            throw new InvalidArgumentException('privacy_confirmed is required for self_service.');
        }
        if ($privacyPolicyVersion === null) {
            throw new InvalidArgumentException('privacy_policy_version is required for self_service.');
        }
    }

    $organizationNormalized = be_startpartner_normalize_organization((string)$organizationName);
    $identityKey = hash('sha256', $organizationNormalized . '|' . $primaryContact['email_normalized']);

    $canonicalPayload = [
        'source' => $source,
        'organization_name_normalized' => $organizationNormalized,
        'primary_email' => $primaryContact['email_normalized'],
        'website_url' => be_startpartner_normalize_url($input['website_url'] ?? $input['website'] ?? null),
        'description_text' => be_startpartner_clean_text(
            $input['description_text'] ?? $input['description'] ?? null,
            5000,
            'description_text'
        ),
        'desired_content_scope' => $desiredContentScope,
        'form_version' => $formVersion,
    ];

    $idempotencyValue = trim((string)($input['idempotency_key'] ?? ''));
    if ($idempotencyValue !== '') {
        if (strlen($idempotencyValue) < 16 || strlen($idempotencyValue) > 191) {
            throw new InvalidArgumentException('idempotency_key length is invalid.');
        }
    } else {
        $idempotencyValue = json_encode(
            $canonicalPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    return [
        'source' => $source,
        'source_reference' => be_startpartner_clean_text($input['source_reference'] ?? null, 191, 'source_reference'),
        'organization_name' => $organizationName,
        'organization_name_normalized' => $organizationNormalized,
        'website_url' => $canonicalPayload['website_url'],
        'description_text' => $canonicalPayload['description_text'],
        'desired_content_scope' => $desiredContentScope,
        'identity_key' => $identityKey,
        'idempotency_key_hash' => hash('sha256', $source . '|' . $idempotencyValue),
        'privacy_policy_version' => $privacyPolicyVersion,
        'form_version' => $formVersion,
        'retention_review_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . BE_STARTPARTNER_RETENTION_REVIEW_DAYS . ' days')
            ->format('Y-m-d H:i:s'),
        'contacts' => $contacts,
    ];
}

function be_startpartner_transition_allowed(string $fromStatus, string $toStatus): bool
{
    if (!in_array($fromStatus, BE_STARTPARTNER_STATUSES, true) || !in_array($toStatus, BE_STARTPARTNER_STATUSES, true)) {
        return false;
    }
    if ($fromStatus === $toStatus || in_array($fromStatus, BE_STARTPARTNER_TERMINAL_STATUSES, true)) {
        return false;
    }

    $allowed = [
        'new' => ['needs_information', 'qualified', 'waitlisted', 'routed_to_regular_product', 'rejected', 'withdrawn', 'expired'],
        'needs_information' => ['new', 'qualified', 'waitlisted', 'routed_to_regular_product', 'rejected', 'withdrawn', 'expired'],
        'qualified' => ['needs_information', 'waitlisted', 'routed_to_regular_product', 'rejected', 'withdrawn', 'expired'],
        'waitlisted' => ['qualified', 'routed_to_regular_product', 'rejected', 'withdrawn', 'expired'],
    ];

    return in_array($toStatus, $allowed[$fromStatus] ?? [], true);
}

function be_startpartner_case_state(string $candidateStatus): string
{
    return match ($candidateStatus) {
        'new' => 'new',
        'needs_information' => 'waiting',
        'qualified' => 'decision_required',
        'waitlisted' => 'parked',
        'routed_to_regular_product' => 'done',
        'rejected', 'withdrawn' => 'rejected',
        'expired' => 'parked',
        default => throw new DomainException('Unsupported candidate status.'),
    };
}

function be_startpartner_next_action(string $candidateStatus): string
{
    return match ($candidateStatus) {
        'new' => 'Kandidaten prüfen und qualifizieren.',
        'needs_information' => 'Fehlende Angaben intern klären; keine automatische Nachricht versenden.',
        'qualified' => 'Aufnahme, Warteliste oder regulären Produktweg entscheiden.',
        'waitlisted' => 'Kapazität und erneuten Prüfzeitpunkt beobachten.',
        'routed_to_regular_product' => 'Regulären Produktweg außerhalb dieses Gate-1-Prozesses fortführen.',
        'rejected' => 'Vorgang ist abgelehnt; Aufbewahrungsfrist beachten.',
        'withdrawn' => 'Rückzug dokumentiert; Aufbewahrungsfrist beachten.',
        'expired' => 'Aufbewahrungs- oder Reaktivierungsentscheidung treffen.',
        default => throw new DomainException('Unsupported candidate status.'),
    };
}

