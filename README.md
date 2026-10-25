# 小老师 – Luyện nói tiếng Trung với AI

Trang web cho học viên nói tiếng Trung với AI (giống Doubao): nói qua micro, AI trả lời bằng giọng nói, kèm pinyin, bản dịch tiếng Việt và sửa lỗi.

- Trang giới thiệu: `index.html` (trang chủ)
- App luyện nói: `app/` – HTML/CSS/JS thuần (`app/index.html`, `app/assets/`)
- Máy chủ: 1 file PHP (`app/api/chat.php`) giữ API key và gọi **Claude** hoặc **Doubao**
- Nhận dạng giọng nói & đọc: dùng sẵn của trình duyệt (Chrome / Edge, cả trên Android)

## 1. Đưa code lên GitHub

1. Cài Git: https://git-scm.com/download/win
2. Tạo repo mới (trống) trên https://github.com/new, ví dụ `luyen-noi-tieng-trung`.
3. Mở PowerShell trong thư mục này và chạy:

```bash
git init
git add .
git commit -m "Trang luyện nói tiếng Trung với AI"
git branch -M main
git remote add origin https://github.com/<tên-github>/luyen-noi-tieng-trung.git
git push -u origin main
```

`app/api/config.php` (chứa API key) đã nằm trong `.gitignore` nên sẽ không bị đẩy lên.

## 2. Host trên Hostinger (gói Web/Cloud hosting có PHP)

1. hPanel → **Websites** → chọn website → **Advanced → Git**.
2. Ở **Create a New Repository**: dán URL repo GitHub, branch `main`, ô *Directory* để trống (cài vào `public_html`) → **Create**. Nếu repo là private, bấm tạo SSH key ở đó và thêm vào GitHub → repo → Settings → Deploy keys.
3. Bấm **Deploy**. (Tuỳ chọn: bật *Auto Deployment* và thêm webhook URL vào GitHub để mỗi lần push là web tự cập nhật.)
4. Vào **File Manager** → `public_html/app/api/` → copy `config.sample.php` thành `config.php` → mở ra điền:
   - `provider`: `'claude'` hoặc `'doubao'`
   - API key tương ứng
   - `access_code`: mã bạn phát cho học viên (khuyên nên đặt)
5. Bật **SSL** cho tên miền (hPanel → Security → SSL). **Micro chỉ hoạt động trên https.**

## Lấy API key

- **Claude**: https://console.anthropic.com → API Keys. Model mặc định `claude-opus-5-5`, `claude_effort` = `low` để trả lời nhanh.
- **Doubao**: https://console.volcengine.com/ark → API Key; `doubao_model` là tên model hoặc Endpoint ID (`ep-...`).

## Cài app lên điện thoại / máy tính

App là PWA: sau khi web đã chạy trên https, vào `https://<tên-miền>/app/`:

- **Android (Chrome)**: bấm nút "Cài ứng dụng lên máy" trong menu, hoặc ⋮ → *Cài đặt ứng dụng*.
- **PC (Chrome / Edge)**: bấm biểu tượng cài đặt ⊕ ở thanh địa chỉ, hoặc nút "Cài ứng dụng lên máy".
- **iPhone (Safari)**: nút Chia sẻ → *Thêm vào MH chính*.

App có biểu tượng 师 riêng và mở toàn màn hình. Vẫn cần Internet để nói chuyện với AI.

## Ghi chú

- Học viên nên dùng **Chrome hoặc Edge**. Safari/Firefox vẫn gõ chữ và nghe đọc được, nhưng nói qua micro có thể không hoạt động.
- Giọng đọc phụ thuộc máy học viên; Edge trên Windows có giọng "Xiaoxiao Online (Natural)" rất tự nhiên.
- *Chế độ gọi điện*: sau khi AI đọc xong, micro tự bật lại – luyện liên tục không cần bấm.
