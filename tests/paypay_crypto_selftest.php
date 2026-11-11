<?php

/**
 * Standalone self-test for PayPayCrypto (no Laravel needed).
 *   php tests/paypay_crypto_selftest.php
 * Cross-checks the PHP code against the openssl CLI so we don't only test it against itself.
 */

declare(strict_types=1);

require __DIR__ . '/../app/Services/PayPay/PayPayCrypto.php';

use App\Services\PayPay\PayPayCrypto;

$fail = 0;
function check(string $name, bool $ok): void
{
    global $fail;
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
}
function sh(string $cmd): array
{
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

$dir = sys_get_temp_dir() . '/paypay_selftest_' . bin2hex(random_bytes(4));
mkdir($dir, 0700);

// 1) keys exactly as the PayPay docs suggest (openssl, 1024 bits)
sh("openssl genrsa -out $dir/private.pem 1024");
sh("openssl rsa -in $dir/private.pem -pubout -out $dir/public.pem");
sh("openssl rsa -in $dir/private.pem -traditional -out $dir/private_pkcs1.pem");
check('generated 1024-bit keys', is_file("$dir/private.pem") && is_file("$dir/public.pem"));

$privPem = file_get_contents("$dir/private.pem");
$pubPem = file_get_contents("$dir/public.pem");
$strip = fn(string $pem) => preg_replace('/-----[^-]+-----|\s+/', '', $pem);

echo "key loading\n";
check('private PEM (PKCS#8)', PayPayCrypto::loadPrivateKey($privPem) !== null);
check('private PEM (PKCS#1)', PayPayCrypto::loadPrivateKey(file_get_contents("$dir/private_pkcs1.pem")) !== null);
check('private bare base64', PayPayCrypto::loadPrivateKey($strip($privPem)) !== null);
check('private PEM with literal \\n (.env style)', PayPayCrypto::loadPrivateKey(str_replace("\n", '\n', $privPem)) !== null);
check('public PEM', PayPayCrypto::loadPublicKey($pubPem) !== null);
check('public bare base64', PayPayCrypto::loadPublicKey($strip($pubPem)) !== null);

$priv = PayPayCrypto::loadPrivateKey($privPem);
$pub = PayPayCrypto::loadPublicKey($pubPem);

echo "biz_content encryption (multi-block, multibyte)\n";
$biz = json_encode([
    'cashier_type' => 'SDK', 'payer_ip' => '123.25.68.9', 'sale_product_code' => '050200001', 'timeout_express' => '2h',
    'trade_info' => ['currency' => 'AOA', 'out_trade_no' => 'MT261103120000ABCD1234', 'payee_identity' => '200001835716',
        'payee_identity_type' => '1', 'price' => '2500.00', 'quantity' => '1', 'subject' => 'Bilhete Festival de Verão – Luanda ção', 'total_amount' => '2500.00'],
    'pay_method' => ['pay_product_code' => '31', 'amount' => '2500.00', 'bank_code' => 'MUL', 'phone_num' => '987654321'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
check('payload spans more than one 117-byte block (' . strlen($biz) . ' bytes)', strlen($biz) > 117);

$enc = PayPayCrypto::encryptWithPrivateKey($biz, $priv);
$raw = base64_decode($enc, true);
check('ciphertext is a multiple of 128 bytes', $raw !== false && strlen($raw) % 128 === 0 && strlen($raw) === 128 * (int) ceil(strlen($biz) / 117));
check('round trip (private encrypt -> public decrypt)', PayPayCrypto::decryptWithPublicKey($enc, $pub) === $biz);

// independent check: openssl CLI recovers the first 117-byte block from the first 128 ciphertext bytes
file_put_contents("$dir/block1.bin", substr($raw, 0, 128));
[$code, $recovered] = sh("openssl pkeyutl -verifyrecover -pubin -inkey $dir/public.pem -in $dir/block1.bin");
check('openssl CLI recovers block 1 (cross-check)', $code === 0 && $recovered === substr($biz, 0, 117));

echo "signing string\n";
$params = [
    'charset' => 'UTF-8', 'biz_content' => 'GkTO5wRUFTij4GPniUQChP...', 'partner_id' => '200001835716',
    'service' => 'instant_trade', 'request_no' => '5298b3d713b6430c95729f9e54c967dc', 'format' => 'JSON',
    'sign' => 'ignored', 'language' => 'pt', 'sign_type' => 'RSA', 'version' => '1.0', 'timestamp' => '2022-01-20 19:44:51',
    'empty' => '', 'nothing' => null,
];
$expected = 'biz_content=GkTO5wRUFTij4GPniUQChP...&charset=UTF-8&format=JSON&language=pt&partner_id=200001835716'
    . '&request_no=5298b3d713b6430c95729f9e54c967dc&service=instant_trade&timestamp=2022-01-20 19:44:51&version=1.0';
check('matches the docs example (sorted, no sign/sign_type/empties)', PayPayCrypto::signingString($params) === $expected);

echo "signature\n";
$text = PayPayCrypto::signingString($params);
$sig = PayPayCrypto::sign($text, $priv);
check('own verify', PayPayCrypto::verify($text, $sig, $pub));
check('tampered text rejected', !PayPayCrypto::verify($text . 'x', $sig, $pub));
check('garbage signature rejected', !PayPayCrypto::verify($text, 'not-base64!!', $pub));
file_put_contents("$dir/data.txt", $text);
file_put_contents("$dir/sig.bin", base64_decode($sig));
[$code, $out] = sh("openssl dgst -sha1 -verify $dir/public.pem -signature $dir/sig.bin $dir/data.txt");
check('openssl CLI verifies our SHA1withRSA signature (cross-check)', $code === 0 && str_contains($out, 'Verified OK'));

echo "webhook (PayPay signs with ITS private key, we verify with its public key)\n";
$notif = [
    'gmt_create' => '1641546460908', 'amount' => '2500.00', 'gmt_payment' => '1641546465878', 'notify_time' => '20220120111833',
    'payerIdentity' => '924***323', 'role' => 'partner', 'input_charset' => 'UTF-8', 'notify_create' => '1642673913264',
    'notify_id' => 'a146a25b97b14399a20acfe24bfccaaa', 'notify_type' => 'trade_status_sync', 'payeeIdentity' => 'l******n@zsaipay.com',
    'out_trade_no' => 'MT261103120000ABCD1234', 'inner_trade_no' => '101164154646089866469', 'gmt_close' => '1642673910000',
    'sign_type' => 'RSA', 'status' => 'TRADE_FINISHED', 'failReason' => '',
];
$notif['sign'] = PayPayCrypto::sign(PayPayCrypto::signingString($notif), $priv); // test key plays the PayPay role
$body = http_build_query($notif);
parse_str($body, $received); // what the controller does with the raw body
check('valid notification verifies', PayPayCrypto::verify(PayPayCrypto::signingString($received), $received['sign'], $pub));
$received['amount'] = '1.00';
check('changed amount is rejected', !PayPayCrypto::verify(PayPayCrypto::signingString($received), $received['sign'], $pub));

array_map('unlink', glob("$dir/*"));
rmdir($dir);

echo $fail === 0 ? "\nALL PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
