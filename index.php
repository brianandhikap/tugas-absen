<?php
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');

usleep(rand(200000, 800000));

$a = base64_decode('PGh0bWwgbGFuZz0iZW4iPgo8aGVhZD4KPG1ldGEgY2hhcnNldD0idXRmLTgiPgo8dGl0bGU+RXJyb3I8L3RpdGxlPgo8L2hlYWQ+Cjxib2R5Pgo8cHJlPkNhbm5vdCBHRVQgLzwvcHJlPgo8L2JvZHk+CjwvaHRtbD4=');

function confuse($n) {
    $trash = '';
    for ($i = 0; $i < $n; $i++) {
        $trash .= chr(rand(65, 90));
    }
    return sha1($trash);
}

file_put_contents(__DIR__ . '/honeylog.log', date('Y-m-d H:i:s') . " - IP: " . $_SERVER['REMOTE_ADDR'] . " - UA: " . $_SERVER['HTTP_USER_AGENT'] . PHP_EOL, FILE_APPEND);

echo $a;

for ($i = 0; $i < 3; $i++) {
    confuse(rand(10, 100));
}
