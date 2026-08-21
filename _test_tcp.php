<?php
$hosts = ['192.168.1.76', '222.252.156.2'];
foreach ($hosts as $host) {
    echo "=== HOST $host ===\n";
    foreach ([6379, 8086, 1883, 9002] as $port) {
        $sock = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 3);
        if ($sock) { echo "  PORT $port: OPEN\n"; fclose($sock); }
        else { echo "  PORT $port: FAIL ($errstr)\n"; }
    }
}
$ping = shell_exec('ping -n 2 192.168.1.76 2>&1');
echo "--- PING LAN ---\n" . (str_contains($ping, 'TTL') ? "PING OK\n" : substr($ping, 0, 200));
