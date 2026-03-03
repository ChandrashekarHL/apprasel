<!-- Section-Specific AI Chat Widget -->
<style>
@keyframes slideChatIn {
    from { transform: translateX(120%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
#section-mallika-chat {
    display: none; 
    flex-direction: column; 
    position: fixed; 
    bottom: 20px; 
    right: 30px; 
    width: 380px; 
    height: 550px; 
    max-height: 80vh; 
    background: white; 
    border-radius: 12px; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
    z-index: 10000; 
    overflow: hidden; 
    border: 1px solid #eee;
    animation: slideChatIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.chat-message-bubble {
    margin-bottom: 15px;
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 15px;
    line-height: 1.4;
    font-size: 0.95em;
    word-wrap: break-word;
}
.chat-ai {
    align-self: flex-start;
    background: #f1f3f5;
    color: #2c3e50;
    border-bottom-left-radius: 4px;
}
.chat-user {
    align-self: flex-end;
    margin-left: auto;
    background: #3498db;
    color: white;
    border-bottom-right-radius: 4px;
}
</style>

<div id="section-mallika-chat">
    <div style="background: linear-gradient(135deg, #2c3e50, #3498db); padding: 15px; display: flex; justify-content: space-between; align-items: center; color: white;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 35px; height: 35px; background: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                <i class="fas fa-robot" style="color: #3498db; font-size: 18px;"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 1em;">Mallika AI</h4>
                <span style="font-size: 0.75em; opacity: 0.9;"><i class="fas fa-circle" style="color:#2ecc71; font-size:8px;"></i> <?php echo htmlspecialchars($page_section ?? 'Assistant'); ?> Mentor</span>
            </div>
        </div>
        <button onclick="toggleSectionChat()" style="background: transparent; border: none; color: white; cursor: pointer; padding: 5px; font-size: 1.1em; transition: 0.2s;"><i class="fas fa-times"></i></button>
    </div>

    <!-- Chat Area -->
    <div id="section-chat-messages" style="flex: 1; padding: 20px; overflow-y: auto; background: #fafbfc; display: flex; flex-direction: column;"></div>

    <!-- Input Area -->
    <div style="padding: 15px; background: white; border-top: 1px solid #eee;">
        <div style="display: flex; gap: 10px;">
            <input type="text" id="section-chat-input" placeholder="Ask Mallika about <?php echo htmlspecialchars($page_section ?? 'this section'); ?>..." style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 25px; outline: none; transition: border-color 0.3s; font-size: 0.95em;" onkeypress="if(event.key === 'Enter') sendSectionMessage()">
            <button onclick="sendSectionMessage()" style="width: 45px; height: 45px; border-radius: 50%; border: none; background: #3498db; color: white; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-paper-plane" style="margin-left: -2px;"></i>
            </button>
        </div>
    </div>
</div>

<!-- Floating Chat Button icon -->
<button onclick="toggleSectionChat()" id="floating-chat-btn" style="position: fixed; bottom: 20px; right: 30px; width: 60px; height: 60px; border-radius: 50%; background: #2c3e50; color: white; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2); cursor: pointer; z-index: 9999; display: flex; align-items: center; justify-content: center; font-size: 24px; transition: transform 0.2s;">
    <i class="fas fa-comment-dots"></i>
</button>

<script>
    const sectionName = "<?php echo htmlspecialchars($page_section ?? 'General'); ?>";
    const recordCount = <?php echo isset($section_record_count) ? (int)$section_record_count : 1; ?>; // Default 1 to avoid auto-spam if not set
    let sectionChatHistory = [];
    
    // Auto-invoke if section is empty
    window.addEventListener('load', function() {
        if (recordCount === 0 && !sessionStorage.getItem('mallikaSectionTrigger_' + sectionName)) {
            setTimeout(() => {
                toggleSectionChat();
                let greetingText = `Welcome to the ${sectionName} section! I see this is currently empty.<br><br>How can I help you get started?`;
                renderSectionMessage('ai', greetingText);
                
                // Render Quick Actions
                const area = document.getElementById('section-chat-messages');
                const quickActions = document.createElement('div');
                quickActions.style.display = 'flex';
                quickActions.style.flexDirection = 'column';
                quickActions.style.gap = '8px';
                quickActions.style.marginBottom = '15px';
                quickActions.style.maxWidth = '85%';
                
                const actions = [
                    "Suggest a problem statement topic",
                    "What counts as valid activity here?",
                    "Help me plan my next steps"
                ];
                
                actions.forEach(action => {
                    const btn = document.createElement('button');
                    btn.innerText = action;
                    btn.style.background = 'white';
                    btn.style.color = '#3498db';
                    btn.style.border = '1px solid #3498db';
                    btn.style.padding = '8px 12px';
                    btn.style.borderRadius = '20px';
                    btn.style.cursor = 'pointer';
                    btn.style.textAlign = 'left';
                    btn.style.fontSize = '0.85em';
                    btn.style.transition = 'all 0.2s';
                    
                    btn.onmouseover = () => { btn.style.background = '#eaf2f8'; };
                    btn.onmouseout = () => { btn.style.background = 'white'; };
                    
                    btn.onclick = () => {
                        quickActions.remove(); // Remove buttons after clicking one
                        document.getElementById('section-chat-input').value = action;
                        sendSectionMessage();
                    };
                    quickActions.appendChild(btn);
                });
                
                area.appendChild(quickActions);
                area.scrollTop = area.scrollHeight;
                
                sessionStorage.setItem('mallikaSectionTrigger_' + sectionName, 'true');
            }, 1500);
        }
    });

    function toggleSectionChat() {
        const chat = document.getElementById('section-mallika-chat');
        const btn = document.getElementById('floating-chat-btn');
        if (chat.style.display === 'none' || chat.style.display === '') {
            chat.style.display = 'flex';
            btn.style.transform = 'scale(0)';
            document.getElementById('section-chat-input').focus();
            
            // If chat is empty, give a contextual greeting
            if (document.getElementById('section-chat-messages').innerHTML.trim() === '') {
                renderSectionMessage('ai', `Hi! I'm here to help you specifically with your ${sectionName} records. What are you working on today?`);
            }
        } else {
            chat.style.display = 'none';
            btn.style.transform = 'scale(1)';
        }
    }

    function renderSectionMessage(role, text) {
        const area = document.getElementById('section-chat-messages');
        const div = document.createElement('div');
        div.className = 'chat-message-bubble ' + (role === 'ai' ? 'chat-ai' : 'chat-user');
        
        if (role === 'ai') {
            div.innerHTML = '<strong style="display:block; margin-bottom:4px; font-size:0.85em; color:#7f8c8d;">Mallika</strong>' + text.replace(/\n/g, '<br>');
        } else {
            div.innerHTML = text.replace(/\n/g, '<br>');
        }
        
        area.appendChild(div);
        area.scrollTop = area.scrollHeight;
    }

    function sendSectionMessage() {
        const input = document.getElementById('section-chat-input');
        const text = input.value.trim();
        if(!text) return;
        
        renderSectionMessage('user', text);
        input.value = '';
        sectionChatHistory.push("user: " + text);
        
        const loader = document.createElement('div');
        loader.id = 'section-ai-typing';
        loader.innerHTML = '<div style="margin-left:5px; color:#95a5a6; font-size: 0.8em; font-style: italic;"><i class="fas fa-circle-notch fa-spin"></i> Mallika is typing...</div>';
        document.getElementById('section-chat-messages').appendChild(loader);

        fetch('ai_suggest.php', {
            method: 'POST',
            body: JSON.stringify({ 
                type: 'supervisor_reply', 
                user_msg: text, 
                history: sectionChatHistory.join('\n'),
                section: sectionName,
                role: 'Faculty',
                name: '<?php echo htmlspecialchars($_SESSION["full_name"] ?? "Faculty"); ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
             document.getElementById('section-ai-typing').remove();
             let aiResp = { message: data.suggestion, action: "assist" };
             try {
                let jsonStr = data.suggestion.replace(/```json/g, '').replace(/```/g, '').trim();
                aiResp = JSON.parse(jsonStr);
             } catch(e) {}

             renderSectionMessage('ai', aiResp.message);
             sectionChatHistory.push("ai: " + aiResp.message);
        })
        .catch(err => {
            document.getElementById('section-ai-typing').remove();
            renderSectionMessage('ai', "I'm having a little trouble connecting to the network. Please try again in a moment.");
        });
    }
</script>
