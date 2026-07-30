<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$domain=file_get_contents($root.'/api/startpartner/_gate4_domain.php');
$content=file_get_contents($root.'/api/startpartner/content.php');
$approval=file_get_contents($root.'/api/submissions/_approval_domain.php');
if($domain===false||$content===false||$approval===false){fwrite(STDERR,"Gate-4 submission owners are missing.\n");exit(1);}
$required=[
    "INNER JOIN submissions",
    "submission organizer does not match pilot organizer",
    "['event','activity']",
    "publication_status='approved'",
    "UPDATE submissions SET status='approved'",
    "startpartner_pilot_usage",
    "requested_model_key='startpartner_pilot'",
    "payment_kind='pilot'",
    "intake_origin='startpartner_pilot'",
    "be_submission_approval_assert_pilot_path",
    "be_submission_approval_mark_pilot_approved",
];
$combined=$domain."\n".$content."\n".$approval;
foreach($required as $token){if(!str_contains($combined,$token)){fwrite(STDERR,"Missing submission contract token: {$token}\n");exit(1);}}
$forbidden=["publication_entitlements SET","publication_consumptions (","release-payment.php","stripe_checkout","be_send_mail("];
foreach($forbidden as $token){if(str_contains($combined,$token)){fwrite(STDERR,"Pilot submission path touches forbidden regular owner: {$token}\n");exit(1);}}
printf("=== Startpartner Gate-4 Submission Contract: OK ===\n");
