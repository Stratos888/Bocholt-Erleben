<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$domain=file_get_contents($root.'/api/startpartner/_gate4_domain.php');
$content=file_get_contents($root.'/api/startpartner/content.php');
if($domain===false||$content===false){fwrite(STDERR,"Gate-4 submission owners are missing.\n");exit(1);}
$required=[
    "INNER JOIN submissions",
    "submission organizer does not match pilot organizer",
    "['event','activity']",
    "publication_status='approved'",
    "UPDATE submissions SET status='approved'",
    "startpartner_pilot_usage",
];
$combined=$domain."\n".$content;
foreach($required as $token){if(!str_contains($combined,$token)){fwrite(STDERR,"Missing submission contract token: {$token}\n");exit(1);}}
$forbidden=["publication_entitlements SET","publication_consumptions (","release-payment.php","stripe_checkout"];
foreach($forbidden as $token){if(str_contains($combined,$token)){fwrite(STDERR,"Pilot submission path touches forbidden regular owner: {$token}\n");exit(1);}}
printf("=== Startpartner Gate-4 Submission Contract: OK ===\n");
