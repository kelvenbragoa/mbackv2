<?php

return [
    // Provided by PayPay's sales team after the contract is signed.
    'api_url' => env('PAYPAY_API_URL'),
    'partner_id' => env('PAYPAY_PARTNER_ID'),
    'sale_product_code' => env('PAYPAY_SALE_PRODUCT_CODE'),

    // Your own RSA key pair (1024 bits, required by PayPay). Prefer files over env strings.
    // Only the PUBLIC key is uploaded to the PayPay portal. The private key never leaves your server.
    'private_key_path' => env('PAYPAY_PRIVATE_KEY_PATH', storage_path('paypay/private.pem')),
    'private_key' => env('PAYPAY_PRIVATE_KEY'),

    // PayPay's official public key (downloaded from the portal's API settings), used to verify webhooks.
    'public_key_path' => env('PAYPAY_PUBLIC_KEY_PATH', storage_path('paypay/paypay_public.pem')),
    'public_key' => env('PAYPAY_PUBLIC_KEY'),

    'language' => 'pt',
    'timezone' => 'Africa/Luanda', // timestamps must be Angola time (GMT+1)

    // How long an unpaid order stays open: m / h / d, no decimals. Docs say 40m-7d, default 2h.
    'timeout_express' => env('PAYPAY_TIMEOUT_EXPRESS', '2h'),

    // Must be whitelisted by PayPay if set. Leave null to skip the redirect.
    'return_url' => env('PAYPAY_RETURN_URL'),

    'http_timeout' => 15,
];
