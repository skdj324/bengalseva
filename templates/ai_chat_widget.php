<!-- BengalSeva Floating AI Customer Support Chat Widget -->
<div id="aiChatWidgetContainer">
    <!-- Floating Toggle Button -->
    <button type="button" id="aiChatToggleBtn" class="shadow-lg" onclick="toggleAIChatWindow()" title="Chat with BengalSeva AI Support">
        <i class="bi bi-robot fs-4" id="aiIconOpen"></i>
        <i class="bi bi-x-lg fs-4 d-none" id="aiIconClose"></i>
        <span class="d-none d-md-inline ms-1 fw-bold">AI Support</span>
    </button>

    <!-- Chat Box Window -->
    <div id="aiChatWindow" class="card border-0 shadow-lg rounded-4 d-none">
        <!-- Header -->
        <div class="card-header bg-dark text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                    <i class="bi bi-robot"></i>
                </div>
                <div>
                    <strong class="d-block text-white" style="font-size:0.9rem;">BengalSeva AI Assistant</strong>
                    <small class="text-success" style="font-size:0.75rem;"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i> Online 24x7</small>
                </div>
            </div>
            <button type="button" class="btn btn-sm text-white-50 p-0 fs-5" onclick="toggleAIChatWindow()">&times;</button>
        </div>

        <!-- Chat Messages Body -->
        <div class="card-body p-3 overflow-y-auto" id="aiChatBody" style="height: 320px; background: #f8fafc;">
            <div class="chat-bubble ai-bubble">
                <small class="d-block fw-bold text-primary mb-1">BengalSeva AI</small>
                <span>Namaskar! Welcome to BengalSeva. How can I help you today with bike rides, grocery orders, doctor appointments or local services in West Bengal? (English / বাংলা / हिन्दी)</span>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div id="aiTypingIndicator" class="px-3 py-1 text-muted small d-none" style="background:#f8fafc;">
            <span class="spinner-grow spinner-grow-sm text-primary me-1" role="status"></span> AI is thinking...
        </div>

        <!-- Input Footer -->
        <div class="card-footer bg-white p-2 border-top rounded-bottom-4">
            <form id="aiChatForm" onsubmit="sendAIMessage(event)">
                <div class="input-group">
                    <input type="text" id="aiUserInput" class="form-control border-0 bg-light" placeholder="Ask anything in English, বাংলা, हिन्दी..." autocomplete="off" required>
                    <button type="submit" class="btn btn-primary fw-bold px-3">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#aiChatWidgetContainer {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
}
#aiChatToggleBtn {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: #ffffff;
    border: none;
    border-radius: 50px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
#aiChatToggleBtn:hover {
    transform: scale(1.04);
}
#aiChatWindow {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 360px;
    max-width: 90vw;
    border: 1px solid #e2e8f0 !important;
}
.chat-bubble {
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 0.85rem;
    margin-bottom: 10px;
    line-height: 1.4;
    word-break: break-word;
}
.ai-bubble {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 2px;
}
.user-bubble {
    background: #0d6efd;
    color: #ffffff;
    margin-left: auto;
    border-top-right-radius: 2px;
    max-width: 85%;
}
</style>

<script>
function toggleAIChatWindow() {
    const win = document.getElementById('aiChatWindow');
    const openIcon = document.getElementById('aiIconOpen');
    const closeIcon = document.getElementById('aiIconClose');

    if (win.classList.contains('d-none')) {
        win.classList.remove('d-none');
        openIcon.classList.add('d-none');
        closeIcon.classList.remove('d-none');
        document.getElementById('aiUserInput').focus();
    } else {
        win.classList.add('d-none');
        openIcon.classList.remove('d-none');
        closeIcon.classList.add('d-none');
    }
}

function sendAIMessage(e) {
    e.preventDefault();
    const input = document.getElementById('aiUserInput');
    const msg = input.value.trim();
    if (!msg) return;

    const chatBody = document.getElementById('aiChatBody');

    // Append User Message
    const userDiv = document.createElement('div');
    userDiv.className = 'chat-bubble user-bubble';
    userDiv.innerText = msg;
    chatBody.appendChild(userDiv);

    input.value = '';
    chatBody.scrollTop = chatBody.scrollHeight;

    // Show Typing Indicator
    const indicator = document.getElementById('aiTypingIndicator');
    indicator.classList.remove('d-none');

    const fd = new FormData();
    fd.append('message', msg);

    fetch('<?= APP_URL ?>/api/gemini-chat.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            indicator.classList.add('d-none');
            const aiDiv = document.createElement('div');
            aiDiv.className = 'chat-bubble ai-bubble';
            aiDiv.innerHTML = `<small class="d-block fw-bold text-primary mb-1">BengalSeva AI</small><span>${(data.reply || '').replace(/\n/g, '<br>')}</span>`;
            chatBody.appendChild(aiDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
        })
        .catch(() => {
            indicator.classList.add('d-none');
            const errDiv = document.createElement('div');
            errDiv.className = 'chat-bubble ai-bubble text-danger';
            errDiv.innerText = 'Unable to reach AI assistant. Please contact our support team at +91 98300XXXXX.';
            chatBody.appendChild(errDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
        });
}
</script>