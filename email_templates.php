<?php

function buildOrderConfirmationEmail(
    int $orderId,
    string $customerName,
    string $customerEmail,
    array $items,
    float $total
): string {
    $escape = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $itemRows = '';
    foreach ($items as $item) {
        $name = $escape((string) $item['name']);
        $quantity = (int) $item['quantity'];
        $price = (float) $item['price'];
        $image = isset($item['image']) && is_string($item['image'])
            ? filter_var($item['image'], FILTER_VALIDATE_URL)
            : false;
        if (
            $image &&
            !in_array(
                strtolower((string) parse_url($image, PHP_URL_SCHEME)),
                ['http', 'https'],
                true
            )
        ) {
            $image = false;
        }

        $thumbnail = $image
            ? '<img src="' . $escape($image) . '" width="64" height="64" alt="" '
                . 'style="display:block;width:64px;height:64px;object-fit:cover;border-radius:8px;">'
            : '<span style="display:block;width:64px;height:64px;background-color:#f1f3f5;'
                . 'border-radius:8px;"></span>';

        $itemRows .= '<tr><td style="padding:12px;background-color:#f7f8f6;border-radius:8px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td width="76" valign="middle">' . $thumbnail . '</td>'
            . '<td valign="middle" style="font-family:Arial,sans-serif;color:#14213d;font-size:15px;'
            . 'line-height:22px;"><strong>' . $name . '</strong><br>'
            . '<span style="color:#6c757d;font-size:13px;">Quantity: ' . $quantity . '</span></td>'
            . '<td align="right" valign="middle" style="font-family:Arial,sans-serif;color:#14213d;'
            . 'font-size:14px;white-space:nowrap;">Rs. '
            . number_format($price * $quantity, 2) . '</td></tr></table></td></tr>'
            . '<tr><td height="10" style="font-size:0;line-height:10px;">&nbsp;</td></tr>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
        . '<title>Your ShopEase order is confirmed</title></head>'
        . '<body style="margin:0;padding:24px 12px;background-color:#e8efdf;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
        . 'style="width:100%;max-width:600px;background-color:#ffffff;border-radius:12px;'
        . 'overflow:hidden;">'
        . '<tr><td align="center" style="padding:28px 24px 12px;font-family:Arial,sans-serif;'
        . 'color:#f18a00;font-size:22px;font-weight:bold;">'
        . '<span style="display:inline-block;width:22px;height:22px;line-height:22px;'
        . 'margin-right:6px;border-radius:50%;background-color:#f18a00;color:#ffffff;'
        . 'font-size:15px;">S</span> ShopEase</td></tr>'
        . '<tr><td align="center" style="padding:12px 28px 24px;font-family:Arial,sans-serif;'
        . 'color:#14213d;">'
        . '<div style="font-size:36px;line-height:42px;font-weight:bold;">Your order is confirmed</div>'
        . '<p style="margin:12px 0 0;color:#6c757d;font-size:15px;line-height:24px;">'
        . 'Thanks for shopping with us, ' . $escape($customerName) . '.</p>'
        . '<p style="margin:8px 0 0;color:#6c757d;font-size:14px;">Order #'
        . $orderId . '</p></td></tr>'
        . '<tr><td style="padding:0 24px 24px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td style="padding:0 0 12px;font-family:Arial,sans-serif;color:#14213d;'
        . 'font-size:20px;font-weight:bold;">Order summary</td></tr>'
        . $itemRows
        . '<tr><td style="padding-top:12px;border-top:1px solid #e9ecef;'
        . 'font-family:Arial,sans-serif;color:#6c757d;font-size:14px;line-height:28px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td>Subtotal</td><td align="right">Rs. ' . number_format($total, 2) . '</td></tr>'
        . '<tr><td style="padding-top:6px;color:#14213d;font-size:17px;font-weight:bold;">Total</td>'
        . '<td align="right" style="padding-top:6px;color:#14213d;font-size:17px;font-weight:bold;">Rs. '
        . number_format($total, 2) . '</td></tr></table></td></tr></table></td></tr>'
        . '<tr><td style="padding:20px 24px;background-color:#f7f8f6;">'
        . '<div style="font-family:Arial,sans-serif;color:#14213d;font-size:19px;font-weight:bold;">'
        . 'Customer information</div>'
        . '<p style="margin:10px 0 4px;font-family:Arial,sans-serif;color:#6c757d;font-size:12px;">'
        . 'Name</p><p style="margin:0;font-family:Arial,sans-serif;color:#14213d;font-size:14px;">'
        . $escape($customerName) . '</p>'
        . '<p style="margin:10px 0 4px;font-family:Arial,sans-serif;color:#6c757d;font-size:12px;">'
        . 'Email</p><p style="margin:0;font-family:Arial,sans-serif;color:#14213d;font-size:14px;">'
        . $escape($customerEmail) . '</p></td></tr>'
        . '<tr><td align="center" style="padding:22px 24px;font-family:Arial,sans-serif;'
        . 'color:#6c757d;font-size:13px;line-height:20px;">Need help with your order? '
        . 'Contact the ShopEase team.<br>Thank you for choosing ShopEase.</td></tr>'
        . '</table></td></tr></table></body></html>';
}
