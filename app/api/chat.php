<?php
// Proxy giữa trình duyệt và AI: giữ API key ở server, không lộ ra cho học viên.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed');
set_time_limit(120);

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) fail(500, 'Chưa có api/config.php trên server. Hãy copy config.sample.php thành config.php và điền API key.');
$config = require $configFile;

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) fail(400, 'Dữ liệu gửi lên không hợp lệ');

if (!empty($config['access_code']) && !hash_equals((string)$config['access_code'], (string)($input['access_code'] ?? ''))) {
    fail(401, 'Sai mã truy cập');
}

check_rate_limit((int)($config['rate_limit_per_hour'] ?? 120));

// ---- Kiểm tra & chuẩn hoá lịch sử hội thoại ----
$history = [];
foreach (array_slice((array)($input['messages'] ?? []), -20) as $m) {
    $role = $m['role'] ?? '';
    $content = trim((string)($m['content'] ?? ''));
    if (!in_array($role, ['user', 'assistant'], true) || $content === '') continue;
    $history[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
}
// Claude yêu cầu tin đầu tiên là của user
while ($history && $history[0]['role'] !== 'user') array_shift($history);
if (!$history || end($history)['role'] !== 'user') fail(400, 'Thiếu tin nhắn của học viên');

$level = preg_replace('/[^A-Za-z0-9 ]/', '', (string)($input['level'] ?? 'HSK2'));
$scenario = mb_substr(strip_tags((string)($input['scenario'] ?? '')), 0, 300);
$system = build_system_prompt($level, $scenario);

$raw = match ($config['provider'] ?? 'claude') {
    'doubao' => call_doubao($config, $system, $history),
    'gemini' => call_gemini($config, $system, $history),
    'demo' => call_demo($history),
    default => call_claude($config, $system, $history),
};

echo json_encode(parse_reply($raw), JSON_UNESCAPED_UNICODE);


function build_system_prompt(string $level, string $scenario): string {
    $scene = $scenario !== '' ? "Tình huống luyện tập: {$scenario}. Hãy nhập vai phù hợp với tình huống này." : 'Trò chuyện tự do về cuộc sống hằng ngày.';
    return <<<TXT
Bạn là "小老师", gia sư tiếng Trung thân thiện, đang luyện NÓI với học viên người Việt Nam qua giọng nói.
Trình độ học viên: {$level}. Chỉ dùng từ vựng và ngữ pháp phù hợp trình độ này.
{$scene}

Quy tắc:
- Câu trả lời tiếng Trung ngắn gọn như hội thoại thật (1–3 câu), luôn kết thúc bằng một câu hỏi hoặc gợi ý để học viên nói tiếp.
- Tin nhắn của học viên đến từ nhận dạng giọng nói nên có thể sai chữ đồng âm; hãy đoán ý họ muốn nói.
- Nếu học viên nói sai ngữ pháp, dùng từ chưa tự nhiên, hoặc nói tiếng Việt, hãy điền phần correction: câu gốc, câu đúng/tự nhiên hơn bằng tiếng Trung, và giải thích ngắn hoàn toàn bằng tiếng Việt (không chen tiếng Anh; chỉ dùng chữ Hán khi trích ví dụ). Nếu không có lỗi đáng kể thì has_error = false và các trường còn lại để chuỗi rỗng.
- Không dùng emoji, markdown hay ký hiệu đặc biệt trong reply_zh vì nó sẽ được đọc thành tiếng.

Luôn trả lời đúng một đối tượng JSON dạng:
{"reply_zh": "...", "pinyin": "...", "vi": "...", "correction": {"has_error": false, "original": "", "corrected": "", "explain_vi": ""}}
trong đó pinyin có dấu thanh cho reply_zh, vi là bản dịch tiếng Việt của reply_zh.
TXT;
}

function reply_schema(): array {
    return [
        'type' => 'object',
        'properties' => [
            'reply_zh' => ['type' => 'string'],
            'pinyin' => ['type' => 'string'],
            'vi' => ['type' => 'string'],
            'correction' => [
                'type' => 'object',
                'properties' => [
                    'has_error' => ['type' => 'boolean'],
                    'original' => ['type' => 'string'],
                    'corrected' => ['type' => 'string'],
                    'explain_vi' => ['type' => 'string'],
                ],
                'required' => ['has_error', 'original', 'corrected', 'explain_vi'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['reply_zh', 'pinyin', 'vi', 'correction'],
        'additionalProperties' => false,
    ];
}

function call_claude(array $config, string $system, array $history): string {
    $body = [
        'model' => $config['claude_model'] ?? 'claude-opus-5-5',
        'max_tokens' => 16000,
        'system' => $system,
        'messages' => $history,
        'output_config' => [
            'effort' => $config['claude_effort'] ?? 'low',
            'format' => ['type' => 'json_schema', 'schema' => reply_schema()],
        ],
        // Nếu bị bộ lọc an toàn từ chối, server tự chuyển sang model dự phòng
        'fallbacks' => 'default',
    ];
    [$status, $res] = http_post_json('https://api.anthropic.com/v1/messages', [
        'x-api-key: ' . ($config['claude_api_key'] ?? ''),
        'anthropic-version: 2026-10-21',
        'anthropic-beta: server-side-fallback-2026-07-01',
    ], $body);

    if ($status !== 200) fail(502, 'Lỗi từ Claude API (' . $status . '): ' . ($res['error']['message'] ?? 'không rõ'));
    if (($res['stop_reason'] ?? '') === 'refusal') {
        return json_encode(['reply_zh' => '我们换个话题吧。', 'pinyin' => 'Wǒmen huàn ge huàtí ba.', 'vi' => 'Chúng ta đổi chủ đề nhé.', 'correction' => ['has_error' => false]], JSON_UNESCAPED_UNICODE);
    }
    $text = '';
    foreach ($res['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    return $text;
}

function call_doubao(array $config, string $system, array $history): string {
    if (empty($config['doubao_api_key']) || empty($config['doubao_model'])) {
        fail(500, 'Chưa điền doubao_api_key hoặc doubao_model trong api/config.php');
    }
    $base = rtrim($config['doubao_base_url'] ?? 'https://ark.ap-southeast.bytepluses.com/api/v3', '/');
    $body = [
        'model' => $config['doubao_model'] ?? '',
        'messages' => array_merge([['role' => 'system', 'content' => $system]], $history),
    ];
    [$status, $res] = http_post_json($base . '/chat/completions', [
        'Authorization: Bearer ' . ($config['doubao_api_key'] ?? ''),
    ], $body);

    if ($status !== 200) fail(502, 'Lỗi từ Doubao API (' . $status . '): ' . ($res['error']['message'] ?? 'không rõ'));
    return (string)($res['choices'][0]['message']['content'] ?? '');
}

function call_gemini(array $config, string $system, array $history): string {
    if (empty($config['gemini_api_key'])) fail(500, 'Chưa điền gemini_api_key trong api/config.php');
    $model = rawurlencode($config['gemini_model'] ?? 'gemini-2.5-flash');
    $contents = array_map(fn($m) => [
        'role' => $m['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => $m['content']]],
    ], $history);
    $body = [
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents' => $contents,
        'generationConfig' => ['responseMimeType' => 'application/json'],
    ];
    // Hội thoại cần trả lời nhanh: giảm mức "suy nghĩ" (Gemini 3 trở lên)
    if (!empty($config['gemini_thinking'])) {
        $body['generationConfig']['thinkingConfig'] = ['thinkingLevel' => $config['gemini_thinking']];
    }
    [$status, $res] = http_post_json("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
        'x-goog-api-key: ' . $config['gemini_api_key'],
    ], $body);

    if ($status !== 200) fail(502, 'Lỗi từ Gemini API (' . $status . '): ' . ($res['error']['message'] ?? 'không rõ'));
    if (!empty($res['promptFeedback']['blockReason'])) {
        return json_encode(['reply_zh' => '我们换个话题吧。', 'pinyin' => 'Wǒmen huàn ge huàtí ba.', 'vi' => 'Chúng ta đổi chủ đề nhé.', 'correction' => ['has_error' => false]], JSON_UNESCAPED_UNICODE);
    }
    $text = '';
    foreach ($res['candidates'][0]['content']['parts'] ?? [] as $part) {
        if (empty($part['thought'])) $text .= $part['text'] ?? '';
    }
    return $text;
}

// Chế độ demo: không gọi AI, trả lời mẫu theo lượt để thử micro và giọng đọc miễn phí.
function call_demo(array $history): string {
    $replies = [
        ['你好！我是小老师。你叫什么名字？', 'Nǐ hǎo! Wǒ shì Xiǎo lǎoshī. Nǐ jiào shénme míngzi?', 'Xin chào! Mình là Tiểu lão sư. Bạn tên là gì?'],
        ['很高兴认识你！你是哪国人？', 'Hěn gāoxìng rènshi nǐ! Nǐ shì nǎ guó rén?', 'Rất vui được biết bạn! Bạn là người nước nào?'],
        ['你学汉语多长时间了？', 'Nǐ xué Hànyǔ duō cháng shíjiān le?', 'Bạn học tiếng Trung bao lâu rồi?'],
        ['你说得很好！你喜欢吃中国菜吗？', 'Nǐ shuō de hěn hǎo! Nǐ xǐhuan chī Zhōngguó cài ma?', 'Bạn nói giỏi lắm! Bạn có thích ăn món Trung Quốc không?'],
    ];
    $turn = count(array_filter($history, fn($m) => $m['role'] === 'user')) - 1;
    [$zh, $py, $vi] = $replies[$turn % count($replies)];
    return json_encode([
        'reply_zh' => $zh,
        'pinyin' => $py,
        'vi' => $vi . ' (Chế độ demo: chưa kết nối AI thật)',
        'correction' => ['has_error' => false, 'original' => '', 'corrected' => '', 'explain_vi' => ''],
    ], JSON_UNESCAPED_UNICODE);
}

function http_post_json(string $url, array $headers, array $body): array {
    $ch = curl_init($url);
    // PHP trên Windows không kèm chứng chỉ HTTPS; dùng kho chứng chỉ của Windows khi chạy thử trên máy.
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $out = curl_exec($ch);
    if ($out === false) fail(502, 'Không kết nối được tới AI: ' . curl_error($ch));
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($out, true) ?? []];
}

// Model đôi khi bọc JSON trong ```; lấy đoạn {...} ngoài cùng rồi parse.
function parse_reply(string $raw): array {
    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    $data = ($start !== false && $end > $start) ? json_decode(substr($raw, $start, $end - $start + 1), true) : null;
    if (!is_array($data) || empty($data['reply_zh'])) {
        return ['reply_zh' => trim($raw), 'pinyin' => '', 'vi' => '', 'correction' => ['has_error' => false], 'raw' => trim($raw)];
    }
    $data['raw'] = json_encode($data, JSON_UNESCAPED_UNICODE);
    return $data;
}

function check_rate_limit(int $perHour): void {
    if ($perHour <= 0) return;
    $file = sys_get_temp_dir() . '/zh_chat_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x');
    $now = time();
    $hits = array_filter(
        file_exists($file) ? (array)json_decode((string)file_get_contents($file), true) : [],
        fn($t) => $t > $now - 3600
    );
    if (count($hits) >= $perHour) fail(429, 'Bạn đã nói quá nhiều trong 1 giờ, nghỉ một chút rồi quay lại nhé!');
    $hits[] = $now;
    file_put_contents($file, json_encode(array_values($hits)), LOCK_EX);
}
