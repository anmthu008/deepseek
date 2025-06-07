<?php
/**
 * DeepSeek API Configuration File
 * Contains sensitive information such as API key and endpoint URL
 */


// Prevent direct access
if (!defined('DEEPSEEK_ACCESS')) {
    die('Direct access not permitted');
}

return [
    // API Configuration
    'api' => [
        'key' => 'sk-148a849ede56455ab2602db661ea9f42', // Replace with your actual DeepSeek API key
        'url' => 'https://api.deepseek.com/v1/chat/completions', // DeepSeek API endpoint
        'models' => [
            'default' => 'deepseek-chat', // Default model
            'thinking' => 'deepseek-reasoner' // Deep thinking model
        ],
        'parameters' => [
            'temperature' => 0.1,  // Default temperature
            'max_tokens' => 20000,  // Default maximum tokens
            'top_p' => 0.9,        // Default top_p value
        ]
    ],
    
    // File upload configuration
    'upload' => [
        'max_file_size' => 100 * 1024 * 1024, // 100MB
        'allowed_types' => [
            // Documents
            'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'md',
            // Spreadsheets
            'xls', 'xlsx', 'csv',
            // Presentations
            'ppt', 'pptx',
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            // Code files
            'json', 'xml', 'html', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'h'
        ],
        'directory' => __DIR__ . '/uploads',
    ],
    
    // Conversation history configuration
    'conversation' => [
        'directory' => __DIR__ . '/conversations',
        'max_history' => 100 // Maximum number of conversations stored per user
    ],
    
    // User configuration
    'users' => [
        'use_authentication' => false, // Whether to enable user authentication
        'default_user' => 'anonymous', // Default user ID
    ],
    
    // System configuration
    'system' => [
        'debug' => false, // Debug mode
        'timezone' => 'Asia/Shanghai', // Timezone setting
        'version' => '1.0.0', // Application version
    ]
];
?>
