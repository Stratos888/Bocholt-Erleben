<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate2_domain.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$expectException = static function(callable $callback, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = $message;
    } catch (InvalidArgumentException|DomainException $expected) {
    }
};

$assert(count(BE_STARTPARTNER_GATE2_STATUSES) === 13, 'Gate-2-Statussatz muss genau 13 Zustände besitzen.');
$assert(!in_array('qualified', BE_STARTPARTNER_GATE2_STATUSES, true), 'Legacy qualified darf kein kanonischer Gate-2-Zustand sein.');
$assert(be_startpartner_gate2_validate_status('qualified') === 'decision_ready', 'Legacy qualified muss am API-Rand deterministisch nach decision_ready abgebildet werden.');
$assert(count(BE_STARTPARTNER_QUALIFICATION_DIMENSIONS) === 14, 'Gate 2 benötigt genau 14 Qualifikationsdimensionen.');
$assert(count(BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS) === 6, 'Gate 2 benötigt genau sechs harte Mindestdimensionen.');

$unknown = array_map(
    static fn(string $dimension): array => ['dimension' => $dimension, 'assessment' => 'unknown'],
    BE_STARTPARTNER_QUALIFICATION_DIMENSIONS
);
$unknownReadiness = be_startpartner_gate2_readiness($unknown);
$assert(!$unknownReadiness['ready'], 'Unbewertete Dimensionen dürfen nicht entscheidungsreif sein.');
$assert(count($unknownReadiness['blockers']) === 14, 'Jede unbewertete Dimension muss als Blocker sichtbar sein.');

$ready = array_map(
    static fn(string $dimension): array => [
        'dimension' => $dimension,
        'assessment' => in_array($dimension, BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS, true) ? 'adequate' : 'weak',
    ],
    BE_STARTPARTNER_QUALIFICATION_DIMENSIONS
);
$readyState = be_startpartner_gate2_readiness($ready);
$assert($readyState['ready'], 'Bewusste Bewertungen mit erfüllten Mindestdimensionen müssen entscheidungsreif sein.');

$ready[0]['assessment'] = 'weak';
$blockedState = be_startpartner_gate2_readiness($ready);
$assert(!$blockedState['ready'], 'Eine schwache harte Mindestdimension muss blockieren.');
$assert(($blockedState['blockers'][0]['code'] ?? '') === 'minimum_not_met', 'Harter Mindestblocker benötigt einen stabilen Code.');

$hashA = be_startpartner_gate2_payload_hash('candidate-1', 'profile.update', [
    'operation_id' => 'gate2:199:test',
    'expected_revision' => 1,
    'operator_name' => 'Contract',
    'profile' => ['b' => 2, 'a' => 1],
]);
$hashB = be_startpartner_gate2_payload_hash('candidate-1', 'profile.update', [
    'operator_name' => 'Contract',
    'profile' => ['a' => 1, 'b' => 2],
    'expected_revision' => 1,
    'operation_id' => 'gate2:199:other-id',
]);
$hashC = be_startpartner_gate2_payload_hash('candidate-1', 'profile.update', [
    'expected_revision' => 1,
    'operator_name' => 'Contract',
    'profile' => ['a' => 1, 'b' => 3],
]);
$assert($hashA === $hashB, 'Payload-Hash muss unabhängig von Objektreihenfolge und operation_id stabil sein.');
$assert($hashA !== $hashC, 'Abweichender Payload muss einen anderen Hash besitzen.');

$assert(be_startpartner_gate2_case_state('decision_ready') === 'decision_required', 'Entscheidungsreife muss als Entscheidung projiziert werden.');
$assert(be_startpartner_gate2_case_state('accepted_pending_terms') === 'waiting', 'Reservierter Platz mit offenen Bedingungen muss wartend bleiben.');
$assert(be_startpartner_gate2_case_state('waitlisted') === 'parked', 'Warteliste muss geparkt werden.');
$assert(be_startpartner_gate2_case_state('routed_to_regular_product') === 'done', 'Regulärer Alternativweg muss den Gate-2-Fall abschließen.');

$expectException(
    static fn() => be_startpartner_gate2_operation_id('bad id'),
    'Operation IDs mit Leerzeichen müssen abgelehnt werden.'
);
$expectException(
    static fn() => be_startpartner_gate2_expected_revision(0),
    'Revision null muss abgelehnt werden.'
);
$expectException(
    static fn() => be_startpartner_gate2_normalize_qualification([
        'dimension' => 'local_relevance',
        'assessment' => 'adequate',
    ]),
    'Bewertete Dimensionen ohne Reason und Evidence müssen abgelehnt werden.'
);

$normalizedQualification = be_startpartner_gate2_normalize_qualification([
    'dimension' => 'local_relevance',
    'assessment' => 'adequate',
    'reason' => 'Lokaler Bezug ist belegt.',
    'evidence_text' => 'Sitz und Angebot liegen in Bocholt.',
]);
$assert($normalizedQualification['assessment'] === 'adequate', 'Qualifikationsnormalisierung muss gültige Bewertungen erhalten.');

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-2 Domain Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-2 Domain Contract: OK ===\n";
