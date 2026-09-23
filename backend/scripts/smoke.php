<?php

// Runs through the actual HTTP API. Adds one demo task and one offer; repeatable.
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000/api', '/');

function callApi(string $method, string $path, ?array $body = null, ?string $role = null, int $expected = 200): array
{
    global $base;
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($role !== null) {
        $headers[] = 'X-Demo-Role: '.$role;
        $headers[] = 'X-Demo-Id: 1';
    }
    $context = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        'ignore_errors' => true, 'timeout' => 15,
    ]]);
    $result = file_get_contents($base.$path, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/HTTP\/\S+\s+(\d+)/', $statusLine, $matches);
    if ($result === false || (int) ($matches[1] ?? 0) !== $expected) {
        throw new RuntimeException("{$method} {$path}: {$statusLine}\n".$result);
    }
    echo "{$method} {$path}: {$expected}".PHP_EOL;

    return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
}

function example(string $name): array
{
    return json_decode(file_get_contents(__DIR__.'/../docs/examples/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
}

try {
    callApi('GET', '/health');
    callApi('GET', '/demo/profiles');
    $questions = callApi('POST', '/ai/questions', example('ai-questions'), 'customer');
    if (count($questions['data']['questions']) < 3) {
        throw new RuntimeException('Expected at least three questions.');
    }
    $task = callApi('POST', '/tasks', example('task-create'), 'customer', 201)['data'];
    if ($task['score'] !== 20 || $task['status'] !== 'draft') {
        throw new RuntimeException('Expected a draft with 20 points.');
    }
    $id = $task['id'];
    $task = callApi('PUT', "/tasks/{$id}", example('task-update'), 'customer')['data'];
    if ($task['score'] !== 100) {
        throw new RuntimeException('Expected 100 points after confirmation.');
    }
    callApi('POST', "/tasks/{$id}/publish", example('publish'), 'customer');
    $catalog = callApi('GET', '/tasks')['data'];
    if (! in_array($id, array_column($catalog, 'id'), true)) {
        throw new RuntimeException('Published task missing from catalog.');
    }
    $offer = callApi('POST', "/tasks/{$id}/offers", example('offer-create'), 'team', 201)['data'];
    callApi('GET', "/tasks/{$id}/offers", null, 'customer');
    $offerId = $offer['id'];
    callApi('PATCH', "/offers/{$offerId}/decision", example('decision'), 'customer');
    $saved = callApi('GET', '/my/offers', null, 'team')['data'];
    $match = array_values(array_filter($saved, fn ($item) => $item['id'] === $offerId));
    if (($match[0]['decision'] ?? '') !== 'selected') {
        throw new RuntimeException('Decision did not persist.');
    }
    echo "PASS: task {$id}, offer {$offerId}; score 20 -> 100; selected.".PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
