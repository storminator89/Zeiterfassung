<?php

// Coarse CLI-only proof. No bootstrap, HTTP/auth, server, file DB or framework boot.
require_once __DIR__.'/../lib/api_pagination.php';
require_once __DIR__.'/../lib/attendance.php';
require_once __DIR__.'/../lib/api_booking_day.php';

function ensure_pagination(bool $condition, string $group): void
{
    if (!$condition) {
        throw new RuntimeException($group);
    }
}

$source = file_get_contents(__DIR__.'/../api.php');
ensure_pagination(preg_match('/^function getTimeEntries\(.*?^\}/ms', $source, $definition) === 1, 'native reader definition');
eval($definition[0]);

function native_pagination_read($connection, array $query): array
{
    $_GET = $query;
    http_response_code(200);
    ob_start();
    getTimeEntries($connection, 1);
    $body = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    return [http_response_code(), $body];
}

// One strict-input group: bounds are checked as digit strings before any cast.
ensure_pagination(api_pagination([]) === ['limit' => 100, 'offset' => null]
    && api_pagination(['limit' => '0001000', 'offset' => '0002147483647']) === ['limit' => 1000, 'offset' => 2147483647]
    && api_pagination(['limit' => 0, 'offset' => 0]) === ['limit' => 0, 'offset' => 0], 'defaults, leading zeros and inclusive integer bounds');
$noSql = new class {
    public function prepare(string $sql): never { throw new LogicException('invalid pagination reached SQL'); }
};
$invalid = ['', ' ', ' 1', '1 ', '+1', '-1', '1.5', '1.0', '1e2', "1\n", 'NaN', '9223372036854775808', str_repeat('9', 128), [], ['1'], null, true, false, 1.0, -1];
foreach (['limit' => 1000, 'offset' => 2147483647] as $field => $maximum) {
    foreach (array_merge($invalid, [(string) ($maximum + 1), $maximum + 1]) as $value) {
        $query = [$field => $value];
        try {
            api_pagination($query);
            throw new LogicException('invalid pagination accepted');
        } catch (InvalidArgumentException $error) {
            [$status, $body] = native_pagination_read($noSql, $query);
            ensure_pagination($status === 400 && $body === ['success' => false, 'message' => $error->getMessage()], 'strict scalar/range rejection is JSON 400 before SQL');
        }
    }
}
// PHP query parsing, shared with the dormant adapter, must not hide array input.
parse_str('limit%5B%5D=1&offset=0', $arrayQuery);
ensure_pagination(native_pagination_read($noSql, $arrayQuery)[0] === 400, 'parsed array query rejected');

// One read-only paging group with anonymous own/foreign rows in SQLite memory.
$db = PDO::connect('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE zeiterfassung(id INTEGER PRIMARY KEY,user_id INTEGER,startzeit TEXT,endzeit TEXT,pause INTEGER)');
$insert = $db->prepare('INSERT INTO zeiterfassung VALUES(?,?,?,NULL,0)');
$base = new DateTimeImmutable('2026-10-27T00:00:00+01:00');
for ($id = 1; $id <= 1002; $id++) {
    $insert->execute([$id, 1, $base->modify('+'.$id.' minutes')->format('c')]);
}
$insert->execute([2000, 2, '2026-10-28T00:00:00+01:00']);
$before = $db->query('SELECT * FROM zeiterfassung ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$pages = [
    [[], 100, null, 100, 1002],
    [['offset' => '5'], 100, 5, 100, 997],
    [['limit' => '1'], 1, null, 1, 1002],
    [['limit' => '1000', 'offset' => '1'], 1000, 1, 1000, 1001],
    [['limit' => '0', 'offset' => '0'], 0, 0, 0, null],
    [['offset' => '2147483647'], 100, 2147483647, 0, null],
];
foreach ($pages as [$query, $limit, $offset, $count, $first]) {
    [$status, $body] = native_pagination_read($db, $query + ['start_date' => '2026-10-27', 'end_date' => '2026-10-27', 'user_id' => 2]);
    ensure_pagination($status === 200 && $body['success'] === true
        && $body['meta'] === ['total' => 1002, 'filtered' => $count, 'limit' => $limit, 'offset' => $offset]
        && count($body['data']) === $count && ($body['data'][0]['id'] ?? null) === $first
        && !in_array(2, array_column($body['data'], 'user_id'), true), 'bounded paging, own/date scope, order and metadata');
}
ensure_pagination($before === $db->query('SELECT * FROM zeiterfassung ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'fixture data unchanged');

echo json_encode([
    'passed' => true,
    'groups' => ['strict scalar/range validation and JSON 400 before SQL', 'bounded default/offset-only/explicit limit=1/max/zero pages with own/date scope and unchanged fixture'],
    'runtime' => PHP_VERSION,
    'sqlite' => $db->query('SELECT sqlite_version()')->fetchColumn(),
    'memory_only' => true,
    'http_auth_framework_boot_ui_real_database_deployment_tested' => false,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
