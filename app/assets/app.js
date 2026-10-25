(() => {
  const $ = (id) => document.getElementById(id);
  const chat = $('chat');
  const text = $('text');
  const micBtn = $('micBtn');
  const sendBtn = $('sendBtn');
  const status = $('status');

  let messages = [];      // lịch sử gửi cho AI: {role, content}
  let busy = false;

  // ---------- Cài đặt (lưu trong trình duyệt) ----------
  const settingIds = ['level', 'scenario', 'customScenario', 'showPinyin', 'showVi', 'autoSpeak', 'handsFree', 'rate', 'accessCode'];
  const store = {
    get(k) { try { return localStorage.getItem('zh_' + k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem('zh_' + k, v); } catch {} },
  };
  for (const id of settingIds) {
    const el = $(id);
    const saved = store.get(id);
    if (saved !== null) el.type === 'checkbox' ? (el.checked = saved === '1') : (el.value = saved);
    el.addEventListener('change', () => {
      store.set(id, el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value);
      applySettings();
    });
  }
  $('rate').addEventListener('input', applySettings);

  function applySettings() {
    document.body.classList.toggle('no-pinyin', !$('showPinyin').checked);
    document.body.classList.toggle('no-vi', !$('showVi').checked);
    $('customScenario').classList.toggle('hidden', $('scenario').value !== 'custom');
    $('rateLabel').textContent = Number($('rate').value).toFixed(1) + 'x';
  }
  applySettings();

  const scenarioText = () => $('scenario').value === 'custom' ? $('customScenario').value : $('scenario').value;

  $('menuBtn').onclick = () => document.body.classList.toggle('menu-open');
  chat.addEventListener('click', () => document.body.classList.remove('menu-open'));

  $('newChat').onclick = () => {
    stopSpeaking();
    messages = [];
    chat.innerHTML = '';
    addBubble('ai', { reply_zh: '好，我们重新开始吧！你想聊什么？', pinyin: 'Hǎo, wǒmen chóngxīn kāishǐ ba! Nǐ xiǎng liáo shénme?', vi: 'Được, mình bắt đầu lại nhé! Bạn muốn nói chuyện gì?' });
    document.body.classList.remove('menu-open');
  };

  document.querySelectorAll('.starters button').forEach((b) => {
    b.onclick = () => { text.value = b.textContent.replace('…', ''); text.focus(); };
  });

  // ---------- Hiển thị tin nhắn ----------
  function el(tag, cls, content) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (content !== undefined) e.textContent = content;
    return e;
  }

  function addBubble(role, data) {
    chat.querySelector('.welcome')?.remove();
    const box = el('div', 'msg ' + role);
    if (role === 'user') {
      box.textContent = data;
    } else {
      box.append(el('div', 'zh', data.reply_zh));
      if (data.pinyin) box.append(el('div', 'pinyin', data.pinyin));
      if (data.vi) box.append(el('div', 'vi', data.vi));
      const c = data.correction;
      if (c && c.has_error && c.corrected) {
        const fix = el('div', 'fix');
        fix.append('Sửa lỗi: ');
        if (c.original) { fix.append(el('s', '', c.original)); fix.append(' → '); }
        fix.append(el('b', '', c.corrected));
        if (c.explain_vi) { fix.append(document.createElement('br')); fix.append(c.explain_vi); }
        box.append(fix);
      }
      const actions = el('div', 'actions');
      const play = el('button', '', '🔊 Nghe lại');
      play.onclick = () => speak(data.reply_zh);
      actions.append(play);
      if (c && c.has_error && c.corrected) {
        const playFix = el('button', '', '🔊 Nghe câu đúng');
        playFix.onclick = () => speak(c.corrected);
        actions.append(playFix);
      }
      const copy = el('button', '', 'Sao chép');
      copy.onclick = () => navigator.clipboard?.writeText(data.reply_zh);
      actions.append(copy);
      box.append(actions);
    }
    chat.append(box);
    chat.scrollTop = chat.scrollHeight;
    return box;
  }

  // ---------- Gửi tin ----------
  async function send(content) {
    content = content.trim();
    if (!content || busy) return;
    busy = true;
    sendBtn.disabled = true;
    stopListening();
    stopSpeaking();
    text.value = '';
    autoGrow();

    addBubble('user', content);
    messages.push({ role: 'user', content });
    const typing = el('div', 'msg ai typing', '小老师 đang trả lời');
    chat.append(typing);
    chat.scrollTop = chat.scrollHeight;

    try {
      const res = await fetch('api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          messages,
          level: $('level').value,
          scenario: scenarioText(),
          access_code: $('accessCode').value,
        }),
      });
      const data = await res.json().catch(() => ({ error: 'Máy chủ trả về dữ liệu không hợp lệ (HTTP ' + res.status + ')' }));
      typing.remove();
      if (!res.ok || data.error) {
        messages.pop();
        if (res.status === 401) document.body.classList.add('menu-open');
        chat.append(el('div', 'error', '⚠ ' + (data.error || 'Lỗi ' + res.status)));
        return;
      }
      messages.push({ role: 'assistant', content: data.raw || data.reply_zh });
      addBubble('ai', data);
      if ($('autoSpeak').checked || $('handsFree').checked) speak(data.reply_zh, () => {
        if ($('handsFree').checked) startListening();
      });
    } catch (err) {
      typing.remove();
      messages.pop();
      const local = /^(localhost|127\.0\.0\.1)$/.test(location.hostname);
      chat.append(el('div', 'error', local
        ? '⚠ Máy chủ app đã tắt. Bấm đúp chay-thu.bat để bật lại rồi gửi lại câu này.'
        : '⚠ Không kết nối được máy chủ. Kiểm tra mạng rồi thử lại.'));
    } finally {
      busy = false;
      sendBtn.disabled = false;
      chat.scrollTop = chat.scrollHeight;
    }
  }

  $('composer').addEventListener('submit', (e) => { e.preventDefault(); send(text.value); });
  text.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(text.value); }
  });
  function autoGrow() { text.style.height = 'auto'; text.style.height = text.scrollHeight + 'px'; }
  text.addEventListener('input', autoGrow);

  // ---------- Đọc thành tiếng (TTS) ----------
  let zhVoice = null;
  function pickVoice() {
    const voices = speechSynthesis.getVoices();
    zhVoice = voices.find((v) => /zh[-_]CN/i.test(v.lang) && /Xiaoxiao|Natural|Online|Google/i.test(v.name))
      || voices.find((v) => /zh[-_]CN/i.test(v.lang))
      || voices.find((v) => /^zh/i.test(v.lang)) || null;
  }
  if ('speechSynthesis' in window) {
    pickVoice();
    speechSynthesis.onvoiceschanged = pickVoice;
  }

  function speak(str, onEnd) {
    if (!('speechSynthesis' in window) || !str) { onEnd?.(); return; }
    speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(str);
    u.lang = 'zh-CN';
    if (zhVoice) u.voice = zhVoice;
    u.rate = Number($('rate').value);
    u.onend = () => { status.textContent = ''; onEnd?.(); };
    u.onerror = () => { status.textContent = ''; };
    status.textContent = '🔊 Đang đọc…';
    speechSynthesis.speak(u);
  }
  function stopSpeaking() {
    if ('speechSynthesis' in window) speechSynthesis.cancel();
  }

  // ---------- Nhận dạng giọng nói (STT) ----------
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  let rec = null;
  let listening = false;

  if (!SR) {
    micBtn.title = 'Trình duyệt này chưa hỗ trợ nói. Hãy dùng Chrome hoặc Edge.';
    micBtn.style.opacity = 0.4;
  }

  function startListening() {
    if (!SR) { status.textContent = 'Trình duyệt này chưa hỗ trợ nhận dạng giọng nói, hãy dùng Chrome hoặc Edge.'; return; }
    if (listening || busy) return;
    stopSpeaking();
    rec = new SR();
    rec.lang = 'zh-CN';
    rec.interimResults = true;
    rec.continuous = false;
    let finalText = '';
    rec.onresult = (e) => {
      let interim = '';
      for (let i = e.resultIndex; i < e.results.length; i++) {
        const t = e.results[i][0].transcript;
        e.results[i].isFinal ? (finalText += t) : (interim += t);
      }
      text.value = finalText + interim;
      autoGrow();
    };
    rec.onerror = (e) => {
      if (e.error === 'not-allowed') status.textContent = 'Bạn cần cho phép trình duyệt dùng micro.';
      else if (e.error === 'no-speech') status.textContent = 'Không nghe thấy gì, bấm micro để thử lại.';
      else status.textContent = 'Lỗi micro: ' + e.error;
    };
    rec.onend = () => {
      listening = false;
      micBtn.classList.remove('listening');
      if (status.textContent.startsWith('🎙')) status.textContent = '';
      if (finalText.trim()) send(finalText);
    };
    rec.start();
    listening = true;
    micBtn.classList.add('listening');
    status.textContent = '🎙 Đang nghe… hãy nói tiếng Trung';
  }
  function stopListening() {
    if (rec && listening) rec.stop();
  }

  micBtn.onclick = () => (listening ? stopListening() : startListening());

  // ---------- Cài app (PWA) ----------
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});

  const installBtn = $('installBtn');
  let installPrompt = null;
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    installPrompt = e;
    installBtn.classList.remove('hidden');
  });
  installBtn.onclick = async () => {
    if (!installPrompt) return;
    installPrompt.prompt();
    await installPrompt.userChoice;
    installPrompt = null;
    installBtn.classList.add('hidden');
  };
  window.addEventListener('appinstalled', () => installBtn.classList.add('hidden'));
  if (!standalone && /iPhone|iPad|iPod/i.test(navigator.userAgent)) $('iosHint').classList.remove('hidden');
})();
