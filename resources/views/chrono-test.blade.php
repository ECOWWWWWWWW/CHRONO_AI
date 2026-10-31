<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chrono AI Test Bench</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 3px;
        }
    </style>
</head>

<body class="bg-gray-100 h-screen flex overflow-hidden font-sans text-gray-800" x-data="chronoApp()">

    <!-- Top Navigation Tabs -->
    <div class="fixed top-0 left-0 right-0 bg-white shadow-sm z-50 px-4 py-2 flex gap-4 border-b border-gray-200">
        <button @click="activeTab = 'chat'" class="px-4 py-2 rounded-md text-sm font-medium transition"
            :class="activeTab === 'chat' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'">
            💬 Chat Assistant
        </button>
        <button @click="activeTab = 'ingest'" class="px-4 py-2 rounded-md text-sm font-medium transition"
            :class="activeTab === 'ingest' ? 'bg-green-600 text-white' : 'text-gray-600 hover:bg-gray-100'">
            📂 RAG Ingestion
        </button>
    </div>

    <!-- Main Content Area (Offset by navbar height) -->
    <div class="flex-1 pt-12 h-full flex w-full">

        <!-- TAB 1: CHAT INTERFACE -->
        <div x-show="activeTab === 'chat'" class="flex w-full h-full">
            <!-- Sidebar: Session List -->
            <div class="w-64 bg-white border-r border-gray-200 flex flex-col h-full shadow-sm z-20">
                <div class="p-4 border-b border-gray-200 bg-indigo-50">
                    <h2 class="font-bold text-xl text-indigo-700 flex items-center gap-2">
                        <span>⚡</span> Chrono AI
                    </h2>
                    <button @click="createNewSession()"
                        class="mt-3 w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-md transition text-sm font-medium shadow-sm disabled:opacity-50"
                        :disabled="isLoadingList">
                        + New Lesson Chat
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-1 custom-scrollbar">
                    <template x-for="session in sessions" :key="session.id">
                        <div @click="selectSession(session)"
                            class="cursor-pointer p-3 rounded-md hover:bg-gray-50 transition border-l-4 group"
                            :class="currentSession?.id === session.id ? 'border-indigo-500 bg-indigo-50' : 'border-transparent'">
                            <p class="font-medium text-sm truncate" x-text="session.title"></p>
                            <p class="text-xs text-gray-500 mt-1" x-text="session.subject || 'General'"></p>
                        </div>
                    </template>

                    <div x-show="sessions.length === 0 && !isLoadingList"
                        class="text-center text-gray-400 text-sm mt-8 italic">
                        No active sessions.<br>Create one above!
                    </div>
                    <div x-show="isLoadingList" class="text-center text-gray-400 text-sm mt-8 animate-pulse">
                        Loading...
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="flex-1 flex flex-col h-full relative bg-gray-50">
                <header
                    class="bg-white border-b border-gray-200 p-4 flex justify-between items-center shadow-sm z-10 min-h-[60px]">
                    <div x-show="currentSession">
                        <h3 class="font-semibold text-lg text-gray-800" x-text="currentSession?.title"></h3>
                        <p class="text-xs text-gray-500" x-text="'Grade: ' + (currentSession?.grade_level || 'N/A')">
                        </p>
                    </div>
                    <div x-show="!currentSession" class="text-gray-400 italic">
                        Select or create a lesson to start chatting.
                    </div>
                    <div x-show="currentSession" class="flex items-center gap-2">
                        <span class="text-xs font-mono px-2 py-1 rounded-full bg-green-100 text-green-700"
                            x-show="lastMessageHadRag">✅ RAG Active</span>
                        <span class="text-xs font-mono px-2 py-1 rounded-full bg-yellow-100 text-yellow-700"
                            x-show="!lastMessageHadRag && currentSession">⚠️ No Context</span>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar" id="chat-window">
                    <template x-if="messages.length === 0 && currentSession">
                        <div class="h-full flex flex-col items-center justify-center text-gray-400">
                            <svg class="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                                </path>
                            </svg>
                            <p class="text-sm">Start the conversation!</p>
                        </div>
                    </template>

                    <template x-for="(msg, index) in messages" :key="index">
                        <div class="flex gap-3 max-w-3xl mx-auto"
                            :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold shadow-sm"
                                :class="msg.role === 'user' ? 'bg-blue-600 text-white' : 'bg-indigo-600 text-white'">
                                <span x-text="msg.role === 'user' ? 'U' : 'AI'"></span>
                            </div>
                            <div class="max-w-[80%] md:max-w-[70%] p-4 rounded-2xl shadow-sm text-sm leading-relaxed whitespace-pre-wrap break-words"
                                :class="msg.role === 'user' ? 'bg-blue-600 text-white rounded-tr-none' :
                                    'bg-white text-gray-800 border border-gray-200 rounded-tl-none'">
                                <p x-text="msg.content"></p>
                                <div class="mt-2 pt-2 border-t border-opacity-20 flex justify-between items-center text-[10px]"
                                    :class="msg.role === 'user' ? 'border-white text-blue-100' :
                                        'border-gray-200 text-gray-400'">
                                    <span
                                        x-text="new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})"></span>
                                    <span x-show="msg.role === 'assistant' && msg.meta?.has_context"
                                        class="bg-green-100 text-green-700 px-1 rounded">RAG</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="isSending" class="flex justify-start gap-3 max-w-3xl mx-auto">
                        <div
                            class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-bold mr-2">
                            AI</div>
                        <div
                            class="bg-white border border-gray-200 p-4 rounded-2xl rounded-tl-none shadow-sm animate-pulse text-gray-500 italic">
                            Thinking & Retrieving Curriculum...
                        </div>
                    </div>
                </main>

                <footer class="bg-white border-t border-gray-200 p-4 sticky bottom-0 z-10">
                    <form @submit.prevent="sendMessage" class="max-w-3xl mx-auto flex gap-2">
                        <input type="text" x-model="newMessage" placeholder="Ask about curriculum..."
                            class="flex-1 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                            :disabled="!currentSessionId || isSending">
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition disabled:opacity-50"
                            :disabled="!currentSessionId || !newMessage.trim() || isSending">
                            Send
                        </button>
                    </form>
                </footer>
            </div>
        </div>

        <!-- TAB 2: RAG INGESTION INTERFACE -->
        <div x-show="activeTab === 'ingest'" class="flex-1 p-8 overflow-y-auto bg-gray-50">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📂 Curriculum Document Ingestion</h2>

                <!-- Upload Form -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8">
                    <h3 class="text-lg font-semibold mb-4">Upload New Resource</h3>
                    <form @submit.prevent="uploadDocument" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
                                <input type="text" x-model="uploadForm.title" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500 outline-none"
                                    placeholder="e.g., Grade 7 Science Chapter 4">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Source Type</label>
                                <select x-model="uploadForm.source_type"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-green-500 outline-none">
                                    <option value="admin_curriculum">Admin Curriculum</option>
                                    <option value="teacher_resource">Teacher Resource</option>
                                    <option value="uploaded_file">Generic File</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">File (PDF, DOCX, TXT)</label>
                            <input type="file" x-ref="fileInput" @change="handleFileSelect($event)"
                                accept=".pdf,.docx,.txt,.md,.markdown"
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100" />
                            <p x-show="selectedFileName" class="mt-2 text-sm text-gray-600">Selected: <span
                                    x-text="selectedFileName" class="font-medium"></span></p>
                        </div>

                        <button type="submit" :disabled="isUploading"
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-md transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!isUploading">🚀 Process & Index Document</span>
                            <span x-show="isUploading" class="animate-pulse">Processing... (This may take
                                10-30s)</span>
                        </button>
                    </form>

                    <!-- Result Feedback -->
                    <div x-show="uploadResult" class="mt-4 p-4 rounded-md text-sm"
                        :class="uploadResult.success ? 'bg-green-100 text-green-800 border border-green-200' :
                            'bg-red-100 text-red-800 border border-red-200'">
                        <strong x-text="uploadResult.success ? 'Success!' : 'Error:'"></strong>
                        <p x-text="uploadResult.message"></p>
                        <pre x-show="!uploadResult.success && uploadResult.details"
                            class="mt-2 text-xs bg-black/10 p-2 rounded overflow-x-auto" x-text="uploadResult.details"></pre>
                    </div>
                </div>

                <!-- Recent Documents List -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Recently Indexed Documents</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Title</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Chunks</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="doc in recentDocs" :key="doc.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
                                            x-text="doc.id"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            x-text="doc.title"></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="doc.status === 'indexed' ? 'bg-green-100 text-green-800' : (doc
                                                    .status === 'failed' ? 'bg-red-100 text-red-800' :
                                                    'bg-yellow-100 text-yellow-800')"
                                                x-text="doc.status"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            x-text="doc.chunks"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            x-text="doc.created_at"></td>
                                    </tr>
                                </template>
                                <tr x-show="recentDocs.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No
                                        documents found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('chronoApp', () => ({
                // Global State
                activeTab: 'chat',

                // --- CHAT STATE ---
                sessions: [],
                currentSession: null,
                messages: [],
                newMessage: '',
                isLoadingList: false,
                isSending: false,
                lastMessageHadRag: false,

                // --- INGEST STATE ---
                uploadForm: {
                    title: '',
                    source_type: 'admin_curriculum'
                },
                selectedFile: null,
                selectedFileName: '',
                isUploading: false,
                uploadResult: null,
                recentDocs: [],

                init() {
                    this.fetchSessions();
                    this.fetchRecentDocs();
                },

                // --- CHAT METHODS (Same as before) ---
                async fetchSessions() {
                    this.isLoadingList = true;
                    try {
                        const res = await fetch('/api/chat/sessions');
                        if (!res.ok) throw new Error('Failed');
                        this.sessions = await res.json();
                        if (this.sessions.length > 0 && !this.currentSession) {
                            this.selectSession(this.sessions[0]);
                        }
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.isLoadingList = false;
                    }
                },
                async createNewSession() {
                    const title = prompt("Enter Lesson Title:");
                    if (!title) return;
                    try {
                        const res = await fetch('/api/chat/sessions', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                title
                            })
                        });
                        if (!res.ok) throw new Error('Creation failed');
                        const newSession = await res.json();
                        this.sessions.unshift(newSession);
                        this.selectSession(newSession);
                    } catch (e) {
                        alert('Error: ' + e.message);
                    }
                },
                selectSession(session) {
                    this.currentSession = session;
                    this.messages = [];
                    this.lastMessageHadRag = false;
                    this.loadHistory(session.id);
                },
                async loadHistory(sessionId) {
                    try {
                        const res = await fetch(`/api/chat/sessions/${sessionId}`);
                        if (!res.ok) throw new Error('Failed');
                        const data = await res.json();
                        this.messages = data.messages.map(m => ({
                            ...m,
                            meta: m.retrieved_chunks_meta
                        }));
                        this.scrollToBottom();
                    } catch (e) {
                        console.error(e);
                    }
                },
                async sendMessage() {
                    if (!this.newMessage.trim() || !this.currentSessionId) return;
                    const promptText = this.newMessage;
                    this.newMessage = '';
                    this.isSending = true;
                    this.messages.push({
                        role: 'user',
                        content: promptText,
                        created_at: new Date().toISOString()
                    });
                    this.scrollToBottom();
                    try {
                        const res = await fetch(
                        `/api/chat/sessions/${this.currentSessionId}/send`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                prompt: promptText
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Server error');
                        this.messages.push({
                            role: 'assistant',
                            content: data.message.content,
                            created_at: data.message.created_at,
                            meta: data.message.retrieved_chunks_meta
                        });
                        this.lastMessageHadRag = data.message.retrieved_chunks_meta?.has_context ??
                            false;
                    } catch (e) {
                        alert('Chat Error: ' + e.message);
                    } finally {
                        this.isSending = false;
                        this.scrollToBottom();
                    }
                },
                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = document.getElementById('chat-window');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },
                get currentSessionId() {
                    return this.currentSession?.id;
                },

                // --- INGEST METHODS ---
                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.selectedFile = file;
                        this.selectedFileName = file.name;
                    }
                },
                async uploadDocument() {
                    if (!this.selectedFile || !this.uploadForm.title) {
                        alert('Please provide a title and select a file.');
                        return;
                    }

                    this.isUploading = true;
                    this.uploadResult = null;

                    const formData = new FormData();
                    formData.append('file', this.selectedFile);
                    formData.append('title', this.uploadForm.title);
                    formData.append('source_type', this.uploadForm.source_type);

                    try {
                        const res = await fetch('/api/rag/documents', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: formData
                        });

                        const data = await res.json();

                        if (data.success) {
                            this.uploadResult = {
                                success: true,
                                message: data.message
                            };
                            this.resetUploadForm();
                            this.fetchRecentDocs(); // Refresh list
                        } else {
                            this.uploadResult = {
                                success: false,
                                message: data.message,
                                details: data.details
                            };
                        }

                    } catch (e) {
                        this.uploadResult = {
                            success: false,
                            message: 'Network error: ' + e.message
                        };
                    } finally {
                        this.isUploading = false;
                    }
                },
                resetUploadForm() {
                    this.uploadForm = {
                        title: '',
                        source_type: 'admin_curriculum'
                    };
                    this.selectedFile = null;
                    this.selectedFileName = '';
                    this.$refs.fileInput.value = ''; // Clear file input DOM
                },
                async fetchRecentDocs() {
                    try {
                        const res = await fetch('/api/rag/documents');
                        if (res.ok) {
                            this.recentDocs = await res.json();
                        }
                    } catch (e) {
                        console.error('Failed to load docs', e);
                    }
                }
            }))
        });
    </script>
</body>

</html>
