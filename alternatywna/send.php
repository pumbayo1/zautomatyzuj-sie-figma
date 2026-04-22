<?php
// Formularz kontaktowy — Zautomatyzuj Się
// Formularz kontaktowy (audyt bezpieczeństwa 21.03.2026)

$config = [
    'to' => 'zautomatyzujsie@gmail.com',
    'from_name' => 'Formularz — Zautomatyzuj Się',
    'from_email' => 'noreply@zautomatyzujsie.com',
    'site_url' => 'https://zautomatyzujsie.com',
    'site_name' => 'Zautomatyzuj Się',
    'rate_limit_seconds' => 30,
    'max_per_hour' => 10,
    'honeypot_field' => 'website_url',
    'allowed_topics' => [
        'Audyt Widoczności 360',
        'Audyt + Plan wdrożenia',
        'Analiza firmy',
        'Strona WWW',
        'Sklep internetowy',
        'SEO / AI Search',
        'SEO / Pozycjonowanie',
        'Automatyzacje i integracje',
        'Automatyzacja',
        'Inne',
    ],
];

$rate_dir = __DIR__ . '/.rate_limits';
if (!is_dir($rate_dir)) {
    mkdir($rate_dir, 0700, true);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /");
    exit;
}

// Honeypot
if (!empty($_POST[$config['honeypot_field']]) || !empty($_POST['contact_me'])) {
    sleep(2);
    header("Location: /kontakt.html?sent=1");
    exit;
}

// Proof-of-Interaction
$proof = $_POST['_proof'] ?? '';
if (empty($proof)) {
    sleep(2);
    header("Location: /kontakt.html?sent=1");
    exit;
}
$decoded = base64_decode($proof, true);
$proof_valid = false;
if ($decoded !== false) {
    $parts = explode(':', $decoded);
    if (count($parts) >= 3) {
        $js_score = intval($parts[0]);
        $js_elapsed = intval($parts[1]);
        if ($js_score >= 2 && $js_elapsed >= 3000) {
            $proof_valid = true;
        }
    }
}
if (!$proof_valid) {
    sleep(2);
    header("Location: /kontakt.html?sent=1");
    exit;
}

// Rate limit
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip_hash = md5($ip);
$rate_file = $rate_dir . '/' . $ip_hash;
$now = time();

if (file_exists($rate_file)) {
    $last = intval(file_get_contents($rate_file));
    if (($now - $last) < $config['rate_limit_seconds']) {
        header("Location: /kontakt.html?error=ratelimit");
        exit;
    }
}
file_put_contents($rate_file, $now);

// Sanitize
function clean($str) {
    $str = trim($str);
    $str = strip_tags($str);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    $str = preg_replace('/[\x00-\x1f\x7f]/u', '', $str);
    return $str;
}

$name = clean($_POST['name'] ?? '');
$email = clean($_POST['email'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$topic = clean($_POST['subject'] ?? '');
$message = clean($_POST['message'] ?? '');

// Walidacja
if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    header("Location: /kontakt.html?error=name");
    exit;
}
if (!filter_var(str_replace(['&amp;','&#039;','&quot;','&lt;','&gt;'], ['&',"'",'"','<','>'], $email), FILTER_VALIDATE_EMAIL)) {
    header("Location: /kontakt.html?error=email");
    exit;
}
if (!empty($topic) && !in_array(html_entity_decode($topic), $config['allowed_topics'])) {
    $topic = 'Inne';
}
if (empty($message) || strlen($message) < 5 || strlen($message) > 5000) {
    header("Location: /kontakt.html?error=message");
    exit;
}

// Injection check
foreach ([$name, $email, $topic, $message] as $field) {
    if (preg_match('/\r|\n|%0a|%0d|content-type:|bcc:|cc:|to:/i', $field)) {
        sleep(3);
        header("Location: /kontakt.html?sent=1");
        exit;
    }
}

// Build email
$clean_email = html_entity_decode($email);
$clean_name = html_entity_decode($name);
$clean_topic = html_entity_decode($topic);
$clean_message = html_entity_decode($message);
$clean_phone = html_entity_decode($phone);

$subject = "Nowe zapytanie: {$clean_topic} — {$clean_name}";

$body = "
<html><body style='font-family:Arial,sans-serif;background:#f5f5f5;padding:20px'>
<div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1)'>
<div style='background:linear-gradient(135deg,#E8520F,#D4442A);padding:24px;color:#fff'>
<h2 style='margin:0'>Nowe zapytanie ze strony</h2>
<p style='margin:4px 0 0;opacity:0.9'>zautomatyzujsie.com</p>
</div>
<div style='padding:24px'>
<p><strong>Imię:</strong> {$clean_name}</p>
<p><strong>Email:</strong> <a href='mailto:{$clean_email}'>{$clean_email}</a></p>
" . (!empty($clean_phone) ? "<p><strong>Telefon:</strong> {$clean_phone}</p>" : "") . "
<p><strong>Temat:</strong> {$clean_topic}</p>
<hr style='border:1px solid #eee'>
<p><strong>Wiadomość:</strong></p>
<p style='background:#f9f9f9;padding:16px;border-radius:8px;line-height:1.6'>{$clean_message}</p>
</div>
<div style='padding:16px 24px;background:#f5f5f5;font-size:12px;color:#999'>
IP: {$ip} | Data: " . date('Y-m-d H:i:s') . "
</div>
</div>
</body></html>";

