<?php
/**
 * ============================================================================
 * SINGLE-FILE AI CHAT INTERFACE
 * ============================================================================
 * A portable, no-build PHP chat interface for OpenAI or Local Ollama
 * Drop this file on any shared hosting server and it just works.
 * 
 * @version 1.0.0
 * @author  Senior Full Stack Engineer
 * ============================================================================
 */

// ============================================================================
// CONFIGURATION - Edit these values
// ============================================================================

// Security: Set your access password (required to use the chat)
define('ACCESS_PASSWORD', 'local');

// API Configuration
$apiKey  = 'ollama';  // Your OpenAI API key

// Base URL: OpenAI default, or change for Ollama/other providers
// OpenAI:  https://api.openai.com/v1
// Ollama:  http://localhost:11434/v1
// OpenRouter: https://openrouter.ai/api/v1
$baseUrl = 'http://localhost:11434/v1';

// Model selection
// OpenAI: gpt-4o-mini, gpt-4o, gpt-4-turbo, gpt-3.5-turbo
// Ollama: llama3, mistral, codellama, etc.
$model   = 'llama3:8b';

// System prompt (optional)
$systemPrompt = 'You are a helpful AI assistant. Be concise and helpful.';

// Max tokens for response
$maxTokens = 4096;

// ============================================================================
// SECURITY CHECK - Password Protection
// ============================================================================
session_start();

// Check for password in URL or session
$authenticated = false;

if (isset($_GET['pwd']) && $_GET['pwd'] === ACCESS_PASSWORD) {
    $_SESSION['chat_authenticated'] = true;
    $authenticated = true;
    // Redirect to clean URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (isset($_SESSION['chat_authenticated']) && $_SESSION['chat_authenticated'] === true) {
    $authenticated = true;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ============================================================================
// SSE STREAMING ENDPOINT
// ============================================================================
if (isset($_GET['stream']) && $_GET['stream'] == '1' && $authenticated) {
    
    // Disable all output buffering for real-time streaming
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    @ini_set('implicit_flush', true);
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_implicit_flush(true);
    
    // Set SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Nginx buffering off
    
    // Get the messages from POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['messages'])) {
        echo "data: " . json_encode(['error' => 'Invalid request']) . "\n\n";
        flush();
        exit;
    }
    
    $messages = $data['messages'];
    
    // Prepend system message if configured
    if (!empty($systemPrompt)) {
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt
        ]);
    }
    
    // Prepare API request
    $postData = json_encode([
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => $maxTokens,
        'stream' => true
    ]);
    
    // Initialize cURL
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/chat/completions',
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'Accept: text/event-stream'
        ],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        // Streaming callback
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            // Parse SSE data from API
            $lines = explode("\n", $data);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if (empty($line)) continue;
                
                // Handle data lines
                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6);
                    
                    if ($jsonStr === '[DONE]') {
                        echo "data: [DONE]\n\n";
                        flush();
                        continue;
                    }
                    
                    $json = json_decode($jsonStr, true);
                    
                    if ($json && isset($json['choices'][0]['delta']['content'])) {
                        $content = $json['choices'][0]['delta']['content'];
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        flush();
                    }
                    
                    // Handle errors from API
                    if ($json && isset($json['error'])) {
                        echo "data: " . json_encode(['error' => $json['error']['message']]) . "\n\n";
                        flush();
                    }
                }
            }
            
            return strlen($data);
        }
    ]);
    
    // Execute request
    $result = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        echo "data: " . json_encode(['error' => 'Connection error: ' . $error]) . "\n\n";
        flush();
    }
    
    curl_close($ch);
    exit;
}

