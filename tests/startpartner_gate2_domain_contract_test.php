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

// Der Operator sieht nur sechs Kriterien. Die kanonischen 14 Dimensionen bleiben bewusst Domain-/Speicherdetail.
$qualificationEndpoint = (string)file_get_contents(dirname(__DIR__) . '/api/startpartner/qualification.php');
$startpartnerReview = (string)file_get_contents(dirname(__DIR__) . '/js/control-center/startpartner-review.js');
$compactKeys = [
    'local_editorial_fit',
    'content_sources',
    'user_value_reach',
    'cooperation_maintenance',
    'effort_regular_path',
    'legal_information',
];
foreach ($compactKeys as $key) {
    $assert(str_contains($qualificationEndpoint, "'{$key}' => ["), "Serverseitiges Mapping fehlt für {$key}.");
    $assert(str_contains($startpartnerReview, "key:'{$key}'"), "Control-Center-Check fehlt für {$key}.");
}
$assert(substr_count($qualificationEndpoint, "'dimensions' => [") === 6, 'Der kompakte Serververtrag muss exakt sechs Gruppen besitzen.');
$assert(str_contains($qualificationEndpoint, "'fit' => ['label' => 'Passt', 'assessment' => 'adequate']"), 'Passt muss intern auf adequate abgebildet werden.');
$assert(str_contains($qualificationEndpoint, "'unclear' => ['label' => 'Unklar', 'assessment' => 'unknown']"), 'Unklar muss intern auf unknown abgebildet werden.');
$assert(str_contains($qualificationEndpoint, "'not_fit' => ['label' => 'Passt nicht', 'assessment' => 'weak']"), 'Passt nicht muss intern auf weak abgebildet werden.');
$assert(str_contains($qualificationEndpoint, 'Compact Startpartner mapping must cover all qualification dimensions exactly once.'), 'Die vollständige 14-Dimensionen-Abdeckung muss fail-closed validiert werden.');
$assert(str_contains($qualificationEndpoint, "array_key_exists('qualifications', \$input)"), 'Kompakter und Legacy-Payload dürfen nicht vermischt werden.');
$assert(str_contains($qualificationEndpoint, 'be_startpartner_gate2_qualification_update'), 'Der bestehende Gate-2-Domainowner muss Speichern, Revision und Audit behalten.');
$assert(str_contains($startpartnerReview, 'Sechs kurze Fragen reichen für die Startpartner-Entscheidung.'), 'Die kompakte Bedienlogik muss für den Operator erklärt sein.');
$assert(str_contains($startpartnerReview, 'Notiz / offene Punkte'), 'Es muss genau ein gemeinsames Notizfeld geben.');
$assert(str_contains($startpartnerReview, 'Alle 6 Kriterien'), 'Die Zusammenfassung muss den Sechs-Kriterien-Vertrag zeigen.');
$assert(!str_contains($startpartnerReview, 'Alle 14 Prüfpunkte'), 'Die alte 14-Punkte-Bedienoberfläche darf nicht mehr sichtbar sein.');
$assert(!str_contains($startpartnerReview, 'sp-reason-${dimension}'), 'Pro-Dimension-Begründungen dürfen nicht mehr gerendert werden.');
$assert(!str_contains($startpartnerReview, 'sp-evidence-${dimension}'), 'Pro-Dimension-Nachweisfelder dürfen nicht mehr gerendert werden.');
$assert(str_contains($startpartnerReview, "metric('Fälligkeit',data.next_review_at?formatDate(data.next_review_at):'Nicht gesetzt')"), 'Nicht gesetzte Fälligkeit muss neutral dargestellt werden.');
$assert(str_contains($startpartnerReview, "metric('Bearbeiter',data.assigned_to||'Nicht zugewiesen')"), 'Nicht zugewiesener Bearbeiter muss neutral dargestellt werden.');

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-2 Domain Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-2 Domain Contract: OK ===\n";
