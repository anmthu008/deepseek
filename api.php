<?php
/**
 * DeepSeek Web Interface API Handler
 * Responsible for handling front-end requests, communicating with the DeepSeek API, and managing conversation records
 */

// Prevent direct script execution
define('DEEPSEEK_ACCESS', true);

// Set error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// If it's a preflight request, return success directly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration
$config = require_once __DIR__ . '/config.php';

// Determine if debug mode is enabled
$isDebugMode = isset($config['system']['debug']) && $config['system']['debug'] === true;

// Load file handling module
require_once __DIR__ . '/file_handler.php';

// Set timezone
date_default_timezone_set($config['system']['timezone']);

// Create necessary directories
ensureDirectoriesExist([
    $config['upload']['directory'],
    $config['conversation']['directory']
]);

// Get current user ID (in a real application, there should be authentication logic here)
$userId = $config['users']['default_user'];

// Dispatch handling based on request type
$requestType = isset($_GET['action']) ? $_GET['action'] : '';

// Handle file upload requests
if (isset($_FILES['files'])) {
    handleFileUpload();
    exit;
}

// Handle file serving requests
if ($requestType === 'serve_file') {
    serveFile($_GET['file'] ?? '');
    exit;
}

// Handle JSON POST requests
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If there is JSON data, handle API request
if ($data && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle different types of requests based on the action parameter
    switch ($requestType) {
        case 'get_conversations':
            getConversations();
            break;
        case 'get_conversation':
            getConversation(isset($data['conversation_id']) ? $data['conversation_id'] : '');
            break;
        case 'delete_conversation':
            deleteConversation(isset($data['conversation_id']) ? $data['conversation_id'] : '');
            break;
        default:
            // Default to handling chat requests
            handleChatRequest($data);
            break;
    }
} else {
    // Handle GET requests
    switch ($requestType) {
        case 'get_conversations':
            getConversations();
            break;
        case 'get_conversation':
            getConversation(isset($_GET['conversation_id']) ? $_GET['conversation_id'] : '');
            break;
        default:
            returnError(400, 'Invalid request');
            break;
    }
}

/**
 * Handle chat requests with the DeepSeek API
 */
function handleChatRequest($data) {
    global $config, $userId, $isDebugMode; // Added $isDebugMode
    
    // Validate request data
    if (!isset($data['messages']) || !is_array($data['messages'])) {
        returnError(400, 'Missing or invalid messages');
        return;
    }
    
    // Extract request parameters
    $messages = $data['messages'];
    $conversationId = isset($data['conversation_id']) ? $data['conversation_id'] : generateConversationId();
    $model = isset($data['model']) ? $data['model'] : $config['api']['models']['default'];
    
    // If deep thinking is specified, switch to the thinking model
    if (isset($data['deep_thinking']) && $data['deep_thinking']) {
        $model = $config['api']['models']['thinking'];
    }
    
    // Merge API parameters (use configuration defaults, allow request to override some parameters)
    $temperature = isset($data['temperature']) ? $data['temperature'] : $config['api']['parameters']['temperature'];
    $maxTokens = isset($data['max_tokens']) ? $data['max_tokens'] : $config['api']['parameters']['max_tokens'];
    $topP = isset($data['top_p']) ? $data['top_p'] : $config['api']['parameters']['top_p'];
    $stream = isset($data['stream']) ? $data['stream'] : true;
    $tools = isset($data['tools']) ? $data['tools'] : null;
    
    // Process file information
    $fileInfo = [];
    if (isset($data['files']) && is_array($data['files'])) {
        $fileInfo = $data['files'];
        
        // Add file information to the message
        $fileMessage = "I have uploaded the following files:\n"; // Translated
        foreach ($fileInfo as $file) {
            $fileMessage .= "- {$file['name']} ({$file['type']})\n";
        }
        
        // Ensure the message array contains file information
        $foundUserMessage = false;
        foreach ($messages as $index => $message) {
            if ($message['role'] === 'user') {
                // Check if the last user message already contains file information
                if (!$foundUserMessage) {
                    $foundUserMessage = true;
                }
            }
        }
        
        // If no user message is found or the last message does not contain file information, add file information
        if (!$foundUserMessage) {
            $messages[] = [
                'role' => 'user',
                'content' => $fileMessage
            ];
        }
    }
    
    // Prepare data to be sent to DeepSeek
    $requestData = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'top_p' => $topP,
        'stream' => $stream
    ];
    
    // If there is tool configuration, add it to the request
    if ($tools !== null) {
        $requestData['tools'] = $tools;
    }

    if ($isDebugMode) {
        $loggableRequestData = $requestData;
        // Intentionally not redacting Authorization header for DeepSeek as it's a direct API call,
        // but if this were a user-provided key, redaction would be critical.
        // For this specific DeepSeek API, the key is already in $config, not user input.
        error_log("Request to DeepSeek API: Model - {$loggableRequestData['model']}, Messages Count - " . count($loggableRequestData['messages']));
        // To log full messages (can be verbose):
        // error_log("Request Messages: " . json_encode($loggableRequestData['messages']));
    }
    
    // Save request message to conversation history
    saveConversationMessage($userId, $conversationId, $messages, array_merge([
        'model' => $model,
        'deep_thinking' => isset($data['deep_thinking']) ? $data['deep_thinking'] : false,
        'files' => $fileInfo
    ], $requestData));
    
    // Set curl options
    $ch = curl_init($config['api']['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api']['key'] // Actual key here
    ]);
    
    // If it's a streaming response
    // Replace the streaming output part in the handleChatRequest function
