<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$domain='';foreach(glob($root.'/api/startpartner/_gate4_*.php')?:[] as $file){$domain.=file_get_contents($file)."\n";}
$sql=file_get_contents($root.'/api/sql/012_startpartner_gate4_onboarding_content_activation.sql');
$control=file_get_contents($root.'/js/control-center/startpartner-gate4.js');
$smoke=file_get_contents($root.'/tools/smoke-check-deploy.py');
$failures=[];$assert=static function(bool $ok,string $message)use(&$failures):void{if(!$ok)$failures[]=$message;};
foreach(['startpartner_pilot_onboarding_items','startpartner_pilot_content_links','startpartner_pilot_measurement_preflights','startpartner_pilot_distribution_commitments','startpartner_pilot_usages'] as $table){$assert(str_contains($sql,$table),"Gate-4 schema owner missing: {$table}");}
foreach(['CREATE TABLE IF NOT EXISTS subscriptions','ALTER TABLE subscriptions','ALTER TABLE publication_entitlements','ALTER TABLE publication_consumptions','stripe_'] as $forbidden){$assert(!str_contains($sql,$forbidden),"Migration 012 must not mutate locked regular owner: {$forbidden}");}
foreach(['be_send_mail','stripe_checkout','publication_entitlements','publication_consumptions'] as $forbidden){$assert(!str_contains($domain,$forbidden),"Gate-4 domain contains forbidden side effect: {$forbidden}");}
$assert(str_contains($domain,'organizer_magic_links'),'Gate-4 v2 portal-access proof must read the canonical Magic-Link owner.');
$magicLinkMutation='/\b(?:INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+organizer_magic_links\b/i';
$assert(preg_match($magicLinkMutation,$domain)!==1,'Gate-4 may read organizer_magic_links but must never mutate the Magic-Link owner.');
$temporaryOwners=glob($root.'/api/startpartner/evidence/*')?:[];
$assert($temporaryOwners===[],'Temporary Gate-4 evidence endpoints must be removed.');
$durableRuntime=$domain."\n".$smoke;
foreach(['gate4_staging_lifecycle_241','gate4_staging_migration_241','gate4_staging_marker_cleanup_241','241_gate4_staging_lifecycle_completed','bocholt_gate4_staging_final_241'] as $forbidden){$assert(!str_contains($durableRuntime,$forbidden),"Temporary Gate-4 evidence token remains: {$forbidden}");}
$assert(str_contains($domain,"status = 'approved'")&&str_contains($domain,"status = 'active'"),'Atomic activation must approve first content and activate pilot owners.');
$assert(str_contains($domain,"status = 'released'")&&str_contains($domain,'capacity_before')&&str_contains($domain,'capacity_after'),'Activation must replace the reservation without changing occupied capacity.');
$assert(str_contains($control,'exactly')===false,'UI must not contain test-only evidence claims.');
if($failures){fwrite(STDERR,"=== Startpartner Gate-4 Side-Effect Contract: FAILED ===\n".implode("\n",array_map(static fn($v)=>'- '.$v,$failures))."\n");exit(1);}echo "=== Startpartner Gate-4 Side-Effect Contract: OK ===\n";
