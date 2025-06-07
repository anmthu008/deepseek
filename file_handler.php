<?php
/**
 * DeepSeek Web File Handling Module
 * Responsible for securely handling uploaded and accessed files
 */

// Prevent direct access
if (!defined('DEEPSEEK_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Handle file uploads
 */
function handleFileUpload() {
    global $config;
    $isDebugMode = isset($config['system']['debug']) && $config['system']['debug'] === true;

    if ($isDebugMode) {
        error_log("handleFileUpload entered.");
    }
    
    $uploadDir = $config['upload']['directory'] . '/';
    
    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
             error_log("Failed to create upload directory: " . $uploadDir);
             // Depending on severity, might want to return an error to the user here
        }
    }
    
    $files = $_FILES['files'];
    $fileCount = count($files['name']);
    $uploadedFiles = [];
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = $files['name'][$i];
        $fileTmpPath = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $fileError = $files['error'][$i];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            if ($isDebugMode) {
                error_log("File upload error for '{$fileName}': Error code {$fileError}");
            }
            continue;
        }
        
        // Check file size
        if ($fileSize > $config['upload']['max_file_size']) {
            if ($isDebugMode) {
                error_log("File '{$fileName}' exceeded max size: {$fileSize} bytes.");
            }
            continue;
        }
        
        // Check file type
        if (!in_array($fileExt, $config['upload']['allowed_types'])) {
            if ($isDebugMode) {
                error_log("File '{$fileName}' has disallowed type: {$fileExt}");
            }
            continue;
        }
        
        // Generate a safe filename
        $newFileName = 'uploaded_' . uniqid() . '_' . sanitizeFileName($fileName);
        $uploadFilePath = $uploadDir . $newFileName;
        
        // Move the uploaded file
        if (move_uploaded_file($fileTmpPath, $uploadFilePath)) {
            $uploadedFiles[] = [
                'original_name' => $fileName,
                'stored_name' => $newFileName,
                'size' => $fileSize,
                'path' => $uploadFilePath,
                'url' => './uploads/' . $newFileName,
                'type' => $fileExt
            ];
        } else {
            error_log("Failed to move uploaded file from '{$fileTmpPath}' to '{$uploadFilePath}'.");
        }
    }
    
    if ($isDebugMode) {
        error_log("handleFileUpload completed. Uploaded files: " . json_encode($uploadedFiles));
    }

    // Return upload results
    echo json_encode([
        'success' => count($uploadedFiles) > 0,
        'files' => $uploadedFiles
    ]);
}

/**
 * Securely provide file access
 */
function serveFile($fileParam) {
    global $config;
    $isDebugMode = isset($config['system']['debug']) && $config['system']['debug'] === true;

    if ($isDebugMode) {
        error_log("serveFile entered. Requested file param: " . $fileParam);
    }
    
    $fileName = isset($_GET['file']) ? $_GET['file'] : ''; // $fileParam seems unused, using $_GET directly as per original code
    
    if (empty($fileName) || !preg_match('/^uploaded_[a-zA-Z0-9]+_/', $fileName)) {
        if ($isDebugMode) {
            error_log("serveFile: Invalid or empty filename requested: '{$fileName}'");
        }
        header('HTTP/1.0 404 Not Found');
        echo 'File not found';
        exit;
    }
    
    $filePath = $config['upload']['directory'] . '/' . $fileName;
    
    if (!file_exists($filePath)) {
        if ($isDebugMode) {
            error_log("serveFile: File not found at path: '{$filePath}'");
        }
        header('HTTP/1.0 404 Not Found');
        echo 'File not found';
        exit;
    }
    
    // Get file information
    $fileInfo = pathinfo($filePath);
    $fileExt = strtolower($fileInfo['extension']);
    
    // Set content type
    $contentType = getContentTypeByExtension($fileExt);
    header('Content-Type: ' . $contentType);
    
    // Set content length
    $fileSize = filesize($filePath);
    header('Content-Length: ' . $fileSize);
    
    // Set download headers
    $originalName = preg_replace('/^uploaded_[a-zA-Z0-9]+_/', '', $fileName);
    header('Content-Disposition: inline; filename="' . $originalName . '"');
    
    if ($isDebugMode) {
        error_log("serveFile: Serving file '{$filePath}' with content type '{$contentType}' and original name '{$originalName}'.");
    }

    // Output the file
    readfile($filePath);
    exit;
}

/**
 * Get MIME type by extension
 */
function getContentTypeByExtension($ext) {
    $mimeTypes = [
        // Text files
        'txt' => 'text/plain',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'md' => 'text/markdown',
        
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        
        // Documents
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        
        // Archive files
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        
        // Audio/Video
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        
        // Fonts
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        
        // CSV
        'csv' => 'text/csv',
    ];
    
    return isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
}

/**
 * Sanitize filename
 */
function sanitizeFileName($fileName) {
    // Remove dangerous characters
    $fileName = preg_replace('/[^\w\.\-]/i', '_', $fileName);
    
    // Return the sanitized filename
    return $fileName;
}