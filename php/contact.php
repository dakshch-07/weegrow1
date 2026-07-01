<?php
session_start();
header('Content-Type: application/json');

// 1. Dynamic CORS origin lockdown
$allowed_origins = ['https://weegrow.in', 'http://localhost', 'https://localhost'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins) || strpos($origin, 'http://127.0.0.1') === 0 || strpos($origin, 'http://localhost') === 0) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://weegrow.in");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle pre-flight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method Not Allowed"]);
    exit;
}

require_once 'db.php';
require_once 'mail.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// 2. CSRF token validation
$headers = getallheaders();
$csrf_token_header = isset($headers['X-CSRF-Token']) ? $headers['X-CSRF-Token'] : '';
$csrf_token_body = isset($input['csrf_token']) ? $input['csrf_token'] : '';
$csrf_token = $csrf_token_header ?: $csrf_token_body;

if (empty($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "CSRF token validation failed"]);
    exit;
}

// 3. GDPR compliance checkbox validation
if (!isset($input['privacy_consent']) || $input['privacy_consent'] !== true) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "You must consent to the privacy policy before submitting."]);
    exit;
}

// Allowed enums
$allowed_business_types = ['Restaurant', 'Salon & Spa', 'Retail Shop', 'Gym & Fitness', 'Clinic', 'Home Services', 'Other', ''];
$allowed_packages = ['Not sure', 'Starter ₹2,999', 'Growth ₹7,999', 'Scale ₹14,999', 'Premium ₹24,999', 'Custom', ''];

$name = isset($input['name']) ? trim(strip_tags($input['name'])) : '';
$email = isset($input['email']) ? trim(strip_tags($input['email'])) : '';
$phone = isset($input['phone']) ? trim(strip_tags($input['phone'])) : '';
$business_type = isset($input['business_type']) ? trim(strip_tags($input['business_type'])) : '';
$package = isset($input['package']) ? trim(strip_tags($input['package'])) : '';
$message = isset($input['message']) ? trim(strip_tags($input['message'])) : '';

// Validation
if (empty($name) || strlen($name) > 100) {
    echo json_encode(["success" => false, "error" => "Invalid name"]);
    exit;
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    echo json_encode(["success" => false, "error" => "Invalid email"]);
    exit;
}
if (!empty($phone) && !preg_match('/^[0-9\-\+\s]{7,15}$/', $phone)) {
    echo json_encode(["success" => false, "error" => "Invalid phone number"]);
    exit;
}
if (!empty($business_type) && !in_array($business_type, $allowed_business_types)) {
    echo json_encode(["success" => false, "error" => "Invalid business type"]);
    exit;
}
if (!empty($package) && !in_array($package, $allowed_packages)) {
    echo json_encode(["success" => false, "error" => "Invalid package"]);
    exit;
}
if (empty($message) || strlen($message) < 10 || strlen($message) > 1000) {
    echo json_encode(["success" => false, "error" => "Message must be between 10 and 1000 characters"]);
    exit;
}

try {
    $db = DB::getInstance()->getConnection();
    
    // 4. Rate Limiting per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $salt = getenv('IP_SALT') ?: 'default_weegrow_ip_salt_2026!';
    $ip_hash = hash_hmac('sha256', $ip, $salt);
    
    $now = time();
    $limit_time = 60; // 1 minute
    $limit_attempts = 5;
    
    // Check existing attempts
    $stmt = $db->prepare("SELECT attempts, last_attempt FROM webgrowth_rate_limits WHERE ip_hash = ?");
    $stmt->execute([$ip_hash]);
    $rate = $stmt->fetch();
    
    if ($rate) {
        if (($now - $rate['last_attempt']) < $limit_time) {
            if ($rate['attempts'] >= $limit_attempts) {
                http_response_code(429);
                echo json_encode(["success" => false, "error" => "Too many requests. Please try again after 60 seconds."]);
                exit;
            }
            $stmt = $db->prepare("UPDATE webgrowth_rate_limits SET attempts = attempts + 1, last_attempt = ? WHERE ip_hash = ?");
            $stmt->execute([$now, $ip_hash]);
        } else {
            $stmt = $db->prepare("UPDATE webgrowth_rate_limits SET attempts = 1, last_attempt = ? WHERE ip_hash = ?");
            $stmt->execute([$now, $ip_hash]);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO webgrowth_rate_limits (ip_hash, attempts, last_attempt) VALUES (?, 1, ?)");
        $stmt->execute([$ip_hash, $now]);
    }

    // Save to DB (storing hashed IP for GDPR compliance)
    $stmt = $db->prepare("INSERT INTO webgrowth_leads (name, email, phone, business_type, package, message, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $business_type, $package, $message, $ip_hash]);

    // Send email using SMTP client
    $to = "grow@weegrow.in"; 
    $subject = "New Lead from WeeGROW.in — " . ($business_type ? $business_type : 'General') . ": " . $name;
    
    $email_body = "New Lead Details:\n\n";
    $email_body .= "Name: $name\n";
    $email_body .= "Email: $email\n";
    $email_body .= "Phone: $phone\n";
    $email_body .= "Business Type: $business_type\n";
    $email_body .= "Package: $package\n";
    $email_body .= "Message:\n$message\n\n";
    $email_body .= "Submitted At: " . date('Y-m-d H:i:s') . "\n";
    
    $headers = [
        "Reply-To: $email"
    ];
    
    SMTPMailer::send($to, $subject, $email_body, $headers);

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Contact submit error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "An internal server error occurred. Please try again later."]);
}
?>
