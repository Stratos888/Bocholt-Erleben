<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate2_domain.php';
require_once dirname(__DIR__) . '/api/startpartner/_review_decision_domain.php';

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
$assert(count(BE_STARTPARTNER_QUALIFICATION_DIMENSIONS) === 14, 'Gate 2 behält genau 14 Legacy-Qualifikationsdimensionen für Kompatibilität.');
$assert(count(BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS) === 6, 'Gate 2 behält genau sechs Legacy-Mindestdimensionen.');

$unknown = array_map(
    static fn(string $dimension): array => ['dimension' => $dimension, 'assessment' => 'unknown'],
    BE_STARTPARTNER_QUALIFICATION_DIMENSIONS
);
$unknownReadiness = be_startpartner_gate2_readiness($unknown);
$assert(!$unknownReadiness['ready'], 'Legacy-Readiness bleibt bei unbewerteten Dimensionen false.');
$assert(count($unknownReadiness['blockers']) === 14, 'Legacy-Readiness muss weiterhin alle unbewerteten Dimensionen abbilden.');

$ready = array_map(
    static fn(string $dimension): array => [
        'dimension' => $dimension,
        'assessment' => in_array($dimension, BE_STARTPARTNER_HARD_QUALIFICATION_DIMENSIONS, true) ? 'adequate' : 'weak',
    ],
    BE_STARTPARTNER_QUALIFICATION_DIMENSIONS
);
$readyState = be_startpartner_gate2_readiness($ready);
$assert($readyState['ready'], 'Der bestehende Legacy-Readiness-Vertrag darf nicht gebrochen werden.');
$ready[0]['assessment'] = 'weak';
$blockedState = be_startpartner_gate2_readiness($ready);
$assert(!$blockedState['ready'], 'Der bestehende Legacy-Mindestblocker muss intakt bleiben.');
$assert(($blockedState['blockers'][0]['code'] ?? '') === 'minimum_not_met', 'Harter Legacy-Mindestblocker benötigt einen stabilen Code.');

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
    'Legacy-Bewertungen ohne Reason und Evidence müssen weiterhin abgelehnt werden.'
);

$normalizedQualification = be_startpartner_gate2_normalize_qualification([
    'dimension' => 'local_relevance',
    'assessment' => 'adequate',
    'reason' => 'Lokaler Bezug ist belegt.',
    'evidence_text' => 'Sitz und Angebot liegen in Bocholt.',
]);
$assert($normalizedQualification['assessment'] === 'adequate', 'Legacy-Qualifikationsnormalisierung muss gültige Bewertungen erhalten.');

$assert(BE_STARTPARTNER_REVIEW_DECISIONS === ['approve', 'needs_information', 'reject', 'waitlist'], 'Der neue Review-Vertrag benötigt exakt vier systemische Entscheidungen.');
foreach (['new','prequalifying','contact_pending','awaiting_response','qualifying','needs_information','decision_ready','waitlisted'] as $status) {
    $assert(in_array($status, BE_STARTPARTNER_REVIEW_ACTIVE_STATUSES, true), "KI-gestützte Entscheidung muss aus {$status} möglich sein.");
}
$future20 = new DateTimeImmutable(be_startpartner_review_default_future(20), new DateTimeZone('UTC'));
$future14 = new DateTimeImmutable(be_startpartner_review_default_future(14), new DateTimeZone('UTC'));
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$assert($future20 > $now->modify('+19 days') && $future20 < $now->modify('+21 days'), 'Standardreservierung muss ungefähr 20 Tage laufen.');
$assert($future14 > $now->modify('+13 days') && $future14 < $now->modify('+15 days'), 'Wartelisten-Neubewertung muss ungefähr 14 Tage vorausliegen.');