// ============================================================================
// LOGIN PAGE (if not authenticated)
// ============================================================================
if (!$authenticated):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Söhne', 'ui-sans-serif', system-ui, -apple-system, 'Segoe UI', Roboto, Ubuntu, Cantarell, 'Noto Sans', sans-serif;
            background-color: #343541;
            color: #ececf1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background-color: #40414f;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .login-container h1 {
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .login-container p {
            color: #8e8ea0;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .login-form input {
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #565869;
            background-color: #40414f;
            color: #ececf1;
            font-size: 16px;
            outline: none;
            transition: border-color 0.2s;
        }
        .login-form input:focus {
            border-color: #10a37f;
        }
        .login-form input::placeholder {
            color: #8e8ea0;
        }
        .login-form button {
            padding: 14px 16px;
            border-radius: 12px;
            border: none;
            background-color: #10a37f;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .login-form button:hover {
            background-color: #1a7f64;
        }
        .logo {
            width: 48px;
            height: 48px;
            margin: 0 auto 20px;
            background-color: goldenrod;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo svg {
            width: 28px;
            height: 28px;
            fill: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"></path>
            </svg>
        </div>
        <h1>AI Chat Interface</h1>
        <p>Enter access password to continue</p>
        <form class="login-form" method="GET">
            <input type="password" name="pwd" placeholder="Access Password" required autofocus>
            <button type="submit">Continue</button>
        </form>
    </div>
</body>
</html>
<?php
exit;
endif;

// ============================================================================
// MAIN CHAT INTERFACE (authenticated)
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AI Chat</title>
    
    <!-- External Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    
    <style>
        /* ================================================================
           CSS RESET & BASE STYLES
           ================================================================ */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        body {
            font-family: 'Söhne', 'ui-sans-serif', system-ui, -apple-system, 'Segoe UI', Roboto, Ubuntu, Cantarell, 'Noto Sans', sans-serif;
            background-color: #343541;
            color: #ececf1;
            font-size: 16px;
            line-height: 1.6;
        }
        
        /* ================================================================
           LAYOUT STRUCTURE
           ================================================================ */
        .app-container {
            display: flex;
            height: 100vh;
            width: 100%;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background-color: #202123;
            display: flex;
            flex-direction: column;
            padding: 8px;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 8px;
        }
        
        .new-chat-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: transparent;
            color: #ececf1;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            transition: background-color 0.2s;
        }
        
        .new-chat-btn:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .new-chat-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }
        
        .chat-history-title {
            padding: 12px;
            font-size: 12px;
            color: #8e8ea0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .chat-history-item {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            color: #ececf1;
            cursor: pointer;
            transition: background-color 0.2s;
            /* New Flexbox properties for alignment */
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        
        .chat-history-item:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .chat-history-item.active {
            background-color: rgba(255,255,255,0.15);
        }

        .chat-history-title-text {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .delete-chat-btn {
            opacity: 0; /* Hidden by default */
            background: transparent;
            border: none;
            color: #8e8ea0;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .chat-history-item:hover .delete-chat-btn {
            opacity: 1; /* Show on hover */
        }

        .delete-chat-btn:hover {
            color: #ff4d4d; /* Red color */
            background-color: rgba(255, 77, 77, 0.1);
        }
        
        .delete-chat-btn svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 8px;
        }
        
        .sidebar-footer-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            background: transparent;
            border: none;
            color: #ececf1;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            transition: background-color 0.2s;
        }
        
        .sidebar-footer-btn:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .sidebar-footer-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            position: relative;
        }
        
        /* Header */
        .chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background-color: #343541;
            position: relative;
            z-index: 10;
        }
        
        .menu-toggle {
            display: none;
            background: transparent;
            border: none;
            color: #ececf1;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        
        .menu-toggle:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .menu-toggle svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        .model-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background-color: transparent;
            border: none;
            border-radius: 8px;
            color: #ececf1;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .model-selector:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .model-selector svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Chat Messages Area */
        .chat-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }
        
        .chat-messages {
            max-width: 768px;
            margin: 0 auto;
            padding: 24px 16px 140px;
        }
        
        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 24px;
            text-align: center;
        }
        
        .empty-state-logo {
            width: 72px;
            height: 72px;
            margin-bottom: 24px;
            opacity: 0.3;
        }
        
        .empty-state-logo svg {
            width: 100%;
            height: 100%;
            fill: #ececf1;
        }
        
        .empty-state h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #ececf1;
        }
        
        .empty-state p {
            color: #8e8ea0;
            font-size: 16px;
        }
        
        /* Message Styles */
        .message {
            display: flex;
            gap: 16px;
            padding: 24px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .message:last-child {
            border-bottom: none;
        }
        
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }
        
        .message.user .message-avatar {
            background-color: #5436da;
            color: white;
        }
        
        .message.assistant .message-avatar {
            background-color: #10a37f;
        }
        
        .message.assistant .message-avatar svg {
            width: 20px;
            height: 20px;
            fill: white;
        }
        
        .message-content {
            flex: 1;
            min-width: 0;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        
        .message-content p {
            margin-bottom: 16px;
        }
        
        .message-content p:last-child {
            margin-bottom: 0;
        }
        
        .message-content ul, .message-content ol {
            margin: 16px 0;
            padding-left: 24px;
        }
        
        .message-content li {
            margin-bottom: 8px;
        }
        
        .message-content h1, .message-content h2, .message-content h3, 
        .message-content h4, .message-content h5, .message-content h6 {
            margin: 24px 0 16px;
            font-weight: 600;
        }
        
        .message-content h1 { font-size: 1.5em; }
        .message-content h2 { font-size: 1.3em; }
        .message-content h3 { font-size: 1.15em; }
        
        .message-content a {
            color: #7ab7ff;
            text-decoration: none;
        }
        
        .message-content a:hover {
            text-decoration: underline;
        }
        
        .message-content strong {
            font-weight: 600;
        }
        
        .message-content em {
            font-style: italic;
        }
        
        .message-content blockquote {
            border-left: 3px solid #565869;
            padding-left: 16px;
            margin: 16px 0;
            color: #b4b4b4;
        }
        
        .message-content hr {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 24px 0;
        }
        
        /* Code Blocks */
        .message-content code {
            font-family: 'Söhne Mono', 'Monaco', 'Andale Mono', 'Ubuntu Mono', monospace;
            font-size: 14px;
        }
        
        .message-content code:not(pre code) {
            background-color: rgba(255,255,255,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .message-content pre {
            background-color: #1e1e1e;
            border-radius: 8px;
            margin: 16px 0;
            overflow: hidden;
        }
        
        .code-block-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background-color: #2d2d2d;
            font-size: 12px;
            color: #8e8ea0;
        }
        
        .code-block-header .language {
            text-transform: lowercase;
        }
        
        .copy-code-btn {
            display: flex;
            align-items: center;
            gap: 4px;
            background: transparent;
            border: none;
            color: #8e8ea0;
            font-size: 12px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .copy-code-btn:hover {
            background-color: rgba(255,255,255,0.1);
            color: #ececf1;
        }
        
        .copy-code-btn.copied {
            color: #10a37f;
        }
        
        .copy-code-btn svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        .message-content pre code {
            display: block;
            padding: 16px;
            overflow-x: auto;
            background: transparent !important;
        }
        
        /* Tables */
        .message-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        
        .message-content th, .message-content td {
            border: 1px solid rgba(255,255,255,0.1);
            padding: 10px 14px;
            text-align: left;
        }
        
        .message-content th {
            background-color: rgba(255,255,255,0.05);
            font-weight: 600;
        }
        
        .message-content tr:nth-child(even) {
            background-color: rgba(255,255,255,0.02);
        }
        
        /* Typing Indicator */
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background-color: #8e8ea0;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-6px); opacity: 1; }
        }
        
        /* Streaming cursor */
        .streaming-cursor {
            display: inline-block;
            width: 2px;
            height: 1.2em;
            background-color: #ececf1;
            margin-left: 2px;
            animation: blink 1s infinite;
            vertical-align: text-bottom;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
        
        /* Input Area */
        .input-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 16px;
            background: linear-gradient(transparent, #343541 20%);
        }
        
        .input-wrapper {
            max-width: 768px;
            margin: 0 auto;
        }
        
        .input-box {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            background-color: #40414f;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 12px 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .input-box:focus-within {
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 0 0 1px rgba(255,255,255,0.1);
        }
        
        .input-box textarea {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: #ececf1;
            font-size: 16px;
            font-family: inherit;
            line-height: 1.5;
            resize: none;
            max-height: 200px;
            min-height: 24px;
        }
        
        .input-box textarea::placeholder {
            color: #8e8ea0;
        }
        
        .send-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            background-color: #10a37f;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s, opacity 0.2s;
            flex-shrink: 0;
        }
        
        .send-btn:hover:not(:disabled) {
            background-color: #1a7f64;
        }
        
        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .send-btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        .input-footer {
            text-align: center;
            padding: 12px 16px 8px;
            font-size: 12px;
            color: #8e8ea0;
        }
        
        .input-footer a {
            color: #8e8ea0;
            text-decoration: underline;
        }
        
        /* Stop Button */
        .stop-btn {
            display: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background-color: transparent;
            color: #ececf1;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
            flex-shrink: 0;
        }
        
        .stop-btn:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .stop-btn svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }
        
        .is-streaming .stop-btn {
            display: flex;
        }
        
        .is-streaming .send-btn {
            display: none;
        }
        
        /* Error Message */
        .error-message {
            background-color: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            color: #ff6b6b;
            margin: 16px 0;
            font-size: 14px;
        }
        
        /* Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        
        ::-webkit-scrollbar-thumb {
            background-color: rgba(255,255,255,0.2);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255,255,255,0.3);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 100;
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0,0,0,0.5);
                z-index: 99;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .chat-messages {
                padding: 16px 12px 160px;
            }
            
            .message {
                gap: 12px;
                padding: 16px 0;
            }
            
            .message-avatar {
                width: 30px;
                height: 30px;
                font-size: 12px;
            }
            
            .input-container {
                padding: 12px;
            }
            
            .input-box {
                padding: 10px 12px;
            }
        }
        
        /* Model dropdown */
        .model-dropdown {
            position: relative;
        }
        
        .model-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 8px;
            background-color: #2d2d2d;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 8px;
            min-width: 200px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.3);
            z-index: 50;
        }
        
        .model-dropdown-menu.show {
            display: block;
        }
        
        .model-option {
            padding: 10px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .model-option:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .model-option.active {
            background-color: rgba(16, 163, 127, 0.2);
            color: #10a37f;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Overlay (mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <button class="new-chat-btn" id="newChatBtn">
                    <svg viewBox="0 0 24 24">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    New chat
                </button>
            </div>
            
            <div class="sidebar-content">
                <div class="chat-history-title">Recent Chats</div>
                <div id="chatHistory">
                    <!-- Chat history items will be populated here -->
                </div>
            </div>
            
            <div class="sidebar-footer">
                <a href="?logout=1" class="sidebar-footer-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Log out
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="chat-header">
                <button class="menu-toggle" id="menuToggle">
                    <svg viewBox="0 0 24 24">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                
                <div class="model-dropdown">
                    <button class="model-selector" id="modelSelector">
                        <span id="currentModel"><?php echo htmlspecialchars($model); ?></span>
                        <svg viewBox="0 0 24 24">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="model-dropdown-menu" id="modelDropdown">
                        <div class="model-option" data-model="gpt-4o-mini">gpt-4o-mini</div>
                        <div class="model-option" data-model="gpt-4o">gpt-4o</div>
                        <div class="model-option" data-model="gpt-4-turbo">gpt-4-turbo</div>
                        <div class="model-option" data-model="gpt-3.5-turbo">gpt-3.5-turbo</div>
                    </div>
                </div>
                
                <div class="header-actions">
                    <!-- Additional header actions can go here -->
                </div>
            </header>
            
            <!-- Chat Container -->
            <div class="chat-container" id="chatContainer">
                <div class="chat-messages" id="chatMessages">
                    <!-- Empty State -->
                    <div class="empty-state" id="emptyState">
                        <div class="empty-state-logo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"></path>
                            </svg>
                        </div>
                        <h2>How can I help you today?</h2>
                        <p>Start a conversation below</p>
                    </div>
                </div>
            </div>
            
            <!-- Input Area -->
            <div class="input-container">
                <div class="input-wrapper">
                    <div class="input-box" id="inputBox">
                        <textarea 
                            id="messageInput" 
                            placeholder="Message AI..." 
                            rows="1"
                            autofocus
                        ></textarea>
                        <button class="send-btn" id="sendBtn" disabled>
                            <svg viewBox="0 0 24 24">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                        <button class="stop-btn" id="stopBtn">
                            <svg viewBox="0 0 24 24">
                                <rect x="6" y="6" width="12" height="12" rx="2"></rect>
                            </svg>
                        </button>
                    </div>
                    <div class="input-footer">
                        AI can make mistakes. Consider checking important information.
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    $(document).ready(function() {
        // ================================================================
        // STATE MANAGEMENT
        // ================================================================
        let messages = [];
        let isStreaming = false;
        let currentEventSource = null;
        let currentModel = '<?php echo htmlspecialchars($model); ?>';
        let chatHistories = JSON.parse(localStorage.getItem('chatHistories') || '[]');
        let currentChatId = null;
        
        // ================================================================
        // MARKED.JS CONFIGURATION
        // ================================================================
        marked.setOptions({
            highlight: function(code, lang) {
                if (lang && hljs.getLanguage(lang)) {
                    try {
                        return hljs.highlight(code, { language: lang }).value;
                    } catch (e) {}
                }
                return hljs.highlightAuto(code).value;
            },
            breaks: true,
            gfm: true
        });
        
        // Custom renderer for code blocks with copy button
        const renderer = new marked.Renderer();
        renderer.code = function(code, language) {
            const lang = language || 'plaintext';
            let highlighted;
            try {
                highlighted = language && hljs.getLanguage(language) 
                    ? hljs.highlight(code, { language }).value 
                    : hljs.highlightAuto(code).value;
            } catch (e) {
                highlighted = code;
            }
            
            return `<pre><div class="code-block-header"><span class="language">${lang}</span><button class="copy-code-btn" onclick="copyCode(this)"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span>Copy</span></button></div><code class="hljs language-${lang}">${highlighted}</code></pre>`;
        };
        
        marked.use({ renderer });
        
        // ================================================================
        // DOM ELEMENTS
        // ================================================================
        const $chatMessages = $('#chatMessages');
        const $chatContainer = $('#chatContainer');
        const $messageInput = $('#messageInput');
        const $sendBtn = $('#sendBtn');
        const $stopBtn = $('#stopBtn');
        const $inputBox = $('#inputBox');
        const $emptyState = $('#emptyState');
        const $sidebar = $('#sidebar');
        const $sidebarOverlay = $('#sidebarOverlay');
        const $modelDropdown = $('#modelDropdown');
        
        // ================================================================
        // UTILITY FUNCTIONS
        // ================================================================
        function generateId() {
            return 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        function scrollToBottom() {
            $chatContainer.animate({
                scrollTop: $chatContainer[0].scrollHeight
            }, 100);
        }
        
        function autoResizeTextarea() {
            $messageInput.css('height', 'auto');
            $messageInput.css('height', Math.min($messageInput[0].scrollHeight, 200) + 'px');
        }
        
        function updateSendButton() {
            const hasContent = $messageInput.val().trim().length > 0;
            $sendBtn.prop('disabled', !hasContent || isStreaming);
        }
        
        function setStreamingState(streaming) {
            isStreaming = streaming;
            $inputBox.toggleClass('is-streaming', streaming);
            updateSendButton();
        }
        
        function renderMarkdown(text) {
            return marked.parse(text);
        }
        
        function getUserInitial() {
            return 'U';
        }
        
        // ================================================================
        // MESSAGE RENDERING
        // ================================================================
        function appendMessage(role, content, isStreaming = false) {
            $emptyState.hide();
            
            const messageId = 'msg_' + Date.now();
// Inside appendMessage function
            const avatarContent = role === 'user' 
                ? getUserInitial() // Keep this for user
                : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;stroke:white;"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg>`;
            const renderedContent = role === 'user' 
                ? `<p>${escapeHtml(content)}</p>`
                : (isStreaming ? content + '<span class="streaming-cursor"></span>' : renderMarkdown(content));
            
            const messageHtml = `
                <div class="message ${role}" id="${messageId}">
                    <div class="message-avatar">${avatarContent}</div>
                    <div class="message-content">${renderedContent}</div>
                </div>
            `;
            
            $chatMessages.append(messageHtml);
            scrollToBottom();
            
            return messageId;
        }
        
        function updateMessageContent(messageId, content, isFinal = false) {
            const $message = $(`#${messageId} .message-content`);
            if (isFinal) {
                $message.html(renderMarkdown(content));
                // Re-highlight any code blocks
                $message.find('pre code').each(function() {
                    hljs.highlightElement(this);
                });
            } else {
                $message.html(renderMarkdown(content) + '<span class="streaming-cursor"></span>');
            }
            scrollToBottom();
        }
        
        function showError(message) {
            const errorHtml = `<div class="error-message">${escapeHtml(message)}</div>`;
            $chatMessages.append(errorHtml);
            scrollToBottom();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // ================================================================
        // CHAT HISTORY MANAGEMENT
        // ================================================================
        function saveChatHistory() {
            if (messages.length === 0) return;
            
            const title = messages[0].content.substring(0, 50) + (messages[0].content.length > 50 ? '...' : '');
            
            if (!currentChatId) {
                currentChatId = generateId();
            }
            
            const chatData = {
                id: currentChatId,
                title: title,
                messages: messages,
                model: currentModel,
                updatedAt: Date.now()
            };
            
            const existingIndex = chatHistories.findIndex(c => c.id === currentChatId);
            if (existingIndex >= 0) {
                chatHistories[existingIndex] = chatData;
            } else {
                chatHistories.unshift(chatData);
            }
            
            // Keep only last 50 chats
            chatHistories = chatHistories.slice(0, 50);
            localStorage.setItem('chatHistories', JSON.stringify(chatHistories));
            
            renderChatHistory();
        }
        
        function renderChatHistory() {
            const $history = $('#chatHistory');
            $history.empty();
            
            chatHistories.forEach(chat => {
            const isActive = chat.id === currentChatId ? 'active' : '';
            // UPDATED HTML structure below:
            $history.append(`
                    <div class="chat-history-item ${isActive}" data-id="${chat.id}">
                        <span class="chat-history-title-text">${escapeHtml(chat.title)}</span>
                        <button class="delete-chat-btn" title="Delete chat">
                            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                `);
            });
        }
        
        function loadChat(chatId) {
            const chat = chatHistories.find(c => c.id === chatId);
            if (!chat) return;
            
            currentChatId = chat.id;
            messages = [...chat.messages];
            currentModel = chat.model || currentModel;
            
            $('#currentModel').text(currentModel);
            $('.model-option').removeClass('active');
            $(`.model-option[data-model="${currentModel}"]`).addClass('active');
            
            // Re-render messages
            $chatMessages.empty();
            
            if (messages.length === 0) {
                $chatMessages.append($emptyState.show());
            } else {
                $emptyState.hide();
                messages.forEach(msg => {
                    appendMessage(msg.role, msg.content);
                });
            }
            
            renderChatHistory();
            closeSidebar();
        }
        
        function startNewChat() {
            currentChatId = null;
            messages = [];
            $chatMessages.empty();
            $chatMessages.append($emptyState.show());
            renderChatHistory();
            closeSidebar();
            $messageInput.focus();
        }
        
        // ================================================================
        // SIDEBAR MANAGEMENT
        // ================================================================
        function openSidebar() {
            $sidebar.addClass('open');
            $sidebarOverlay.addClass('show');
        }
        
        function closeSidebar() {
            $sidebar.removeClass('open');
            $sidebarOverlay.removeClass('show');
        }
        
        // ================================================================
        // STREAMING API CALL
        // ================================================================
        async function sendMessage(userMessage) {
            // Add user message to UI and state
            messages.push({ role: 'user', content: userMessage });
            appendMessage('user', userMessage);
            saveChatHistory();
            
            // Clear input
            $messageInput.val('');
            autoResizeTextarea();
            updateSendButton();
            
            // Start streaming state
            setStreamingState(true);
            
            // Create assistant message placeholder
            const assistantMsgId = appendMessage('assistant', '', true);
            let assistantContent = '';
            
            try {
                // Prepare messages for API
                const apiMessages = messages.map(m => ({
                    role: m.role,
                    content: m.content
                }));
                
                // Create fetch request with streaming
                const response = await fetch('?stream=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        messages: apiMessages,
                        model: currentModel
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                while (true) {
                    const { done, value } = await reader.read();
                    
                    if (done) break;
                    
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.slice(6);
                            
                            if (data === '[DONE]') {
                                continue;
                            }
                            
                            try {
                                const parsed = JSON.parse(data);
                                
                                if (parsed.error) {
                                    throw new Error(parsed.error);
                                }
                                
                                if (parsed.content) {
                                    assistantContent += parsed.content;
                                    updateMessageContent(assistantMsgId, assistantContent);
                                }
                            } catch (e) {
                                // Skip invalid JSON lines
                            }
                        }
                    }
                }
                
                // Finalize message
                if (assistantContent) {
                    messages.push({ role: 'assistant', content: assistantContent });
                    updateMessageContent(assistantMsgId, assistantContent, true);
                    saveChatHistory();
                } else {
                    $(`#${assistantMsgId}`).remove();
                }
                
            } catch (error) {
                console.error('Stream error:', error);
                $(`#${assistantMsgId}`).remove();
                showError('Error: ' + error.message);
            } finally {
                setStreamingState(false);
            }
        }
        
        function stopStreaming() {
            if (currentEventSource) {
                currentEventSource.close();
                currentEventSource = null;
            }
            setStreamingState(false);
        }
        
        // ================================================================
        // EVENT HANDLERS
        // ================================================================
        
        // Send message
        $sendBtn.on('click', function() {
            const message = $messageInput.val().trim();
            if (message && !isStreaming) {
                sendMessage(message);
            }
        });
        
        // Enter to send, Shift+Enter for new line
        $messageInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const message = $(this).val().trim();
                if (message && !isStreaming) {
                    sendMessage(message);
                }
            }
        });
        
        // Auto-resize textarea
        $messageInput.on('input', function() {
            autoResizeTextarea();
            updateSendButton();
        });
        
        // Stop streaming
        $stopBtn.on('click', stopStreaming);
        
        // New chat
        $('#newChatBtn').on('click', startNewChat);
        
        // Load chat from history
        $('#chatHistory').on('click', '.chat-history-item', function(e) {
            if ($(e.target).closest('.delete-chat-btn').length) return;
            const chatId = $(this).data('id');
            loadChat(chatId);
        });

        $('#chatHistory').on('click', '.delete-chat-btn', function(e) {
            e.stopPropagation(); // Stop the click from opening the chat
            
            const $item = $(this).closest('.chat-history-item');
            const chatId = $item.data('id');
            
            if (confirm('Are you sure you want to delete this chat?')) {
                // 1. Remove from array
                chatHistories = chatHistories.filter(c => c.id !== chatId);
                
                // 2. Update LocalStorage
                localStorage.setItem('chatHistories', JSON.stringify(chatHistories));
                
                // 3. Check if we deleted the currently active chat
                if (currentChatId === chatId) {
                    startNewChat(); // Clear the screen
                } else {
                    renderChatHistory(); // Just refresh the list
                }
            }
        });
        
        // Mobile menu toggle
        $('#menuToggle').on('click', function() {
            if ($sidebar.hasClass('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        
        $sidebarOverlay.on('click', closeSidebar);
        
        // Model selector
        $('#modelSelector').on('click', function(e) {
            e.stopPropagation();
            $modelDropdown.toggleClass('show');
        });
        
        $('.model-option').on('click', function() {
            const model = $(this).data('model');
            currentModel = model;
            $('#currentModel').text(model);
            $('.model-option').removeClass('active');
            $(this).addClass('active');
            $modelDropdown.removeClass('show');
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function() {
            $modelDropdown.removeClass('show');
        });
        
        // ================================================================
        // INITIALIZATION
        // ================================================================
        renderChatHistory();
        updateSendButton();
        
        // Set initial active model
        $('.model-option').removeClass('active');
        $(`.model-option[data-model="${currentModel}"]`).addClass('active');
        
        // Focus input
        $messageInput.focus();
    });
    
    // ================================================================
    // GLOBAL FUNCTIONS
    // ================================================================
    function copyCode(btn) {
        const code = $(btn).closest('pre').find('code').text();
        navigator.clipboard.writeText(code).then(() => {
            const $btn = $(btn);
            $btn.addClass('copied');
            $btn.find('span').text('Copied!');
            
            setTimeout(() => {
                $btn.removeClass('copied');
                $btn.find('span').text('Copy');
            }, 2000);
        });
    }
    </script>
</body>
</html>