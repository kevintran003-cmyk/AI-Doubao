<?php
// Copy file này thành config.php (cùng thư mục) rồi điền API key.
// config.php đã nằm trong .gitignore nên KHÔNG bị đẩy lên GitHub.

return [
    // 'gemini', 'claude', 'doubao', hoặc 'demo' (thử giao diện miễn phí, không gọi AI)
    'provider' => 'gemini',

    // --- Gemini (Google) --- lấy key miễn phí tại https://aistudio.google.com/apikey
    'gemini_api_key' => '',
    // flash-lite trả lời ~1-2 giây, hợp với hội thoại. 'gemini-3.8-flash' kỹ hơn nhưng rất chậm.
    'gemini_model'   => 'gemini-3.5-flash-lite',
    // Mức suy nghĩ cho model không phải lite: 'low' hoặc 'high'. Để trống = mặc định của model.
    'gemini_thinking' => '',

    // --- Claude (Anthropic) --- lấy key tại https://console.anthropic.com
    'claude_api_key' => 'sk-ant-...',
    'claude_model'   => 'claude-opus-5-5',
    // low = trả lời nhanh, phù hợp hội thoại; có thể đổi thành 'medium'
    'claude_effort'  => 'low',

    // --- Doubao / Seed ---
    // Quốc tế (dễ đăng ký từ Việt Nam): BytePlus ModelArk https://console.byteplus.com/ark
    //   base_url: https://ark.ap-southeast.bytepluses.com/api/v3
    // Trung Quốc (cần xác minh danh tính TQ): Volcengine https://console.volcengine.com/ark
    //   base_url: https://ark.cn-beijing.volces.com/api/v3
    'doubao_api_key' => '',
    // Copy "Model ID" (hoặc Endpoint ID ep-xxxx) từ trang Model của console
    'doubao_model'   => '',
    'doubao_base_url' => 'https://ark.ap-southeast.bytepluses.com/api/v3',

    // Mã truy cập cho học viên (để trống = ai cũng dùng được).
    // Nên đặt để người lạ không dùng hết tiền API của bạn.
    'access_code' => '',

    // Giới hạn số tin nhắn mỗi IP mỗi giờ
    'rate_limit_per_hour' => 120,
];