$reviewDomain = (string)file_get_contents(dirname(__DIR__) . '/api/startpartner/_review_decision_domain.php');
$reviewEndpoint = (string)file_get_contents(dirname(__DIR__) . '/api/startpartner/review-decision.php');
$startpartnerReview = (string)file_get_contents(dirname(__DIR__) . '/js/control-center/startpartner-review.js');
$aiReview = (string)file_get_contents(dirname(__DIR__) . '/js/control-center/startpartner-ai-review.js');
$reviewRenderer = (string)file_get_contents(dirname(__DIR__) . '/js/control-center/review-render.js');

foreach (['ai_assisted_human_decision','be_startpartner_gate2_run_operation','be_startpartner_gate2_insert_decision','Hard capacity stop reached.','accepted_pending_terms','needs_information','rejected','waitlisted'] as $marker) {
    $assert(str_contains($reviewDomain, $marker), "Review-Domainmarker fehlt: {$marker}");
}
$assert(!str_contains($reviewDomain, 'be_startpartner_gate2_qualification_update'), 'Die menschliche Entscheidung darf keine 14 Legacy-Dimensionen künstlich befüllen.');
$assert(str_contains($reviewDomain, 'be_startpartner_review_default_future(20)'), 'Aufnahme benötigt die automatische 20-Tage-Reservierung.');
$assert(str_contains($reviewDomain, 'be_startpartner_review_default_future(14)'), 'Warteliste benötigt die automatische 14-Tage-Neubewertung.');
foreach (['be_require_review_access','be_startpartner_review_decision','STARTPARTNER_CONFLICT'] as $marker) {
    $assert(str_contains($reviewEndpoint, $marker), "Review-Endpunktmarker fehlt: {$marker}");
}

foreach (['Prüfprompt kopieren','EMPFEHLUNG: AUFNEHMEN | RÜCKFRAGE NÖTIG | NICHT GEEIGNET','SICHERHEIT: hoch | mittel | niedrig','Startpartner aufnehmen','Rückfrage nötig','Nicht geeignet','review_approve','review_needs_information','review_reject','/api/startpartner/review-decision.php'] as $marker) {
    $assert(str_contains($aiReview, $marker), "KI-Prüfmarker fehlt: {$marker}");
}
foreach (['lokale und redaktionelle Passung','geeignete Inhalte bzw. belastbare Quellen','relevanter Mehrwert für Nutzer','realistische Zusammenarbeit und laufende Pflege','Einrichtungs-/Betreuungsaufwand','Rechte-, Technik- oder Pflichtangaben'] as $marker) {
    $assert(str_contains($aiReview, $marker), "Fachkriterium fehlt im Prüfprompt: {$marker}");
}
foreach (['Organisation: ${organization}','Website / öffentliche Quelle: ${website}','Gewünschter Bereich: ${scope}','Beschreibung aus der Anfrage: ${description}'] as $marker) {
    $assert(str_contains($aiReview, $marker), "Minimierter Anfragedatenmarker fehlt: {$marker}");
}
foreach (['data.contacts','contact.email','contact.phone'] as $forbidden) {
    $assert(!str_contains($aiReview, $forbidden), "Der Prüfprompt darf keine unnötigen Kontaktdaten referenzieren: {$forbidden}");
}
foreach (['Eignungscheck speichern','sp-check-','Sechs kurze Fragen reichen für die Startpartner-Entscheidung.'] as $forbidden) {
    $assert(!str_contains($startpartnerReview, $forbidden) && !str_contains($aiReview, $forbidden), "Manuelle Eignungscheck-Bedienung darf nicht mehr sichtbar sein: {$forbidden}");
}
$assert(str_contains($startpartnerReview, 'renderStartpartnerAiReview'), 'Startpartner-Renderer muss den KI-gestützten Review einbetten.');
$assert(str_contains($reviewRenderer, "startpartnerAiReviewStatuses.has(status)"), 'Alte Startpartner-Aktionen müssen im aktiven KI-Prüfzustand gefiltert werden.');
$assert(str_contains($reviewRenderer, "'edit_qualification'"), 'Der Renderer muss die alte Qualifikationsaktion explizit ausblenden.');

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-2 Domain Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-2 Domain Contract: OK ===\n";
