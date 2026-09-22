<?php
/**
 * Get currently online player names from the Minecraft server.
 * Returns lowercase name => true
 */
function getOnlinePlayerMap(): array {
    static $cache = null;
    static $cacheTime = 0;
    if ($cache !== null && (time() - $cacheTime) < 5) {
        return $cache;
    }

    $online = [];

    // 1) Minecraft Server List Ping (works for local 127.0.0.1)
    if (defined('MC_SERVER_IP') && defined('MC_SERVER_PORT')) {
        $players = mcListPingPlayers(MC_SERVER_IP, (int)MC_SERVER_PORT);
        foreach ($players as $name) {
            $online[strtolower($name)] = true;
        }
    }

    // 2) Public API fallback (only if IP is not local)
    if (empty($online) && defined('MC_SERVER_IP') && !in_array(MC_SERVER_IP, ['127.0.0.1', 'localhost'], true)) {
        $url = 'https://api.mcsrvstat.us/3/' . rawurlencode(MC_SERVER_IP) . ':' . (int)MC_SERVER_PORT;
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json) {
            $data = json_decode($json, true);
            if (!empty($data['players']['list']) && is_array($data['players']['list'])) {
                foreach ($data['players']['list'] as $name) {
                    $online[strtolower($name)] = true;
                }
            }
        }
    }

    $cache = $online;
    $cacheTime = time();
    return $online;
}

function mcListPingPlayers(string $host, int $port, float $timeout = 2.0): array {
    $players = [];
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$fp) return $players;

    stream_set_timeout($fp, (int)$timeout, (int)(($timeout - (int)$timeout) * 1e6));

    // Handshake + status request (Server List Ping)
    $protocol = 760; // 1.19+ compatible-ish; server usually still replies
    $data = mcPackVarInt(0) // packet id handshake
          . mcPackVarInt($protocol)
          . mcPackString($host)
          . pack('n', $port)
          . mcPackVarInt(1); // next state: status

    $packet = mcPackVarInt(strlen($data)) . $data;
    fwrite($fp, $packet);

    // Status request
    fwrite($fp, mcPackVarInt(1) . mcPackVarInt(0));

    // Read response length
    $len = mcReadVarInt($fp);
    if ($len <= 0 || $len > 65536) {
        fclose($fp);
        return $players;
    }

    $packetId = mcReadVarInt($fp);
    $jsonLen = mcReadVarInt($fp);
    $json = '';
    while (strlen($json) < $jsonLen) {
        $chunk = fread($fp, $jsonLen - strlen($json));
        if ($chunk === false || $chunk === '') break;
        $json .= $chunk;
    }
    fclose($fp);

    $data = json_decode($json, true);
    if (!empty($data['players']['sample']) && is_array($data['players']['sample'])) {
        foreach ($data['players']['sample'] as $p) {
            if (!empty($p['name'])) {
                $players[] = $p['name'];
            }
        }
    }
    return $players;
}

function mcPackVarInt(int $value): string {
    $out = '';
    while (true) {
        if (($value & ~0x7F) === 0) {
            $out .= chr($value);
            return $out;
        }
        $out .= chr(($value & 0x7F) | 0x80);
        $value = $value >> 7;
    }
}

function mcPackString(string $str): string {
    return mcPackVarInt(strlen($str)) . $str;
}

function mcReadVarInt($fp): int {
    $numRead = 0;
    $result = 0;
    do {
        $b = fread($fp, 1);
        if ($b === false || $b === '') return -1;
        $byte = ord($b);
        $value = $byte & 0x7F;
        $result |= $value << (7 * $numRead);
        $numRead++;
        if ($numRead > 5) return -1;
    } while (($byte & 0x80) !== 0);
    return $result;
}