if ($stream) {
    // Set appropriate headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    // Disable output buffering
    if (ob_get_level()) ob_end_clean();
    
    // Disable output compression
    ini_set('zlib.output_compression', 0);
    ini_set('output_buffering', 0);
    
    // Send initial blank data to start the connection
    echo "retry: 1000\n\n";
    flush();
    
    // Record response content
    $responseContent = '';
    
    // Set curl options to ensure smaller data chunks and more frequent output
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // Reduce buffer size
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$responseContent) {
        $responseContent .= $data;
        
        // Split data to ensure finer-grained output
        $chunks = str_split($data, 1); // Send each character individually
        foreach($chunks as $chunk) {
            echo $chunk;
            flush();
        }
        
        return strlen($data);
    });
    
    // Execute request
    $executionResult = curl_exec($ch);

    // Specifically check if curl_exec failed
    if ($executionResult === false) {
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        error_log("cURL execution failed directly. Errno: $curlErrNo, Error: $curlError");
        if ($isDebugMode) {
            // Ensure headers are still set for event-stream before echoing error
            // This check might be redundant if headers are always set before this point,
            // but it's a safeguard.
            if (!headers_sent()) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                if (ob_get_level()) ob_end_clean(); // Ensure no buffering
                ini_set('zlib.output_compression', 0);
                ini_set('output_buffering', 0);
                echo "retry: 1000\n\n"; // Keep connection alive for client to receive error
                flush();
            }
            echo "data: " . json_encode([
                'error' => 'API request execution failed on server.',
                'details' => "cURL Error ($curlErrNo): $curlError. Check server logs."
            ]) . "\n\n";
            echo "data: [DONE]\n\n"; // Ensure stream termination
            flush();
        }
        // No further processing of assistant message if exec failed
        // curl_close($ch) will be handled in the finally block or end of function
        return; // Exit function since the request fundamentally failed
    }
    
    // Existing check for curl_errno (could be set even if $executionResult is not false, e.g. HTTP errors)
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        error_log("cURL Error (post-execution check): " . $curlError); // Clarified log
        if ($isDebugMode) {
            echo "data: " . json_encode(['error' => 'cURL Error: ' . $curlError, 'details' => 'Check server logs for more info.']) . "\n\n";
            echo "data: [DONE]\n\n"; // Ensure stream termination
            flush();
        }
        // No further processing if there's a curl error.
        return;
    } else {
        if ($isDebugMode) {
            error_log("Raw API Response (Stream): " . $responseContent);
        }
        // Parse streaming response to extract full message content
        $assistantMessage = extractAssistantMessage($responseContent);
        if ($assistantMessage) {
            // Save AI response to conversation history
            $assistantData = [
                'role' => 'assistant',
                'content' => $assistantMessage
            ];
            appendConversationMessage($userId, $conversationId, $assistantData);
        }
        
        // Ensure the end marker is sent
        echo "data: [DONE]\n\n";
        flush();
    }
} else { // Non-streaming response
    $responseContent = curl_exec($ch);
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        error_log("cURL Error (Non-Stream): " . $curlError);
        returnError(500, 'API request failed', $isDebugMode ? ['curl_error' => $curlError] : null);
        curl_close($ch);
        return;
    }
    if ($isDebugMode) {
        error_log("Raw API Response (Non-Stream): " . $responseContent);
    }
    $responseData = json_decode($responseContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to decode API JSON response: " . json_last_error_msg());
        returnError(500, 'Invalid API response format', $isDebugMode ? ['json_error' => json_last_error_msg(), 'raw_response' => $responseContent] : null);
        curl_close($ch);
        return;
    }

    // Assuming non-stream response structure is similar for messages
    $assistantMessage = $responseData['choices'][0]['message']['content'] ?? null;
    if ($assistantMessage) {
        $assistantData = [
            'role' => 'assistant',
            'content' => $assistantMessage
        ];
        appendConversationMessage($userId, $conversationId, $assistantData);
    }
    echo json_encode($responseData); // Send the full response back for non-streaming
}
    
    // Close curl
    curl_close($ch);
}

