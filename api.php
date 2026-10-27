<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Including configuration settings
require_once 'config.php';
header('Content-Type: application/json; charset=UTF-8');

// Connect to the database
// Use the configured connection, including audit functions.
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * @OA\Info(title="Zeiterfassung API", version="1.0")
 */

require_once __DIR__ . '/lib/api_tokens.php';

function generateJWT($header, $payload, $secret) {
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('SHA256', "$headerEncoded.$payloadEncoded", $secret, true);
    $signatureEncoded = base64UrlEncode($signature);

    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

/**
 * @OA\Post(
 *     path="/api.php/login",
 *     summary="User login",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="username", type="string"),
 *             @OA\Property(property="password", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Successful login"),
 *     @OA\Response(response=401, description="Invalid credentials")
 * )
 */
function login($conn) {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    if (!is_array($input) || !is_string($input['username'] ?? null) || !is_string($input['password'] ?? null)) { abort_request(400, 'Username and password required.'); }
    $username = $input['username'];
    $password = $input['password'];
    throttle_login($conn, $username);

    $stmt = $conn->prepare("SELECT id, password, auth_source FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['auth_source'] === 'local' && $password !== 'defaultpassword' && password_verify($password, $user['password'])) {
        $user_id = $user['id'];

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $payload = [
            'iss' => "localhost",
            'aud' => "localhost",
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour expiration
            'user_id' => (int)$user_id,
            'ver' => hash('sha256', $user['password'])
        ];

        $jwt = generateJWT($header, $payload, JWT_SECRET);

        echo json_encode(['success' => true, 'token' => $jwt]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

function validateToken() {
    global $conn;
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if (!preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/D', $headers['authorization'] ?? '', $matches)) {
        abort_request(401, 'Invalid bearer token.');
    }
    $payload = validateJWT($matches[1], JWT_SECRET);
    if (!$payload) { abort_request(401, 'Invalid or expired token.'); }
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$payload['user_id']]);
    $password = $stmt->fetchColumn();
    if (!$password || !hash_equals(hash('sha256', $password), $payload['ver'])) {
        abort_request(401, 'Account deleted or credentials changed.');
    }
    return $payload;
}

/**
 * @OA\Post(
 *     path="/api.php/workentry",
 *     summary="Create new work entry",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="startzeit", type="string"),
 *             @OA\Property(property="endzeit", type="string"),
 *             @OA\Property(property="pause", type="integer"),
 *             @OA\Property(property="beschreibung", type="string"),
 *             @OA\Property(property="standort", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Work entry created"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=500, description="Internal server error"),
 *     @OA\SecurityScheme(
 *         securityScheme="bearerAuth",
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="JWT"
 *     )
 * )
 */
function createNewWorkEntry($conn, $user_id) {
    $data=json_decode(file_get_contents('php://input'),true);
    if (!is_array($data)) { abort_request(400,'Invalid request.'); }
    if (!empty($data['endzeit']) || !empty($data['pause'])) { abort_request(409,'Use a correction request for historical entries.'); }
    attendance_json(fn()=>attendance_transition($conn,(int)$user_id,'api:'.$user_id,$data['event_id']??bin2hex(random_bytes(16)),'start'));
}

/**
 * @OA\Post(
 *     path="/api.php/setendzeit",
 *     summary="Set end time of a specific work entry",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer")
 *         )
 *     ),
 *     @OA\Response(response=200, description="End time updated"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=500, description="Internal server error")
 * )
 */
function setEndzeit($conn, $input, $user_id) {
    attendance_json(fn()=>attendance_transition($conn,(int)$user_id,'api:'.$user_id,$input['event_id']??bin2hex(random_bytes(16)),'end',($input['confirm_end_pause']??false)===true,(int)($input['id']??0)));
}

/**
 * @OA\Get(
 *     path="/api.php/users",
 *     summary="Get the authenticated user",
 *     @OA\Response(response=200, description="List of users"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=500, description="Internal server error")
 * )
 */
function getUsers($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

/**
 * @OA\Get(
 *     path="/api.php/timeentries",
 *     summary="Get filtered time entries for the authenticated user",
 *     @OA\Parameter(
 *         name="start_date",
 *         in="query",
 *         description="Start date (YYYY-MM-DD)",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="end_date",
 *         in="query",
 *         description="End date (YYYY-MM-DD)",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="limit",
 *         in="query",
 *         description="Maximum number of entries to return",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="offset",
 *         in="query",
 *         description="Number of entries to skip",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="List of time entries"),
 *     @OA\Response(response=400, description="Invalid parameters"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=500, description="Internal server error")
 * )
 */
function getTimeEntries($conn, $user_id) {
    try {
        $params = [];
        $whereConditions = ["user_id = :user_id"];
        $params[':user_id'] = $user_id;

        // Add date filters if provided
        if (isset($_GET['start_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD']);
                return;
            }
            $whereConditions[] = "date(startzeit) >= :start_date";
            $params[':start_date'] = $_GET['start_date'];
        }

        if (isset($_GET['end_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid end_date format. Use YYYY-MM-DD']);
                return;
            }
            $whereConditions[] = "date(startzeit) <= :end_date";
            $params[':end_date'] = $_GET['end_date'];
        }

        // Build the base query
        $query = "SELECT * FROM zeiterfassung WHERE " . implode(' AND ', $whereConditions);

        // Add ORDER BY
        $query .= " ORDER BY startzeit DESC";

        // Add LIMIT and OFFSET if provided
        if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
            $limit = (int)$_GET['limit'];
            $query .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        if (isset($_GET['offset']) && is_numeric($_GET['offset'])) {
            if (!array_key_exists(':limit', $params)) {
                // SQLite requires LIMIT before OFFSET; -1 leaves the page unbounded.
                $query .= " LIMIT -1";
            }
            $offset = (int)$_GET['offset'];
            $query .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        // Get total count for pagination
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM zeiterfassung WHERE " . implode(' AND ', $whereConditions));
        foreach ($params as $key => $value) {
            if (!in_array($key, [':limit', ':offset'])) {
                $countStmt->bindValue($key, $value);
            }
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();

        // Execute main query
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $entries,
            'meta' => [
                'total' => $totalCount,
                'filtered' => count($entries),
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null,
                'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : null
            ]
        ]);
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

/**
 * @OA\Delete(
 *     path="/api.php/timeentry/{id}",
 *     summary="Delete a specific time entry",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Time entry deleted"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Not allowed to delete"),
 *     @OA\Response(response=404, description="Entry not found"),
 *     @OA\Response(response=500, description="Internal server error")
 * )
 */
function deleteTimeEntry($conn, $user_id, $entry_id) {
    abort_request(409,'Use an approved correction request to cancel a record.');
}

// Parse the URL path and route the request to the appropriate function
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Adjust the base path to match your specific setup
$basePath = '/api.php';

if ($method === 'POST') {
    switch ($uri) {
        case $basePath . '/login':
            login($conn);
            break;
        case $basePath . '/attendance':
            $tokenData = validateToken();
            $input = json_decode(file_get_contents('php://input'), true);
            attendance_json(fn() => attendance_transition($conn, (int)$tokenData['user_id'], 'api:'.$tokenData['user_id'], $input['event_id'] ?? '', $input['action'] ?? '', ($input['confirm_end_pause'] ?? false) === true));
        case $basePath . '/workentry':
            $tokenData = validateToken();
            createNewWorkEntry($conn, $tokenData['user_id']);
            break;
        case $basePath . '/setendzeit':
            $tokenData = validateToken();
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, TRUE);
            setEndzeit($conn, $input, $tokenData['user_id']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    }
} elseif ($method === 'GET') {
    switch ($uri) {
        case $basePath . '/attendance':
            $tokenData = validateToken();
            attendance_json(function () use ($conn, $tokenData) {
                $conn->beginTransaction();
                try {
                    $status = ['success' => true] + attendance_status($conn, (int)$tokenData['user_id']);
                    $conn->commit();
                    return $status;
                } catch (Throwable $error) {
                    if ($conn->inTransaction()) { $conn->rollBack(); }
                    throw $error;
                }
            });
        case $basePath . '/users':
            $tokenData = validateToken();
            getUsers($conn, $tokenData['user_id']);
            break;
        case $basePath . '/timeentries':
            $tokenData = validateToken();
            getTimeEntries($conn, $tokenData['user_id']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    }
} elseif ($method === 'DELETE') {
    if (preg_match('/^' . str_replace('/', '\/', $basePath) . '\/timeentry\/(\d+)$/', $uri, $matches)) {
        $tokenData = validateToken();
        $entry_id = $matches[1];
        deleteTimeEntry($conn, $tokenData['user_id'], $entry_id);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
}
?>
