<?php

function sendShopEmail(
    string $recipient,
    string $subject,
    string $body,
    ?string $htmlBody = null
): bool {
    $autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor'
        . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!is_file($autoloadPath)) {
        error_log("ShopEase: PHPMailer is not installed. Run composer install.");
        return false;
    }

    require_once $autoloadPath;

    $host = getenv("SHOP_SMTP_HOST") ?: "smtp.gmail.com";
    $portValue = getenv("SHOP_SMTP_PORT") ?: "587";
    $username = getenv("SHOP_SMTP_USERNAME") ?: "mf064558@gmail.com";
    $password = getenv("SHOP_SMTP_PASSWORD") ?: "ydhd ikkh lzmo pzbk";
    $fromEmail = getenv("SHOP_SMTP_FROM_EMAIL") ?: $username;

    if ($username === "" || $password === "") {
        error_log(
            "ShopEase: SMTP credentials are missing. Configure "
            . "SHOP_SMTP_USERNAME and SHOP_SMTP_PASSWORD in the Apache environment "
            . "and restart Apache. See email_setup.md."
        );
        return false;
    }

    if (
        !preg_match('/\A[a-z0-9.-]+\z/i', $host) ||
        !ctype_digit($portValue) ||
        (int) $portValue < 1 ||
        (int) $portValue > 65535 ||
        !filter_var($username, FILTER_VALIDATE_EMAIL) ||
        !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ||
        !filter_var($recipient, FILTER_VALIDATE_EMAIL) ||
        preg_match('/[\r\n]/', $subject)
    ) {
        error_log("ShopEase: SMTP settings are invalid.");
        return false;
    }

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = (int) $portValue;
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 30;
        $mailer->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mailer->setFrom($fromEmail, 'ShopEase');
        $mailer->addAddress($recipient);
        $mailer->Subject = $subject;
        if ($htmlBody === null) {
            $mailer->isHTML(false);
            $mailer->Body = $body;
        } else {
            $mailer->isHTML(true);
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $body;
        }
        $mailer->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $error) {
        error_log("ShopEase SMTP Error: " . $error->getMessage());
        return false;
    }
}
