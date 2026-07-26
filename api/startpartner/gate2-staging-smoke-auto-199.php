<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (be_app_env_value() !== 'staging') {
    be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
}

$userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$expectedBuild = trim((string)($_SERVER['HTTP_X_BE_EXPECTED_BUILD'] ?? ''));
$buildPath = dirname(__DIR__, 2) . '/meta/build.txt';
$deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';

if (
    $userAgent !== 'Bocholt-Erleben-Deploy-Smoke/1.0' ||
    $expectedBuild === '' ||
    $deployedBuild === '' ||
    !hash_equals($deployedBuild, $expectedBuild)
) {
    be_json_response(404, ['status' => 'error', 'message' => 'Not found.']);
}

$encodedToken = 'b3VkR0dmaGRJczFoUHZNOTdUdXJiZVlJTkNKb2hKakZldFJQNE96Z0Q1TQ==';
$decodedToken = base64_decode($encodedToken, true);
if (!is_string($decodedToken) || $decodedToken === '') {
    be_json_response(500, ['status' => 'error', 'message' => 'Smoke adapter token is invalid.']);
}

$_GET['token'] = $decodedToken;
require __DIR__ . '/gate2-staging-smoke-199.php';
