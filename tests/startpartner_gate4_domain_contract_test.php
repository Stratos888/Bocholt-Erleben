<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate4_domain.php';

$failures=[];
$assert=static function(bool $condition,string $message) use (&$failures): void { if(!$condition)$failures[]=$message; };

$assert(be_startpartner_gate4_planned_end_date('2026-01-31')==='2026-07-31','31 January must end 31 July.');
$assert(be_startpartner_gate4_planned_end_date('2026-08-31')==='2027-02-28','31 August 2026 must end 28 February 2027.');
$assert(be_startpartner_gate4_planned_end_date('2027-08-31')==='2028-02-29','31 August 2027 must end 29 February 2028.');
$assert(be_startpartner_gate4_planned_end_date('2026-07-29')==='2027-01-29','Normal calendar day must be preserved.');
$assert(count(BE_STARTPARTNER_GATE4_REQUIRED_ITEM_KEYS)===14,'Gate 4 must require exactly fourteen onboarding items.');
$assert(count(array_unique(BE_STARTPARTNER_GATE4_REQUIRED_ITEM_KEYS))===14,'Onboarding item keys must be unique.');
$hashA=be_startpartner_gate4_payload_hash(['b'=>2,'a'=>1]);
$hashB=be_startpartner_gate4_payload_hash(['a'=>1,'b'=>2]);
$assert(hash_equals($hashA,$hashB),'Payload hash must be key-order independent.');

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);} 
printf("=== Startpartner Gate-4 Domain Contract: OK ===\n");
