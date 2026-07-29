<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'api/startpartner/_gate4_domain.php',
    'api/startpartner/onboarding.php',
    'api/startpartner/content.php',
    'api/startpartner/activation.php',
    'api/sql/012_startpartner_gate4_onboarding_content_activation.sql',
];
$combined='';
foreach($files as $file){$path=$root.'/'.$file;if(!is_file($path)){fwrite(STDERR,"Missing {$file}\n");exit(1);} $combined.=file_get_contents($path)."\n";}
$forbidden=['api/stripe','stripe_checkout','stripe_subscription_id =','be_send_mail(','request-magic-link.php','Formspree','Events_Staging'];
foreach($forbidden as $token){if(str_contains($combined,$token)){fwrite(STDERR,"Forbidden Gate-4 side effect token: {$token}\n");exit(1);}}
$required=['startpartner_pilot_usage','pending_activation','Europe/Berlin','converted_to_pilot','capacity_delta'];
foreach($required as $token){if(!str_contains($combined,$token)){fwrite(STDERR,"Missing Gate-4 safety token: {$token}\n");exit(1);}}
printf("=== Startpartner Gate-4 Side-Effect Contract: OK ===\n");
