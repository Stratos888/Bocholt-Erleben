<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate2_domain.php';

const BE_STARTPARTNER_COMPACT_CHECKS = [
    'local_editorial_fit' => [
        'label' => 'Passt das Angebot lokal und redaktionell?',
        'dimensions' => ['local_relevance', 'editorial_fit'],
    ],
    'content_sources' => [
        'label' => 'Sind geeignete Inhalte bzw. Quellen vorhanden?',
        'dimensions' => ['content_sources'],
    ],
    'user_value_reach' => [
        'label' => 'Entsteht ein relevanter Mehrwert für Nutzer und Reichweite?',
        'dimensions' => ['content_leverage', 'reach_leverage', 'user_need'],
    ],
    'cooperation_maintenance' => [
        'label' => 'Ist die Zusammenarbeit und laufende Pflege realistisch?',
        'dimensions' => ['organization_contact', 'maintenance_capability', 'cooperation_readiness'],
    ],
    'effort_regular_path' => [
        'label' => 'Ist der Einrichtungs-/Betreuungsaufwand sinnvoll und der weitere Weg plausibel?',
        'dimensions' => ['setup_effort', 'support_effort', 'regular_path'],
    ],
    'legal_information' => [
        'label' => 'Sind Rechte, Technik und notwendige Angaben geklärt?',
        'dimensions' => ['legal_technical', 'required_information'],
    ],
];

const BE_STARTPARTNER_COMPACT_RESULTS = [
    'fit' => ['label' => 'Passt', 'assessment' => 'adequate'],
    'unclear' => ['label' => 'Unklar', 'assessment' => 'unknown'],
    'not_fit' => ['label' => 'Passt nicht', 'assessment' => 'weak'],
];

function be_startpartner_compact_assert_mapping(): void
{
    $mapped = [];
    foreach (BE_STARTPARTNER_COMPACT_CHECKS as $check) {
        foreach ($check['dimensions'] as $dimension) {
            if (isset($mapped[$dimension])) {
                throw new LogicException('Compact Startpartner mapping contains a duplicate dimension.');
            }
            $mapped[$dimension] = true;
        }
    }
    $expected = BE_STARTPARTNER_QUALIFICATION_DIMENSIONS;
    $actual = array_keys($mapped);
    sort($expected);
    sort($actual);
    if ($actual !== $expected) {
        throw new LogicException('Compact Startpartner mapping must cover all qualification dimensions exactly once.');
    }
}

function be_startpartner_compact_expand(PDO $pdo, string $candidateId, array $input): array
{
    if (array_key_exists('qualifications', $input)) {
        throw new InvalidArgumentException('Use either checks or qualifications, not both.');
    }
    $checks = $input['checks'] ?? null;
    if (!is_array($checks)) {
        throw new InvalidArgumentException('checks must be an object.');
    }

    be_startpartner_compact_assert_mapping();
    $expectedKeys = array_keys(BE_STARTPARTNER_COMPACT_CHECKS);
    $actualKeys = array_keys($checks);
    sort($expectedKeys);
    sort($actualKeys);
    if ($actualKeys !== $expectedKeys) {
        throw new InvalidArgumentException('All six eligibility checks are required exactly once.');
    }

    $note = be_startpartner_clean_text($input['note'] ?? null, 2000, 'note');
    $candidate = be_startpartner_gate2_candidate_detail($pdo, $candidateId, false);
    $currentByDimension = [];
    foreach ($candidate['qualifications'] as $row) {
        $currentByDimension[(string)$row['dimension']] = (string)($row['assessment'] ?? 'unknown');
    }

    $evidence = 'Prüfgrundlage: Startpartner-Anfrage und hinterlegte Kandidatenangaben.';
    $website = trim((string)($candidate['website_url'] ?? ''));
    if ($website !== '') {
        $evidence .= ' Website/Quelle: ' . $website;
    }

    $qualifications = [];
    foreach (BE_STARTPARTNER_COMPACT_CHECKS as $key => $check) {
        $resultKey = trim((string)($checks[$key] ?? ''));
        if (!array_key_exists($resultKey, BE_STARTPARTNER_COMPACT_RESULTS)) {
            throw new InvalidArgumentException('Invalid result for eligibility check: ' . $key);
        }
        $result = BE_STARTPARTNER_COMPACT_RESULTS[$resultKey];
        foreach ($check['dimensions'] as $dimension) {
            $assessment = $result['assessment'];
            if (
                $resultKey === 'fit'
                && ($currentByDimension[$dimension] ?? 'unknown') === 'strong'
            ) {
                $assessment = 'strong';
            }
            $reason = 'Eignungscheck: ' . $check['label'] . ' – ' . $result['label'] . '.';
            if ($note !== null) {
                $reason .= "\nNotiz: " . $note;
            }
            $qualifications[] = [
                'dimension' => $dimension,
                'assessment' => $assessment,
                'reason' => $reason,
                'evidence_text' => $evidence,
            ];
        }
    }

    $expanded = $input;
    unset($expanded['checks'], $expanded['note']);
    $expanded['qualifications'] = $qualifications;
    return $expanded;
}

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }
    $candidateId = trim((string)($input['candidate_id'] ?? ''));
    if ($candidateId === '') {
        throw new InvalidArgumentException('candidate_id is required.');
    }
    if (array_key_exists('checks', $input)) {
        $input = be_startpartner_compact_expand(be_db(), $candidateId, $input);
    }
    $result = be_startpartner_gate2_qualification_update(be_db(), $candidateId, $input);
    be_json_response(200, ['status' => 'ok', 'data' => $result]);
} catch (BeStartpartnerConflictException $error) {
    be_json_response(409, [
        'status' => 'error',
        'code' => 'STARTPARTNER_CONFLICT',
        'message' => 'Zwischenzeitlich geändert.',
        'current' => $error->currentState,
        'error_message' => $error->getMessage(),
    ]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    $statusCode = str_starts_with($error->getMessage(), 'STARTPARTNER_SCHEMA_MISSING:') ? 503 : 404;
    be_json_response($statusCode, [
        'status' => 'error',
        'message' => $statusCode === 503 ? 'Startpartner schema is not ready.' : $error->getMessage(),
        'error_message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Startpartner qualifications could not be updated.',
        'error_message' => $error->getMessage(),
    ]);
}
