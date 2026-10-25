<?php
// Copy file này thành config.php (cùng thư mục) rồi điền API key.
// config.php đã nằm trong .gitignore nên KHÔNG bị đẩy lên GitHub.

return [
    // 'claude' hoặc 'doubao'
    'provider' => 'claude',

    // --- Claude (Anthropic) --- lấy key tại https://console.anthropic.com
    'claude_api_key' => 'sk-ant-...',
    'claude_model'   => 'claude-opus-5-5',
    // low = trả lời nhanh, phù hợp hội thoại; có thể đổi thành 'medium'
    'claude_effort'  => 'low',

    // --- Doubao (Volcengine Ark) --- lấy key tại https://console.volcengine.com/ark
    'doubao_api_key' => '',
    // Tên model hoặc Endpoint ID (ep-xxxx) bạn đã tạo trong Ark
    'doubao_model'   => 'doubao-seed-1-6-250615',
    'doubao_base_url' => 'https://ark.cn-beijing.volces.com/api/v3',

    // Mã truy cập cho học viên (để trống = ai cũng dùng được).
    // Nên đặt để người lạ không dùng hết tiền API của bạn.
    'access_code' => '',

    // Giới hạn số tin nhắn mỗi IP mỗi giờ
    'rate_limit_per_hour' => 120,
];
