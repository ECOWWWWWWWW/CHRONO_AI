<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini AI Chat</title>
    <!-- Tailwind CSS CDN for styling -->
    <script src="https://jsdelivr.net"></script>
</head>

<body class="bg-gray-100 h-screen flex flex-col justify-between font-sans">

    <!-- Header -->
    <header class="bg-white shadow-sm py-4 px-6 border-b border-gray-200">
        <h1 class="text-xl font-bold text-gray-800">Gemini 2.5 Flash Sandbox</h1>
    </header>

    <!-- Chat Conversation Box -->
    <main class="flex-1 overflow-y-auto p-6 space-y-4 max-w-4xl w-full mx-auto" id="chat-box">
        <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg text-sm shadow-sm">
            💡 Type a prompt below to see the <strong>Gemini Interactions API</strong> in action.
        </div>
    </main>

    <!-- Footer Input Bar -->
    <footer class="bg-white border-t border-gray-200 p-4 sticky bottom-0">
        <div class="max-w-4xl mx-auto">
            <form id="chat-form" class="flex gap-4">
                <!-- Laravel CSRF protection token -->
                @csrf
                <input type="text" id="user-prompt" name="prompt" placeholder="Ask Gemini something..." required
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 text-gray-900">
                <button type="submit" id="submit-btn"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-3 rounded-xl transition duration-200 ease-in-out shadow-md">
                    Send
                </button>
            </form>
        </div>
    </footer>

    <!-- Frontend AJAX Logic -->
    <script>
        // 1. Initialize the history array to keep track of the conversation
        let chatHistory = [];

        document.getElementById('chat-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const promptInput = document.getElementById('user-prompt');
            const submitBtn = document.getElementById('submit-btn');
            const promptValue = promptInput.value.trim();

            if (!promptValue) return;

            // Append User's Message bubble to the UI
            appendMessage(promptValue, 'user');
            promptInput.value = ''; // Clear input field

            // Disable button and add typing status indicator
            submitBtn.disabled = true;
            submitBtn.innerText = 'Thinking...';
            const loadingBubble = appendMessage('AI is drafting a response...', 'system-loading');

            try {
                // 2. Perform background AJAX fetch call to your Laravel Backend
                const response = await fetch("{{ route('welcome.send') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({
                        prompt: promptValue,
                        history: chatHistory // <--- SEND THE HISTORY HERE
                    })
                });

                const data = await response.json();
                loadingBubble.remove(); // Remove loading state bubble

                if (data.success) {
                    // 3. Render Gemini's output
                    appendMessage(data.reply, 'ai');

                    // 4. Update History for the NEXT turn
                    // We push the user prompt and AI reply AFTER the request succeeds.
                    // This prevents the current prompt from being duplicated in Laravel.
                    chatHistory.push({
                        role: 'user',
                        text: promptValue
                    });
                    chatHistory.push({
                        role: 'model',
                        text: data.reply
                    });
                } else {
                    appendMessage('An error occurred: ' + (data.error || 'Unknown issue'), 'error');
                }
            } catch (err) {
                loadingBubble.remove();
                appendMessage('Network error. Failed to reach the server.', 'error');
                console.error(err);
            } finally {
                // Re-enable interactive elements
                submitBtn.disabled = false;
                submitBtn.innerText = 'Send';
            }
        });

        // Helper function to append styling blocks dynamically
        function appendMessage(text, sender) {
            const chatBox = document.getElementById('chat-box');
            const messageWrapper = document.createElement('div');
            messageWrapper.className = `flex ${sender === 'user' ? 'justify-end' : 'justify-start'}`;

            let innerBubbleClass = '';
            if (sender === 'user') {
                innerBubbleClass = 'bg-blue-600 text-white rounded-br-none';
            } else if (sender === 'ai') {
                innerBubbleClass = 'bg-white border border-gray-200 text-gray-800 rounded-bl-none';
            } else if (sender === 'system-loading') {
                innerBubbleClass = 'bg-gray-200 text-gray-500 italic animate-pulse';
            } else {
                innerBubbleClass = 'bg-red-100 text-red-700 border border-red-200';
            }

            // Escape HTML characters so the AI doesn't break your UI if it outputs <code> tags
            const escapedText = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g,
                "&quot;").replace(/'/g, "&#039;");

            messageWrapper.innerHTML = `
                <div class="max-w-2xl px-4 py-3 rounded-2xl shadow-sm text-base whitespace-pre-line ${innerBubbleClass}">
                    ${escapedText}
                </div>
            `;

            chatBox.appendChild(messageWrapper);
            chatBox.scrollTop = chatBox.scrollHeight; // Auto scroll to the lowest visible message
            return messageWrapper;
        }
    </script>
</body>

</html>
