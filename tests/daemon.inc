<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(400);

    exit;
}

if ($_SERVER['argc'] < 2) {
    fwrite(STDERR, sprintf("Usage: %s <socket_to_bind>\n", $_SERVER['argv'][0]));
    exit(1);
}

$server = @stream_socket_server($_SERVER['argv'][1]);

for (;;) {
    $socket = stream_socket_accept($server, 5);
    if (!$socket) {
        fwrite(STDERR, "Unable to accept connection\n");
        exit(1);
    }

    $response = '';
    do {
        $response .= fread($socket, 65355);
    } while (!feof($socket));

    fclose($socket);

    $data = json_decode($response, true);

    // Ignore everything except traces.
    if (isset($data['type']) && $data['type'] !== 't2') {
        continue;
    }

    echo json_encode($data['payload']);

    fclose($server);
    exit(0);
}
