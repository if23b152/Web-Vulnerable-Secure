<?php
include 'cookies.php';

require __DIR__ . '/db_queries/db.php';
require __DIR__ . '/db_queries/GET_Emails_from_User.php'; 

$emails = $_SESSION['emails'] ?? [];

$attachmentsByEmail = [];

if (!empty($emails)) {
    $emailIds = array_column($emails, 'email_id');
    $ph = [];
    $params = [];
    foreach ($emailIds as $i => $id) {
        $ph[] = ":id{$i}";
        $params[":id{$i}"] = (int)$id;
    }

    $sql = "SELECT attachment_id, email_id, orig_name, mime_type
            FROM email_attachments
            WHERE email_id IN (" . implode(',', $ph) . ")";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $allAtts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allAtts as $a) {
        $attachmentsByEmail[(int)$a['email_id']][] = $a;
    }
}
?>
<h2>Deine Nachrichten</h2>

<?php if (empty($emails)): ?>
  <p>Du hast noch keine Emails in der Inbox!</p>
<?php else: ?>
  <ul>
    <?php foreach ($emails as $mail): ?>
      <li style="margin-bottom:1rem;">
        <strong>Von:</strong> <?= htmlspecialchars($mail['sender_name']) ?><br>
        <strong>Betreff:</strong> <?= htmlspecialchars($mail['subject']) ?><br>
        <div><?= nl2br(htmlspecialchars($mail['body'])) ?></div>
        <em>Gesendet am: <?= htmlspecialchars($mail['sent_at']) ?></em>

        <?php
          $eid = (int)$mail['email_id'];
          $atts = $attachmentsByEmail[$eid] ?? [];
          if (!empty($atts)):
        ?>
          <h4>Anhänge</h4>
          <ul>
            <?php foreach ($atts as $a): ?>
              <li>
                <a href="download_attachment.php?att=<?= (int)$a['attachment_id'] ?>&disp=attachment">
                  <?= htmlspecialchars($a['orig_name']) ?>
                </a>
                <?php if (strpos($a['mime_type'], 'image/') === 0): ?>
                  <br>
                  <img src="download_attachment.php?att=<?= (int)$a['attachment_id'] ?>&disp=inline"
                       alt="" style="max-width:200px;height:auto;border:1px solid #ddd;">
                <?php elseif ($a['mime_type'] === 'application/pdf'): ?>
                  <br>
                  <a href="download_attachment.php?att=<?= (int)$a['attachment_id'] ?>&disp=inline" target="_blank">
                    PDF im Browser öffnen
                  </a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
