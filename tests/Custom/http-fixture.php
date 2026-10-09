<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Local-only asynchronous HTTP fixture. No external API requests or credentials.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { fwrite(STDERR, $error); exit(1); }
stream_set_blocking($server, false);
fwrite(STDOUT, stream_socket_get_name($server, false) . "\n");
fflush(STDOUT);
$clients = array();
$deadline = microtime(true) + 40;
while (microtime(true) < $deadline) {
    $read = array($server);
    foreach ($clients as $client) {
        if (!isset($client['due'])) $read[] = $client['socket'];
    }
    $write = $except = null;
    stream_select($read, $write, $except, 0, 10000);
    foreach ($read as $socket) {
        if ($socket === $server) {
            $connection = stream_socket_accept($server, 0);
            if ($connection) {
                stream_set_blocking($connection, false);
                $clients[(int)$connection] = array('socket' => $connection, 'buffer' => '');
            }
            continue;
        }
        $id = (int)$socket;
        $chunk = fread($socket, 65536);
        if ($chunk === '' && feof($socket)) { fclose($socket); unset($clients[$id]); continue; }
        $clients[$id]['buffer'] .= $chunk;
        $parts = explode("\r\n\r\n", $clients[$id]['buffer'], 2);
        if (count($parts) !== 2) continue;
        preg_match('/Content-Length:\s*(\d+)/i', $parts[0], $length);
        if (strlen($parts[1]) < (int)($length[1] ?? 0)) continue;
        $payload = json_decode($parts[1], true) ?: array();
        $clients[$id]['due'] = microtime(true) + ($payload['delay'] ?? 0.3);
        $clients[$id]['payload'] = $payload;
        $clients[$id]['headers'] = $parts[0];
        $clients[$id]['active'] = count($clients);
    }
    foreach ($clients as $id => $client) {
        if (!isset($client['due']) || $client['due'] > microtime(true)) continue;
        $payload = $client['payload'];
        $status = $payload['status'] ?? 200;
        $body = json_encode($payload['response'] ?? array('id' => $payload['id'] ?? null, 'active' => $client['active'], 'headers' => $client['headers']));
        fwrite($client['socket'], "HTTP/1.1 $status OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
        fclose($client['socket']);
        unset($clients[$id]);
    }
}