$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    "From: {$config['from_name']} <{$config['from_email']}>",
    "Reply-To: {$clean_name} <{$clean_email}>",
    "Return-Path: {$config['from_email']}",
    "X-Mailer: PHP/" . phpversion(),
]);

$sent = @mail($config['to'], $subject, $body, $headers, "-f{$config['from_email']}");

// Email potwierdzenie do klienta
if ($sent) {
    $confirm_subject = "Dziękujemy za kontakt — Zautomatyzuj Się";
    $confirm_body = "
    <html><body style='font-family:Arial,sans-serif;background:#f5f5f5;padding:20px'>
    <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1)'>
    <div style='background:linear-gradient(135deg,#E8520F,#D4442A);padding:32px;color:#fff;text-align:center'>
    <h1 style='margin:0;font-size:1.6rem'>Dziękujemy za kontakt!</h1>
    <p style='margin:8px 0 0;opacity:0.9;font-size:0.95rem'>Zautomatyzuj Się — Twoja firma. Widoczna.</p>
    </div>
    <div style='padding:32px'>
    <p style='font-size:1.05rem;color:#333;line-height:1.8'>Cześć <strong>{$clean_name}</strong>!</p>
    <p style='color:#555;line-height:1.8'>Otrzymaliśmy Twoją wiadomość i już ją analizujemy. Odezwiemy się najszybciej jak to możliwe — zazwyczaj <strong>w ciągu kilku godzin</strong>.</p>
    <div style='background:#f8f9ff;border-left:4px solid #E8520F;padding:16px 20px;border-radius:0 8px 8px 0;margin:20px 0'>
    <p style='margin:0;color:#555;font-size:0.9rem'><strong>Twoje zapytanie:</strong></p>
    <p style='margin:6px 0 0;color:#333;font-size:0.9rem'>Temat: {$clean_topic}</p>
    <p style='margin:4px 0 0;color:#666;font-size:0.85rem'>{$clean_message}</p>
    </div>
    <p style='color:#555;line-height:1.8'>Jeśli masz dodatkowe pytania, odpowiedz na tego maila lub napisz do nas bezpośrednio:</p>
    <p style='color:#555'>
    📧 <a href='mailto:zautomatyzujsie@gmail.com' style='color:#E8520F'>zautomatyzujsie@gmail.com</a><br>
    📱 <a href='https://ig.me/m/zautomatyzujsie' style='color:#E8520F'>Instagram: @zautomatyzujsie</a><br>
    💬 <a href='https://m.me/zautomatyzujsie' style='color:#E8520F'>Facebook Messenger</a>
    </p>
    <p style='color:#888;font-size:0.85rem;margin-top:24px'>Pozdrawiamy,<br><strong style='color:#333'>Zespół Zautomatyzuj Się</strong></p>
    </div>
    <div style='padding:16px 32px;background:#f5f5f5;text-align:center;font-size:11px;color:#999'>
    Zautomatyzuj Się — Profesjonalna analiza widoczności online<br>
    <a href='https://zautomatyzujsie.com' style='color:#E8520F'>zautomatyzujsie.com</a>
    </div>
    </div>
    </body></html>";

    $confirm_headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        "From: {$config['from_name']} <{$config['from_email']}>",
        "Reply-To: {$config['to']}",
        "Return-Path: {$config['from_email']}",
    ]);

    @mail($clean_email, $confirm_subject, $confirm_body, $confirm_headers, "-f{$config['from_email']}");

    header("Location: /dziekujemy.html");
} else {
    header("Location: /kontakt.html?error=send");
}
exit;
