< !--Add this script right before the closing </body > tag in dashboard.php-- >

    <script>
/**
        * Mallika Idle Detection & Proactive Engagement
        * Auto-triggers Mallika after 2 seconds of user inactivity
        */

        (function() {
            let idleTimer = null;
        let hasTriggeredIdle = false;
        const IDLE_TIME_MS = 2000; // 2 seconds for demo

        // Activity event types to track
        const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

        // Proactive messages from Mallika
        const proactiveMessages = [
        "Hi! I noticed you've been quiet. Are you stuck on something? I'm here to help! 😊",
        "Hey there! Need any assistance with your workload planning or activities?",
        "Hello! I see you're reviewing your dashboard. Is there anything I can clarify or help you with?",
        "Hi! Just checking in - are you having trouble with any section? I'm here to guide you!",
        "Greetings! I'm Mallika, your AI assistant. Feel free to ask me anything about your appraisal or tasks!"
        ];

        function getRandomProactiveMessage() {
        const randomIndex = Math.floor(Math.random() * proactiveMessages.length);
        return proactiveMessages[randomIndex];
    }

        function resetIdleTimer() {
        // Clear existing timer
        if (idleTimer) {
            clearTimeout(idleTimer);
        }

        // Only set new timer if we haven't triggered idle engagement yet
        if (!hasTriggeredIdle) {
            idleTimer = setTimeout(triggerIdleEngagement, IDLE_TIME_MS);
        }
    }

        function triggerIdleEngagement() {
        // Check if chat is already open
        const chat = document.getElementById('mallika-chat');
        if (!chat || chat.style.display === 'flex') {
            return; // Chat already open, don't trigger
        }

        // Mark as triggered so it doesn't repeat
        hasTriggeredIdle = true;

        // Open the chat
        chat.style.display = 'flex';

        // Wait a bit for animation, then send proactive message
        setTimeout(() => {
            const proactiveMsg = getRandomProactiveMessage();
        renderMessage('ai', proactiveMsg);

        // Add to conversation history
        conversationHistory.push({
            role: 'assistant',
        content: proactiveMsg
            });
        }, 500);

        console.log('Mallika: Proactive engagement triggered after idle period');
    }

        function stopIdleTracking() {
        if (idleTimer) {
            clearTimeout(idleTimer);
        idleTimer = null;
        }
        hasTriggeredIdle = true; // Prevent further triggers
    }

        // Initialize idle tracking
        function initIdleTracking() {
        // Only enable if chat is not already open
        const chat = document.getElementById('mallika-chat');
        if (chat && chat.style.display === 'flex') {
            hasTriggeredIdle = true;
        return; // Chat already open, don't start tracking
        }

        // Listen for user activity
        activityEvents.forEach(event => {
            document.addEventListener(event, resetIdleTimer, true);
        });

        // When chat is manually opened, stop idle tracking
        const originalToggleChat = window.toggleChat;
        window.toggleChat = function() {
            stopIdleTracking();
        if (originalToggleChat) {
            originalToggleChat();
            }
        };

        // Start the idle timer
        resetIdleTimer();

        console.log('Mallika: Idle tracking initialized (2s demo mode)');
    }

        // Wait for page to fully load, then start tracking
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initIdleTracking);
    } else {
            // DOM already loaded
            setTimeout(initIdleTracking, 1000); // Small delay to let existing auto-triggers run first
    }
})();
    </script>
