<?php

$testRecipient = getenv('SHOP_SMTP_TEST_RECIPIENT') ?: '';

if (!filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException(
        'Set SHOP_SMTP_TEST_RECIPIENT to a valid email address before testing.'
    );
}

return $testRecipient;
