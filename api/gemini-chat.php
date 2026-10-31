<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/bootstrap.php';

$userMessage = trim($_POST['message'] ?? '');

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'reply' => 'Please enter a valid message.']);
    exit;
}

// Fetch Gemini Settings
$geminiKeyStmt = $pdo->query('SELECT setting_value FROM settings WHERE setting_key = "gemini_api_key" LIMIT 1');
$geminiApiKey = trim((string)$geminiKeyStmt->fetchColumn());

$modelStmt = $pdo->query('SELECT setting_value FROM settings WHERE setting_key = "gemini_model" LIMIT 1');
$geminiModel = trim((string)$modelStmt->fetchColumn()) ?: 'gemini-1.5-flash';

$aiEnabledStmt = $pdo->query('SELECT setting_value FROM settings WHERE setting_key = "ai_support_enabled" LIMIT 1');
$aiEnabled = (trim((string)$aiEnabledStmt->fetchColumn()) !== '0');

// Fallback response if API key is not configured or disabled
if (!$aiEnabled || empty($geminiApiKey)) {
    echo json_encode([
        'success' => true,
        'reply' => "Namaskar! Welcome to BengalSeva Customer Support.\n\nOur team is available 24x7 across all 23 districts of West Bengal.\n- For immediate help: Call or WhatsApp our helpline at +91 98300XXXXX.\n- You can also submit an official complaint ticket on our Support Helpdesk page.\n\nHow else can we assist you with Mart orders, bike rides, or doctor bookings today?"
    ]);
    exit;
}

// Construct BengalSeva System Context for Gemini
$systemContext = "You are BengalSeva AI Support Executive. BengalSeva is West Bengal's premier multi-vertical marketplace connecting all 23 districts (Kolkata, Howrah, Darjeeling, Siliguri, Medinipur, etc.).
Services available:
1. Rapido-style Bike Taxi, Cab & Courier Parcel starting at Rs 49.
2. Fresh Groceries, Fish, Mustard Oil & Mart Delivery with coupon BENGAL50 (50% off).
3. Doctor & Clinic Appointment Bookings (e.g. Apollo Gleneagles).
4. Verified Home Services (Electricians, Plumbers, CA Tax consultants).
5. Bengal Tourism & Homestays in Darjeeling, Mandarmani, Sundarbans & Bolpur.
Be polite, welcoming, helpful, and concise. You can speak English, Bengali (বাংলা), or Hindi (हिन्दी) matching whatever language the customer uses. Never make up fake phone numbers. Always guide users to the website features.";

$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key=" . urlencode($geminiApiKey);

$requestPayload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $systemContext . "\n\nCustomer Message: " . $userMessage]
            ]
        ]
    ]
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response !== false) {
    $resData = json_decode((string)$response, true);
    $replyText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($replyText) {
        echo json_encode(['success' => true, 'reply' => trim($replyText)]);
        exit;
    }
}

// Graceful fallback if Gemini returns quota error or invalid key
echo json_encode([
    'success' => true,
    'reply' => "Thank you for reaching out to BengalSeva. Our support executives are available on phone/WhatsApp at +91 98300XXXXX, or you can submit an online ticket via our Helpdesk."
]);