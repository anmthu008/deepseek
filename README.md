# DeepSeek Frontend UI
A highly accurate clone of the DeepSeek frontend interface, implemented by calling the DeepSeek model through a self-built API proxy.

[Demo Link](http://deepseek.lzx1.top)

## Project Introduction

This project aims to replicate DeepSeek's frontend interactive interface, achieving seamless communication with the DeepSeek large language model through a PHP backend proxy layer. This provides developers and users with a familiar DeepSeek interface experience, while allowing you to use your own API key to access the DeepSeek model.

## Technology Stack

- **Frontend**: User interface implemented with pure HTML, CSS, JavaScript
- **Backend**: API proxy layer implemented in PHP, responsible for handling communication with the DeepSeek API
- **Configuration**: Simple PHP configuration file, no complex environment setup required

## Core Features

- Highly accurate clone of the DeepSeek user interface
- Complete chat conversation functionality
- Support for Markdown, code highlighting, and syntax rendering
- Conversation history management
- Multi-session parallel support
- File upload functionality
- Responsive design, supporting mobile and desktop devices

## Installation Guide

### Prerequisites

- PHP 7.4+
- Web server (Apache/Nginx)
- DeepSeek API Key

### Installation Steps

1. Clone or download the repository to your web server directory

```bash
git clone https://github.com/yourusername/deepseek-frontend-clone.git
# Or download the ZIP file directly and unzip it to the website root directory
```

2. Configure your web server

Ensure your web server points to the project's root directory and that PHP is configured correctly.

For Apache, you can use the following .htaccess configuration:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
</IfModule>
```

3. Configure API Key

Edit the `config.php` file and update the following configuration:

```php
// API Configuration
'api' => [
    'key' => 'YOUR_DEEPSEEK_API_KEY_HERE', // Replace with your API key
    'url' => 'https://api.deepseek.top/v1/chat/completions', // DeepSeek API endpoint
    'models' => [
        'default' => 'deepseek-v3', // Default model
        'thinking' => 'deepseek-r1' // Deep thinking model
    ],
    // Other parameters...
],
```
[Get official API key](https://platform.deepseek.com/)

[Cheaper and more efficient relay API station](https://api.lzx1.top)

4. Directory Permissions

Ensure the upload directory and conversation history directory have correct write permissions:

```bash
chmod 755 uploads
chmod 755 conversations
```

5. Access Your Website

Access the deployed project URL through your browser to use the DeepSeek clone frontend.

## Configuration Details

All project configurations are centralized in the `config.php` file, mainly including the following parts:

### API Configuration

```php
'api' => [
    'key' => 'YOUR_API_KEY', // DeepSeek API Key
    'url' => 'https://api.deepseek.top/v1/chat/completions', // API endpoint
    'models' => [
        'default' => 'deepseek-v3', // Default model
        'thinking' => 'deepseek-r1' // Deep thinking model
    ],
    'parameters' => [
        'temperature' => 0.7,  // Default temperature
        'max_tokens' => 2000,  // Default maximum tokens
        'top_p' => 0.9,        // Default top_p value
    ]
],
```

### File Upload Configuration

```php
'upload' => [
    'max_file_size' => 100 * 1024 * 1024, // Maximum file size (100MB)
    'allowed_types' => [
        // Allowed file types...
    ],
    'directory' => __DIR__ . '/uploads', // Upload directory
],
```

### Conversation History Configuration

```php
'conversation' => [
    'directory' => __DIR__ . '/conversations', // Conversation history storage directory
    'max_history' => 100 // Maximum number of conversations stored per user
],
```

### System Configuration

```php
'system' => [
    'debug' => false, // Debug mode
    'timezone' => 'Asia/Shanghai', // Timezone setting
    'version' => '1.0.0', // Application version
],
```

## Custom Models

You can customize the available DeepSeek models in the configuration file:

```php
'models' => [
    'default' => 'deepseek-v3', // Default conversation model
    'thinking' => 'deepseek-r1', // Deep thinking model
    'custom' => 'your-custom-model-id' // Add custom model
],
```

## Project Structure

```
deepseek-frontend-clone/
├── assets/            # Static assets (CSS, JS, Images)
│   ├── css/           # Style files
│   ├── js/            # JavaScript files
│   └── images/        # Image resources
├── uploads/           # File upload directory
├── conversations/     # Conversation history storage directory
├── api.php            # API handling logic
├── config.php         # Configuration file
├── file_handler.php   # File handling logic
├── index.php          # Main entry point
└── README.md          # Project documentation
```

## Frequently Asked Questions

### API Key Invalid

Ensure you have configured the correct DeepSeek API key in `config.php`, and that the key has sufficient permissions and balance.

### File Upload Failed

Check the write permissions for the `uploads` directory, and ensure that your PHP configuration allows file uploads and that the maximum file size limit is appropriate.

### How to Change API Provider

If you want to use an API from another AI service provider, simply modify the API URL and corresponding parameter format in `config.php`. You may also need to adjust the request handling logic in `api.php`.

## Contribution Guide

Suggestions for improvements and code contributions to this project are welcome:

1. Fork this project
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Disclaimer

This project is for learning and research purposes only and is not affiliated with the official DeepSeek. When using this project, please comply with the terms and conditions of the relevant API service. Users must ensure they have legal API access permissions.

## Acknowledgements

- [DeepSeek](https://deepseek.com/) - Original interface design inspiration
- All contributors and users

---

**Note**: During deployment and use, please ensure your API key is secure. Do not commit configuration files containing real API keys to public repositories.
