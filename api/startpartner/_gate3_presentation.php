<?php
declare(strict_types=1);

// Begriffswechsel: Der frühere sichtbare Status „Pilot-Onboarding“ heißt jetzt „Piloteinrichtung“.

function be_startpartner_gate3_case_action(
    string $key,
    string $label,
    bool $requiresInput = false,
    bool $destructive = false
): array {
    return [
        'key' => $key,
        'label' => $label,
        'requires_input' => $requiresInput,
        'destructive' => $destructive,
        'enabled' => true,
    ];
}

function be_startpartner_gate3_present_case(array $item, array $candidate): array
{
    $gate3 = is_array($candidate['gate3'] ?? null)
        ? $candidate['gate3']
        : ['complete' => false, 'blockers' => []];

    $item['startpartner_candidate'] = $candidate;
    $item['decision_context'] = array_merge((array)($item['decision_context'] ?? []), [
        'candidate_id' => $candidate['id'],
        'candidate_status' => $candidate['status'],
        'candidate_revision' => $candidate['revision'],
        'readiness' => $candidate['readiness'],
        'capacity' => $candidate['capacity'],
        'assigned_to' => $candidate['assigned_to'],
        'next_review_at' => $candidate['next_review_at'],
        'gate3' => $gate3,
    ]);

    if ((string)$candidate['status'] !== 'accepted_pending_terms') {
        return $item;
    }

    if (!empty($gate3['complete'])) {
        $item['display_status'] = 'Piloteinrichtung';
        $item['primary_action'] = be_startpartner_gate3_case_action(
            'details',
            'Pilotstatus prüfen'
        );
        $item['secondary_actions'] = [
            be_startpartner_gate3_case_action('edit_profile', 'Profil bearbeiten', true),
        ];
        $item['next_action'] = 'Piloteinrichtung abschließen; der Pilotstart bleibt bis dahin gesperrt.';
        return $item;
    }

    $item['display_status'] = 'Platz reserviert · Bedingungen offen';
    $item['primary_action'] = be_startpartner_gate3_case_action(
        'confirm_pilot_terms',
        'Bedingungen bestätigen und Pilot anlegen',
        true
    );
    $item['secondary_actions'] = [
        be_startpartner_gate3_case_action('edit_profile', 'Profil bearbeiten', true),
        be_startpartner_gate3_case_action('extend_reservation', 'Reservierung verlängern', true),
        be_startpartner_gate3_case_action(
            'release_reservation',
            'Reservierung freigeben',
            true,
            true
        ),
        be_startpartner_gate3_case_action('details', 'Nachweise und Verlauf'),
    ];
    $item['next_action'] = 'Pilotbedingungen bestätigen und Veranstalterzugang zuordnen.';
    return $item;
}