/**
 * Get all conversations for the user
 */
function getConversations() {
    global $config, $userId;
    
    $conversationsDir = $config['conversation']['directory'] . '/' . $userId;
    $conversations = [];
    
    if (is_dir($conversationsDir)) {
        $files = glob($conversationsDir . '/*.json');
        
        foreach ($files as $file) {
            $conversationId = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);
            
            if ($data) {
                // Extract conversation title and time
                $title = isset($data['title']) ? $data['title'] : 'New Conversation'; // Translated
                $created = isset($data['created_at']) ? $data['created_at'] : filemtime($file);
                $updated = isset($data['updated_at']) ? $data['updated_at'] : filemtime($file);
                
                // If there is no title, try to generate it from the first message
                if ($title === 'New Conversation' && isset($data['messages'][0]['content'])) { // Translated
                    // Use the user's first message as the title
                    foreach ($data['messages'] as $message) {
                        if ($message['role'] === 'user') {
                            $title = mb_substr($message['content'], 0, 30) . (mb_strlen($message['content']) > 30 ? '...' : '');
                            break;
                        }
                    }
                }
                
                $conversations[] = [
                    'id' => $conversationId,
                    'title' => $title,
                    'created_at' => $created,
                    'updated_at' => $updated,
                    'message_count' => count($data['messages'])
                ];
            }
        }
    }
    
    // Sort by update time
    usort($conversations, function($a, $b) {
        return $b['updated_at'] - $a['updated_at'];
    });
    
    // Return conversation list
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
}

/**
 * Get detailed information for a specific conversation
 */
function getConversation($conversationId) {
    global $config, $userId;
    
    if (empty($conversationId)) {
        returnError(400, 'Conversation ID is required');
        return;
    }
    
    $conversationFile = $config['conversation']['directory'] . '/' . $userId . '/' . $conversationId . '.json';
    
    if (!file_exists($conversationFile)) {
        returnError(404, 'Conversation not found');
        return;
    }
    
    $data = json_decode(file_get_contents($conversationFile), true);
    
    if (!$data) {
        returnError(500, 'Failed to load conversation data');
        return;
    }
    
    // Return conversation data
    echo json_encode([
        'success' => true,
        'conversation' => $data
    ]);
}

/**
 * Delete a specific conversation
 */
function deleteConversation($conversationId) {
    global $config, $userId;
    
    if (empty($conversationId)) {
        returnError(400, 'Conversation ID is required');
        return;
    }
    
    $conversationFile = $config['conversation']['directory'] . '/' . $userId . '/' . $conversationId . '.json';
    
    if (!file_exists($conversationFile)) {
        returnError(404, 'Conversation not found');
        return;
    }
    
    if (unlink($conversationFile)) {
        echo json_encode([
            'success' => true,
            'message' => 'Conversation deleted successfully'
        ]);
    } else {
        returnError(500, 'Failed to delete conversation');
    }
}

/**
 * Save conversation message
 */
function saveConversationMessage($userId, $conversationId, $messages, $metadata = []) {
    global $config, $isDebugMode; // Added $isDebugMode
    
    $userDir = $config['conversation']['directory'] . '/' . $userId;
    
    // Ensure user directory exists
    if (!is_dir($userDir)) {
        if (!mkdir($userDir, 0755, true) && !is_dir($userDir)) {
            // Log error if directory creation fails
            error_log("Failed to create directory: " . $userDir);
            // Potentially throw an exception or return an error if critical
        }
    }
    
    $conversationFile = $userDir . '/' . $conversationId . '.json';
    $now = time();
    
    // Create or update conversation file
    if (file_exists($conversationFile)) {
        $currentContent = file_get_contents($conversationFile);
        if ($currentContent === false && $isDebugMode) {
            error_log("Failed to read existing conversation file: " . $conversationFile);
        }
        $data = json_decode($currentContent, true);
        if (!$data) {
            if ($isDebugMode && $currentContent !== '' && $currentContent !== null) { // Avoid logging for new/empty files
                error_log("Failed to decode JSON from conversation file: " . $conversationFile . " - Error: " . json_last_error_msg());
            }
            $data = [
                'id' => $conversationId,
                'title' => 'New Conversation',
                'created_at' => $now,
                'updated_at' => $now,
                'messages' => [],
                'metadata' => []
            ];
        }
    } else {
        $data = [
            'id' => $conversationId,
            'title' => 'New Conversation',
            'created_at' => $now,
            'updated_at' => $now,
            'messages' => [],
            'metadata' => []
        ];
    }
    
    // Update metadata
    $data['metadata'] = array_merge($data['metadata'], $metadata);
    $data['updated_at'] = $now;
    
    // If it's a new conversation, extract the first user message as the title
    if ($data['title'] === 'New Conversation' && !empty($messages)) { // Translated
        // Find the first user message
        foreach ($messages as $message) {
            if ($message['role'] === 'user') {
                $data['title'] = mb_substr($message['content'], 0, 30) . (mb_strlen($message['content']) > 30 ? '...' : '');
                break;
            }
        }
    }
    
    // Save all new messages
    foreach ($messages as $message) {
        if (!isset($message['timestamp'])) {
            $message['timestamp'] = $now;
        }
        $data['messages'][] = $message;
    }
    
    // Write to file
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($conversationFile, $jsonData) === false) {
        error_log("Failed to write to conversation file: " . $conversationFile);
    } elseif ($isDebugMode) {
        error_log("Saved conversation message to: " . $conversationFile . " - Data: " . $jsonData);
    }
    
    return $conversationId;
}

