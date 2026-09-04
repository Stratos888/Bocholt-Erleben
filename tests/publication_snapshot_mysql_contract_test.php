<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/submissions/_publication_snapshot.php';

$dsn = getenv('PUBLICATION_SNAPSHOT_TEST_DSN') ?: '';
$user = getenv('PUBLICATION_SNAPSHOT_TEST_USER') ?: '';
$password = getenv('PUBLICATION_SNAPSHOT_TEST_PASSWORD') ?: '';
if ($dsn === '' || $user === '') exit(2);
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO organizers (organization_name,email,email_normalized) VALUES ('Snapshot Contract','snapshot@example.org','snapshot@example.org')");
    $organizerId = (int)$pdo->lastInsertId();
    $insert = $pdo->prepare("INSERT INTO submissions (organizer_id,submission_kind,status,requested_model_key,payment_kind,organization_name_snapshot,email_snapshot,title,start_date,time_text,location_name,location_address,location_public_confirmed,event_url,ticket_url,description_text,approved_at) VALUES (:organizer_id,:kind,'approved',:model,'subscription','Snapshot Contract','snapshot@example.org',:title,:start_date,'10:00','Markt','Markt 1',1,'https://example.org/approved','https://example.org/ticket',:description,CURRENT_TIMESTAMP)");
    foreach ([['event','active','Approved event','2035-01-01'],['activity','activity_basic','Approved activity',null]] as [$kind,$model,$title,$date]) {
        $insert->execute(['organizer_id'=>$organizerId,'kind'=>$kind,'model'=>$model,'title'=>$title,'start_date'=>$date,'description'=>'Approved description']);
        $id = (int)$pdo->lastInsertId();
        be_replace_submission_publication_snapshot($pdo, $id);
        $pdo->prepare("UPDATE submissions SET title='Private revision',description_text='Must not leak',status='in_review' WHERE id=?")->execute([$id]);
        $snapshot = $pdo->query("SELECT title,description_text FROM submission_publication_snapshots WHERE submission_id={$id}")->fetch(PDO::FETCH_ASSOC);
        $assert($snapshot['title'] === $title && $snapshot['description_text'] === 'Approved description', "$kind revision leaked into the published snapshot.");
        $pdo->prepare("UPDATE submissions SET status='rejected' WHERE id=?")->execute([$id]);
        $assert((int)$pdo->query("SELECT COUNT(*) FROM submission_publication_snapshots WHERE submission_id={$id}")->fetchColumn() === 1, "$kind rejection removed the last approved snapshot.");
        $pdo->prepare("UPDATE submissions SET status='approved',approved_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
        be_replace_submission_publication_snapshot($pdo, $id);
        be_replace_submission_publication_snapshot($pdo, $id);
        $assert((int)$pdo->query("SELECT COUNT(*) FROM submission_publication_snapshots WHERE submission_id={$id}")->fetchColumn() === 1, "$kind re-approval duplicated the snapshot.");
        $assert($pdo->query("SELECT title FROM submission_publication_snapshots WHERE submission_id={$id}")->fetchColumn() === 'Private revision', "$kind re-approval did not replace the snapshot.");
        be_remove_submission_publication_snapshot($pdo, $id);
        $assert((int)$pdo->query("SELECT COUNT(*) FROM submission_publication_snapshots WHERE submission_id={$id}")->fetchColumn() === 0, "$kind unpublish did not remove the snapshot.");
    }
} finally {
    $pdo->rollBack();
}
if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo "Publication snapshot database contract: OK\n";
