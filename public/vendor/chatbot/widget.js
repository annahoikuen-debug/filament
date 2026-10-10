// Chatbot Widget for Public Site
// Vanilla JS implementation with Shadow DOM encapsulation

(function() {
  // Widget configuration
  const config = {
    apiUrl: '/api/public/chatbot/message',
    primaryColor: '#1e3a8a', // Default blue color
    widgetId: 'public-chatbot-widget',
    fabId: 'public-chatbot-fab',
    panelId: 'public-chatbot-panel',
  };

  // Read data-* attributes from script tag for configuration
  const scriptTag = document.currentScript;
  if (scriptTag) {
    if (scriptTag.dataset.apiUrl) {
      config.apiUrl = scriptTag.dataset.apiUrl;
    }
    if (scriptTag.dataset.primaryColor) {
      config.primaryColor = scriptTag.dataset.primaryColor;
    }
  }

  // Generate visitor ID if not exists
  function getOrCreateVisitorId() {
    let visitorId = localStorage.getItem('chatbot_visitor_id');
    if (!visitorId) {
      visitorId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
      });
      localStorage.setItem('chatbot_visitor_id', visitorId);
    }
    return visitorId;
  }

  // Load chat history from localStorage
  function loadChatHistory() {
    const history = localStorage.getItem('chatbot_history');
    return history ? JSON.parse(history) : [];
  }

  // Save chat history to localStorage
  function saveChatHistory(history) {
    localStorage.setItem('chatbot_history', JSON.stringify(history));
  }

  // Add message to chat history
  function addToChatHistory(message, isUser = false) {
    const history = loadChatHistory();
    history.push({
      id: Date.now() + Math.random(),
      message: message,
      isUser: isUser,
      timestamp: new Date().toISOString(),
    });
    // Keep only last 50 messages
    if (history.length > 50) {
      history.splice(0, history.length - 50);
    }
    saveChatHistory(history);
  }

  // Create widget elements using Shadow DOM
  function createWidget() {
    // Create main container
    const container = document.createElement('div');
    container.id = config.widgetId;
    
    // Create shadow root
    const shadow = container.attachShadow({ mode: 'open' });
    
    // Create style element
    const style = document.createElement('style');
    style.textContent = `
      :host {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      }
      
      #fab {
        position: relative;
        width: 56px;
        height: 56px;
        background-color: var(--primary-color, #1e3a8a);
        color: white;
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.2s ease;
      }
      
      #fab:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
      }
      
      #fab:active {
        transform: scale(0.95);
      }
      
      #panel {
        position: absolute;
        bottom: 80px;
        right: 0;
        width: 320px;
        max-height: 400px;
        background-color: white;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.1);
      }
      
      #panel.open {
        display: flex;
        animation: slideUp 0.3s ease-out;
      }
      
      @keyframes slideUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      
      #chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      
      .message-bubble {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 18px;
        word-wrap: break-word;
        line-height: 1.4;
      }
      
      .user-message {
        background-color: var(--primary-color, #1e3a8a);
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 6px;
      }
      
      .bot-message {
        background-color: #f1f5f9;
        color: #1e293b;
        align-self: flex-start;
        border-bottom-left-radius: 6px;
      }
      
      .message-time {
        font-size: 0.75em;
        opacity: 0.7;
        margin-top: 4px;
        text-align: right;
      }
      
      #input-area {
        display: flex;
        padding: 12px;
        gap: 8px;
        border-top: 1px solid #e2e8f0;
        background-color: white;
      }
      
      #message-input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s ease;
      }
      
      #message-input:focus {
        border-color: var(--primary-color, #1e3a8a);
        box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.2);
      }
      
      #send-button {
        width: 36px;
        height: 36px;
        background-color: var(--primary-color, #1e3a8a);
        color: white;
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color 0.2s ease;
      }
      
      #send-button:hover {
        background-color: #1e40af;
      }
      
      #send-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      
      .quick-replies {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px;
        background-color: #f8fafc;
      }
      
      .quick-reply-btn {
        background-color: white;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 8px 16px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      
      .quick-reply-btn:hover {
        background-color: #f1f5f9;
        border-color: #d1d5db;
      }
      
      .quick-reply-btn:active {
        transform: scale(0.95);
      }
      
      .lead-form {
        padding: 16px;
        background-color: white;
        border-top: 1px solid #e2e8f0;
      }
      
      .lead-form h3 {
        margin-top: 0;
        margin-bottom: 12px;
        color: #1e293b;
        font-size: 16px;
        font-weight: 600;
      }
      
      .lead-form-group {
        margin-bottom: 12px;
      }
      
      .lead-form-label {
        display: block;
        margin-bottom: 4px;
        font-size: 14px;
        color: #374151;
        font-weight: 500;
      }
      
      .lead-form-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s ease;
      }
      
      .lead-form-input:focus {
        border-color: var(--primary-color, #1e3a8a);
        box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.2);
      }
      
      .action-links {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 16px;
        background-color: #f8fafc;
      }
      
      .action-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background-color: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        text-decoration: none;
        color: #1e293b;
        font-size: 14px;
        transition: all 0.2s ease;
      }
      
      .action-link:hover {
        background-color: #f1f5f9;
        border-color: #d1d5db;
        transform: translateX(4px);
      }
      
      .action-link-icon {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
      }
    `;
    
    // Create FAB (Floating Action Button)
    const fab = document.createElement('button');
    fab.id = config.fabId;
    fab.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4V20M4 12H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    
    // Create panel
    const panel = document.createElement('div');
    panel.id = config.panelId;
    panel.className = 'panel';
    
    // Append style and elements to shadow DOM
    shadow.appendChild(style);
    shadow.appendChild(fab);
    shadow.appendChild(panel);
    
    // Append container to body
    document.body.appendChild(container);
    
    return { fab, panel, shadow };
  }

  // Initialize widget
  function initWidget() {
    const { fab, panel, shadow } = createWidget();
    const visitorId = getOrCreateVisitorId();
    
    let isPanelOpen = false;
    let isTyping = false;
    let formLoadedAt = null;
    
    // Toggle panel visibility
    fab.addEventListener('click', () => {
      isPanelOpen = !isPanelOpen;
      panel.classList.toggle('open', isPanelOpen);
      
      if (isPanelOpen) {
        // Load initial chat history when panel opens
        renderChatHistory();
        // Show quick replies if no chat history
        const history = loadChatHistory();
        if (history.length === 0) {
          showQuickReplies();
        }
        // Focus on input
        setTimeout(() => {
          const input = shadow.getElementById('message-input');
          if (input) input.focus();
        }, 100);
      }
    });
    
    // Close panel when clicking outside
    document.addEventListener('click', (e) => {
      if (!shadow.host.contains(e.target) && isPanelOpen) {
        isPanelOpen = false;
        panel.classList.remove('open');
      }
    });
    
    // Handle ESC key to close panel
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && isPanelOpen) {
        isPanelOpen = false;
        panel.classList.remove('open');
      }
    });
    
    // Render chat history
    function renderChatHistory() {
      const messagesContainer = shadow.getElementById('chat-messages');
      if (!messagesContainer) return;
      
      const history = loadChatHistory();
      messagesContainer.innerHTML = '';
      
      history.forEach(msg => {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message-bubble ${msg.isUser ? 'user-message' : 'bot-message'}`;
        
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        timeSpan.textContent = new Date(msg.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        messageDiv.textContent = msg.message;
        messageDiv.appendChild(timeSpan);
        
        messagesContainer.appendChild(messageDiv);
      });
      
      // Scroll to bottom
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Show quick replies
    function showQuickReplies() {
      const messagesContainer = shadow.getElementById('chat-messages');
      if (!messagesContainer) return;
      
      messagesContainer.innerHTML = '';
      
      const quickRepliesDiv = document.createElement('div');
      quickRepliesDiv.className = 'quick-replies';
      
      // Get quick replies from config or use defaults
      const quickReplies = [
        '料金を知りたい',
        '機能を知りたい', 
        '資料請求',
        'デモ希望'
      ];
      
      quickReplies.forEach(reply => {
        const btn = document.createElement('button');
        btn.className = 'quick-reply-btn';
        btn.textContent = reply;
        btn.addEventListener('click', () => {
          sendMessage(reply);
        });
        quickRepliesDiv.appendChild(btn);
      });
      
      messagesContainer.appendChild(quickRepliesDiv);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Show lead form
    function showLeadForm(formType) {
      const messagesContainer = shadow.getElementById('chat-messages');
      if (!messagesContainer) return;
      
      messagesContainer.innerHTML = '';
      
      const leadFormDiv = document.createElement('div');
      leadFormDiv.className = 'lead-form';
      
      const title = document.createElement('h3');
      title.textContent = formType === 'demo' ? 'デモ申込み' : 
                       formType === 'catalog' ? '資料請求' : 'お問い合わせ';
      leadFormDiv.appendChild(title);
      
      const form = document.createElement('form');
      form.id = 'lead-form';
      form.innerHTML = `
        <div class="lead-form-group">
          <label class="lead-form-label" for="lead-name">氏名</label>
          <input type="text" id="lead-name" class="lead-form-input" placeholder="山田太郎" required>
        </div>
        <div class="lead-form-group">
          <label class="lead-form-label" for="lead-email">メールアドレス</label>
          <input type="email" id="lead-email" class="lead-form-input" placeholder="example@example.com" required>
        </div>
        <div class="lead-form-group">
          <label class="lead-form-label" for="lead-facility">施設名</label>
          <input type="text" id="lead-facility" class="lead-form-input" placeholder="施設名" required>
        </div>
        <button type="submit" class="lead-form-submit">送信する</button>
      `;
      
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('.lead-form-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = '送信中...';
        
        try {
          const formData = new FormData(form);
          const data = Object.fromEntries(formData);
          
          // Submit to site-forms API
          const response = await fetch(`/api/site-forms/${formType}`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
          });
          
          if (response.ok) {
            // Show success message
            messagesContainer.innerHTML = '';
            const successDiv = document.createElement('div');
            successDiv.className = 'message-bubble bot-message';
            successDiv.textContent = '送信完了しました。確認メールをお送りしました。';
            messagesContainer.appendChild(successDiv);
            
            // Add link to complete page
            const completeLink = document.createElement('div');
            completeLink.className = 'action-link';
            completeLink.innerHTML = `
              <svg class="action-link-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M5 12l7 7 10-10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span>完了ページを見る</span>
            `;
            completeLink.href = `/complete.html?type=${formType}`;
            completeLink.target = "_blank";
            messagesContainer.appendChild(completeLink);
          } else {
            throw new Error('Submission failed');
          }
        } catch (error) {
          messagesContainer.innerHTML = '';
          const errorDiv = document.createElement('div');
          errorDiv.className = 'message-bubble bot-message';
          errorDiv.textContent = '送信に失敗しました。しばらく経ってからもう一度お試しください。';
          messagesContainer.appendChild(errorDiv);
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = '送信する';
        }
      });
      
      leadFormDiv.appendChild(form);
      messagesContainer.appendChild(leadFormDiv);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Send message to API
    async function sendMessage(message) {
      if (!message.trim()) return;
      
      // Add user message to chat
      addToChatHistory(message, true);
      renderChatHistory();
      
      // Show typing indicator
      showTypingIndicator();
      formLoadedAt = Date.now();
      
      try {
        const response = await fetch(config.apiUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            message: message,
            visitor_id: visitorId,
          }),
          credentials: 'same-origin'
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        // Hide typing indicator
        hideTypingIndicator();
        
        if (result.success) {
          // Add bot response to chat
          addToChatHistory(result.reply, false);
          renderChatHistory();
          
          // Handle special intents
          if (result.intent === 'lead_capture' && result.form) {
            showLeadForm(result.form.endpoint.split('/').pop());
          }
        } else {
          throw new Error('API returned error');
        }
      } catch (error) {
        console.error('Chatbot error:', error);
        // Hide typing indicator
        hideTypingIndicator();
        
        // Show error message
        addToChatHistory('申し訳ありません、一時的に接続に問題が発生しています。しばらく経ってからもう一度お試しください。', false);
        renderChatHistory();
      }
    }
    
    // Show typing indicator
    function showTypingIndicator() {
      const messagesContainer = shadow.getElementById('chat-messages');
      if (!messagesContainer) return;
      
      hideTypingIndicator(); // Clear any existing
      
      const typingDiv = document.createElement('div');
      typingDiv.className = 'message-bubble bot-message typing-indicator';
      typingDiv.innerHTML = `
        <div class="typing-dots">
          <span></span>
          <span></span>
          <span></span>
        </div>
        <span class="message-time"></span>
      `;
      
      const style = document.createElement('style');
      style.textContent = `
        .typing-dots {
          display: flex;
          gap: 4px;
        }
        .typing-dots span {
          width: 8px;
          height: 8px;
          background-color: #9ca3af;
          border-radius: 50%;
          display: inline-block;
          animation: typing 1.4s infinite ease-in-out;
        }
        .typing-dots span:nth-child(2) {
          animation-delay: 0.2s;
        }
        .typing-dots span:nth-child(3) {
          animation-delay: 0.4s;
        }
        @keyframes typing {
          0%, 60%, 100% {
            transform: translateY(0);
          }
          30% {
            transform: translateY(-6px);
          }
        }
      `;
      
      typingDiv.appendChild(style);
      messagesContainer.appendChild(typingDiv);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Hide typing indicator
    function hideTypingIndicator() {
      const messagesContainer = shadow.getElementById('chat-messages');
      if (!messagesContainer) return;
      
      const existingIndicator = messagesContainer.querySelector('.typing-indicator');
      if (existingIndicator) {
        existingIndicator.remove();
      }
    }
    
    // Handle Enter key in input
    const messageInput = document.createElement('input');
    messageInput.id = 'message-input';
    messageInput.type = 'text';
    messageInput.placeholder = 'メッセージを入力...';
    messageInput.maxLength = 500;
    
    messageInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const message = messageInput.value.trim();
        if (message) {
          sendMessage(message);
          messageInput.value = '';
        }
      }
    });
    
    // Create send button
    const sendButton = document.createElement('button');
    sendButton.id = 'send-button';
    sendButton.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4l16 12-16 12V4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    sendButton.disabled = true;
    
    // Enable/disable send button based on input
    messageInput.addEventListener('input', () => {
      sendButton.disabled = !messageInput.value.trim();
    });
    
    // Send button click handler
    sendButton.addEventListener('click', () => {
      const message = messageInput.value.trim();
      if (message) {
        sendMessage(message);
        messageInput.value = '';
      }
    });
    
    // Create input area
    const inputArea = document.createElement('div');
    inputArea.id = 'input-area';
    inputArea.appendChild(messageInput);
    inputArea.appendChild(sendButton);
    
    // Build panel structure
    panel.innerHTML = `
      <div id="chat-messages"></div>
    `;
    
    // Add input area to panel
    panel.appendChild(inputArea);
    
    // Initialize with welcome message if no history
    const history = loadChatHistory();
    if (history.length === 0) {
      // Add welcome bot message
      addToChatHistory('こんにちは！お気軽にお問い合わせください。', false);
      renderChatHistory();
      showQuickReplies();
    }
  }
  
  // Initialize widget when DOM is loaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWidget);
  } else {
    initWidget();
  }
})();