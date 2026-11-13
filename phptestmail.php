<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This mail test can only be run from the command line.\n");
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'email_sender.php';

try {
    $recipient = require __DIR__ . DIRECTORY_SEPARATOR . 'testemailconfig.php';
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

$sent = sendShopEmail(
    $recipient,
    'ShopEase SMTP test',
    "This is a test email from ShopEase.\n\nIf you received it, SMTP is configured correctly."
);

if (!$sent) {
    fwrite(STDERR, "Test email could not be sent. Check the PHP error log for details.\n");
    exit(1);
}

fwrite(STDOUT, "Test email sent to " . $recipient . "." . PHP_EOL);