/**
 * Add a single message to an existing conversation
 */
function appendConversationMessage($userId, $conversationId, $message) {
    global $config, $isDebugMode; // Added $isDebugMode
    
    $userDir = $config['conversation']['directory'] . '/' . $userId;
    $conversationFile = $userDir . '/' . $conversationId . '.json';
    
    if (file_exists($conversationFile)) {
        $currentContent = file_get_contents($conversationFile);
        if ($currentContent === false && $isDebugMode) {
            error_log("Failed to read existing conversation file for append: " . $conversationFile);
        }
        $data = json_decode($currentContent, true);

        if ($data) {
            if (!isset($message['timestamp'])) {
                $message['timestamp'] = time();
            }
            $data['messages'][] = $message;
            $data['updated_at'] = time();
            
            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($conversationFile, $jsonData) === false) {
                error_log("Failed to append to conversation file: " . $conversationFile);
            } elseif ($isDebugMode) {
                error_log("Appended message to: " . $conversationFile . " - Message: " . json_encode($message));
            }
            return true;
        } elseif ($isDebugMode && $currentContent !== '' && $currentContent !== null) {
             error_log("Failed to decode JSON for append from conversation file: " . $conversationFile . " - Error: " . json_last_error_msg());
        }
    } elseif ($isDebugMode) {
        error_log("Conversation file not found for append: " . $conversationFile);
    }
    
    return false;
}

/**
 * Extract the complete assistant message from the SSE stream response
 */
function extractAssistantMessage($responseContent) {
    global $isDebugMode; // Added $isDebugMode
    $content = '';

    if ($isDebugMode) {
        error_log("extractAssistantMessage received: " . $responseContent);
    }

    $lines = explode("\n", $responseContent);
    
    foreach ($lines as $line) {
        if ($isDebugMode) {
            error_log("Processing line: " . $line);
        }
        if (strpos($line, 'data: ') === 0) {
            $data = substr($line, 6);
            if ($data === '[DONE]') continue;
            
            try {
                $json = json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if ($isDebugMode) {
                        error_log("JSON decode error: " . json_last_error_msg() . " for data: " . $data);
                    }
                    continue; // Skip malformed JSON
                }
                if ($isDebugMode) {
                    error_log("Decoded JSON: " . print_r($json, true));
                }
                if (isset($json['choices'][0]['delta']['content'])) {
                    $content .= $json['choices'][0]['delta']['content'];
                }
            } catch (Exception $e) {
                if ($isDebugMode) {
                    error_log("Exception during JSON decode or processing: " . $e->getMessage() . " for data: " . $data);
                }
                // Parsing error, skip
            }
        }
    }
    
    if ($isDebugMode) {
        error_log("extractAssistantMessage returning: " . $content);
    }
    return $content;
}

/**
 * Generate a unique conversation ID
 */
function generateConversationId() {
    return uniqid() . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
}

/**
 * Ensure required directories exist
 */
function ensureDirectoriesExist($directories) {
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Return error message
 */
function returnError($code, $message, $debugInfo = null) {
    global $isDebugMode;
    http_response_code($code);

    $errorData = ['error' => $message];
    if ($isDebugMode && $debugInfo !== null) {
        $errorData['debug_info'] = $debugInfo;
    }

    $logMessage = "Error {$code}: {$message}";
    if ($debugInfo) {
        $logMessage .= " - Debug Info: " . json_encode($debugInfo);
    }
    error_log($logMessage);

    echo json_encode($errorData);
    exit;
}