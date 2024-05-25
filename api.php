<?php
// Including configuration settings
include 'config.php';

// Connect to the database
$conn = new PDO("sqlite:$database");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * @OA\Info(title="Zeiterfassung API", version="1.0")
 */

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateJWT($header, $payload, $secret) {
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('SHA256', "$headerEncoded.$payloadEncoded", $secret, true);
    $signatureEncoded = base64UrlEncode($signature);

    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

/**
 * @OA\Post(
 *     path="/api/login",
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

    $username = $input['username'];
    $password = $input['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $user_id = $user['id'];

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $payload = [
            'iss' => "localhost",
            'aud' => "localhost",
            'iat' => time(),
            'exp' => time() + (365 * 24 * 60 * 60), // 1 year expiration
            'user_id' => $user_id
        ];

        $jwt = generateJWT($header, $payload, 'your_secret_key');

        echo json_encode(['success' => true, 'token' => $jwt]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function validateJWT($jwt, $secret) {
    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) {
        return false;
    }

    $headerEncoded = $tokenParts[0];
    $payloadEncoded = $tokenParts[1];
    $signatureProvided = $tokenParts[2];

    $header = json_decode(base64UrlDecode($headerEncoded), true);
    $payload = json_decode(base64UrlDecode($payloadEncoded), true);

    $signature = hash_hmac('SHA256', "$headerEncoded.$payloadEncoded", $secret, true);
    $signatureEncoded = base64UrlEncode($signature);

    if ($signatureEncoded !== $signatureProvided) {
        return false;
    }

    if ($payload['exp'] < time()) {
        return false;
    }

    return $payload;
}

function validateToken() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
        exit();
    }

    $authHeader = $headers['Authorization'];
    list($jwt) = sscanf($authHeader, 'Bearer %s');

    if (!$jwt) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token format']);
        exit();
    }

    $secret = 'your_secret_key';
    $payload = validateJWT($jwt, $secret);

    if (!$payload) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit();
    }

    return $payload;
}

/**
 * @OA\Post(
 *     path="/api/workentry",
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
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $startzeit_raw = $input['startzeit'];
    $endzeit_raw = isset($input['endzeit']) ? $input['endzeit'] : null;
    $pause = $input['pause'];
    $beschreibung = $input['beschreibung'];
    $standort = $input['standort'];

    $startzeit_iso = date('Y-m-d\TH:i:s', strtotime($startzeit_raw));
    $endzeit_iso = !is_null($endzeit_raw) ? date('Y-m-d\TH:i:s', strtotime($endzeit_raw)) : null;

    try {
        $stmt = $conn->prepare("INSERT INTO zeiterfassung (startzeit, endzeit, pause, beschreibung, standort, user_id) VALUES (:startzeit, :endzeit, :pause, :beschreibung, :standort, :user_id)");
        $stmt->bindParam(':startzeit', $startzeit_iso);
        $stmt->bindParam(':endzeit', $endzeit_iso);
        $stmt->bindParam(':pause', $pause);
        $stmt->bindParam(':beschreibung', $beschreibung);
        $stmt->bindParam(':standort', $standort);
        $stmt->bindParam(':user_id', $user_id);

        $stmt->execute();
        $lastId = $conn->lastInsertId();

        $stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':id', $lastId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $newEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'message' => 'Eintrag erstellt', 'data' => $newEntry]);
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

/**
 * @OA\Post(
 *     path="/api/setendzeit",
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
    $id = $input['id'];
    $endzeit = date('Y-m-d\TH:i:s'); 

    try {
        $stmt = $conn->prepare("UPDATE zeiterfassung SET endzeit = :endzeit WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':endzeit', $endzeit, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Endzeit aktualisiert', 'id' => $id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating endzeit']);
        }
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

/**
 * @OA\Get(
 *     path="/api/users",
 *     summary="Get all users",
 *     @OA\Response(response=200, description="List of users"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=500, description="Internal server error")
 * )
 */
function getUsers($conn) {
    try {
        $stmt = $conn->prepare("SELECT id, username, email, role FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

/**
 * @OA\Get(
 *     path="/api/timeentries",
 *     summary="Get all time entries for the authenticated user",
 *     @OA\Response(response=200, description="List of time entries"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=500, description="Internal server error")
 * )
 */
function getTimeEntries($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $entries]);
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

/**
 * @OA\Delete(
 *     path="/api/timeentry/{id}",
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
    try {
        // Check if the entry exists
        $stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE id = :id");
        $stmt->bindParam(':id', $entry_id, PDO::PARAM_INT);
        $stmt->execute();
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Eintrag nicht gefunden']);
            return;
        }

        // Check if the entry belongs to the user
        if ($entry['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Nicht erlaubt zu löschen']);
            return;
        }

        // Entry exists and belongs to the user, proceed with deletion
        $stmt = $conn->prepare("DELETE FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':id', $entry_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Eintrag gelöscht']);
    } catch (\Exception $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
}

// Parse the URL path and route the request to the appropriate function
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    switch ($uri) {
        case '/jobrouter/webcampus/zeit/zeiterfassung/api.php/login':
            login($conn);
            break;
        case '/jobrouter/webcampus/zeit/zeiterfassung/api.php/workentry':
            $tokenData = validateToken();
            createNewWorkEntry($conn, $tokenData['user_id']);
            break;
        case '/jobrouter/webcampus/zeit/zeiterfassung/api.php/setendzeit':
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
        case '/jobrouter/webcampus/zeit/zeiterfassung/api.php/users':
            $tokenData = validateToken();
            getUsers($conn);
            break;
        case '/jobrouter/webcampus/zeit/zeiterfassung/api.php/timeentries':
            $tokenData = validateToken();
            getTimeEntries($conn, $tokenData['user_id']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    }
} elseif ($method === 'DELETE') {
    if (preg_match('/^\/jobrouter\/webcampus\/zeit\/zeiterfassung\/api.php\/timeentry\/(\d+)$/', $uri, $matches)) {
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
