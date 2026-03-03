document.addEventListener('DOMContentLoaded', function () {
    // Check if config exists (it's now returned for all pages)
    if (typeof window.aiConfig === 'undefined') return;

    const config = window.aiConfig;

    // 1. Create Floating Action Button (FAB)
    const fab = document.createElement('div');
    fab.className = 'ai-chat-fab';
    fab.innerHTML = `
        <i class="fas fa-user-tie fab-icon"></i>
        <span class="fab-label">Ask Mallika</span>
    `;
    document.body.appendChild(fab);

    // 2. Create Chat Widget
    const widget = document.createElement('div');
    widget.className = 'ai-chat-widget';

    widget.innerHTML = `
        <div class="ai-chat-header">
            <span class="ai-chat-title"><i class="fas fa-robot" style="color: var(--primary-gold);"></i> Mallika (AI Assistant)</span>
            <span class="ai-chat-close">&times;</span>
        </div>
        <div class="ai-chat-body" id="chatBody"></div>
        <div class="ai-chat-input-area">
            <input type="text" class="ai-chat-input" placeholder="Type your question..." id="aiInput">
            <button class="ai-chat-send" id="aiSend"><i class="fas fa-paper-plane"></i></button>
        </div>
    `;

    document.body.appendChild(widget);

    // Elements
    const chatBody = widget.querySelector('#chatBody');
    const closeBtn = widget.querySelector('.ai-chat-close');
    const inputField = widget.querySelector('#aiInput');
    const sendBtn = widget.querySelector('#aiSend');

    // 3. Logic: Toggle Widget
    fab.addEventListener('click', () => {
        widget.classList.add('active');
        fab.style.display = 'none'; // Hide button when chat is open

        // Initialize chat if empty
        if (chatBody.children.length === 0) {
            initChat();
        }
    });

    closeBtn.addEventListener('click', () => {
        widget.classList.remove('active');
        fab.style.display = 'flex'; // Show button when chat is closed
    });

    // 4. Input Handling
    sendBtn.addEventListener('click', sendMessage);
    inputField.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // 5. Auto-Trigger Logic
    // If specific trigger condition met (e.g. empty section), open automatically.
    if (config.trigger) {
        setTimeout(() => {
            fab.click(); // Simulate click to open
        }, 2000);
    }

    function initChat() {
        showTyping();
        setTimeout(() => {
            removeTyping();
            addMessage(config.message, 'bot');

            const options = config.trigger
                ? ['What can I do?', 'Not now']
                : ['Help with this section', 'General tips'];

            showOptions(options);
        }, 1000);
    }

    function sendMessage() {
        const text = inputField.value.trim();
        if (!text) return;

        inputField.value = '';
        addMessage(text, 'user');

        // Remove old options
        const existingOpts = chatBody.querySelectorAll('.chat-options');
        existingOpts.forEach(el => el.remove());

        showTyping();

        fetch('ai_suggest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                section: config.sectionName,
                days: config.daysInactive,
                user_input: text
            })
        })
            .then(response => response.json())
            .then(data => {
                removeTyping();
                addMessage(data.suggestion, 'bot');
            })
            .catch(err => {
                removeTyping();
                addMessage("I'm having a bit of trouble connecting.", 'bot');
            });
    }

    function addMessage(text, type) {
        const msg = document.createElement('div');
        msg.className = `chat-message ${type}`;
        msg.textContent = text;
        chatBody.appendChild(msg);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function showTyping() {
        const indicator = document.createElement('div');
        indicator.className = 'typing-indicator';
        indicator.id = 'typing';
        indicator.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
        chatBody.appendChild(indicator);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function removeTyping() {
        const indicator = document.getElementById('typing');
        if (indicator) indicator.remove();
    }

    function showOptions(options) {
        const optContainer = document.createElement('div');
        optContainer.className = 'chat-options';

        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'chat-option-btn';
            btn.textContent = opt;
            btn.onclick = () => handleOptionClick(opt, optContainer);
            optContainer.appendChild(btn);
        });

        chatBody.appendChild(optContainer);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function handleOptionClick(choice, container) {
        container.remove();
        addMessage(choice, 'user');
        showTyping();

        if (choice === 'What can I do?' || choice === 'Generate another' || choice === 'Help with this section') {
            fetch('ai_suggest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    section: config.sectionName,
                    days: config.daysInactive
                })
            })
                .then(response => response.json())
                .then(data => {
                    removeTyping();
                    if (choice === 'What can I do?') addMessage("Here's a suggestion:", 'bot');
                    addMessage(data.suggestion, 'bot');
                    showOptions(['Generate another', 'Thanks']);
                })
                .catch(err => {
                    removeTyping();
                    addMessage("I'm having trouble connecting.", 'bot');
                });

        } else if (choice === 'Not now' || choice === 'Thanks') {
            setTimeout(() => {
                removeTyping();
                addMessage("Anytime!", 'bot');
                setTimeout(() => {
                    widget.classList.remove('active');
                    fab.style.display = 'flex';
                }, 1500);
            }, 600);
        } else {
            // General handler
            setTimeout(() => {
                removeTyping();
                addMessage("I'm here to help with whatever you need.", 'bot');
            }, 1000);
        }
    }
});
