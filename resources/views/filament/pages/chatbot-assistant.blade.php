<x-filament-panels::page>
    <div class="fi-page">
        <div class="fi-page-header">
            <h1 class="fi-page-title">チャットボット</h1>
        </div>

        <div class="fi-page-content">
            <x-filament::section>
                <div
                    x-data="chatbotWidget()"
                    x-init="init()"
                    class="flex flex-col h-[600px]"
                >
                    <div
                        x-ref="messages"
                        class="flex-1 overflow-y-auto space-y-3 p-4 bg-gray-50 rounded-lg"
                        x-show="messages.length > 0"
                    >
                        <template x-for="message in messages" :key="message.id">
                            <div
                                :class="message.role === 'user' ? 'text-right' : 'text-left'"
                            >
                                <div
                                    :class="message.role === 'user'
                                        ? 'inline-block bg-blue-600 text-white rounded-lg px-4 py-2'
                                        : 'inline-block bg-white border rounded-lg px-4 py-2'"
                                    x-text="message.text"
                                    style="white-space: pre-wrap;"
                                ></div>

                                <!-- 管理画面ディープリンク -->
                                <template x-if="message.role === 'bot' && message.actionLinks && message.actionLinks.length > 0">
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <template x-for="link in message.actionLinks" :key="link.url">
                                            <a
                                                :href="link.url"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium px-2.5 py-1 rounded border border-gray-300 transition-colors shadow-sm"
                                                x-text="link.label"
                                            ></a>
                                        </template>
                                    </div>
                                </template>

                                <!-- クイックリプライボタン（カテゴリ対応 / フラット互換） -->
                                <template x-if="message.role === 'bot'">
                                    <div class="mt-2 space-y-2">
                                        <!-- カテゴリ階層表示 -->
                                        <template x-if="message.quickReplyCategories && Object.keys(message.quickReplyCategories).length > 0">
                                            <div class="space-y-2">
                                                <template x-for="(queries, catName) in message.quickReplyCategories" :key="catName">
                                                    <div class="bg-gray-100/80 rounded p-2 text-left">
                                                        <div class="text-[11px] font-semibold text-gray-500 mb-1" x-text="catName"></div>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <template x-for="(query, label) in queries" :key="label">
                                                                <button
                                                                    type="button"
                                                                    class="text-xs bg-white text-blue-700 hover:bg-blue-50 border border-blue-200 rounded-full px-2.5 py-1 transition-colors"
                                                                    :aria-label="label + 'の質問を送信'"
                                                                    @click="sendQuickReply(query)"
                                                                    :disabled="loading"
                                                                    x-text="label"
                                                                ></button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- フラット表示（フォールバック） -->
                                        <template x-if="(!message.quickReplyCategories || Object.keys(message.quickReplyCategories).length === 0) && message.quickReplies && Object.keys(message.quickReplies).length > 0">
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="(query, label) in message.quickReplies" :key="label">
                                                    <button
                                                        type="button"
                                                        class="text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-full px-3 py-1 transition-colors"
                                                        :aria-label="label + 'の質問を送信'"
                                                        @click="sendQuickReply(query)"
                                                        :disabled="loading"
                                                        x-text="label"
                                                    ></button>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- 回答フィードバック（Good/Bad） -->
                                <template x-if="message.role === 'bot' && message.chatLogId">
                                    <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                        <template x-if="!message.feedback">
                                            <div class="flex items-center gap-1.5 bg-gray-50/80 px-2 py-0.5 rounded border border-gray-100">
                                                <span>参考になりましたか？</span>
                                                <button
                                                    type="button"
                                                    class="hover:scale-125 transition-transform p-0.5 text-sm"
                                                    title="役に立った"
                                                    aria-label="役に立った"
                                                    @click="sendFeedback(message, 'helpful')"
                                                >👍</button>
                                                <button
                                                    type="button"
                                                    class="hover:scale-125 transition-transform p-0.5 text-sm"
                                                    title="解決しなかった"
                                                    aria-label="解決しなかった"
                                                    @click="sendFeedback(message, 'unhelpful')"
                                                >👎</button>
                                            </div>
                                        </template>
                                        <template x-if="message.feedback">
                                            <span class="text-xs text-gray-400 italic">フィードバックを送信しました</span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div x-show="messages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center text-gray-500 py-8">
                        <p class="mb-4 text-sm font-medium text-gray-700">入居者名や請求について質問してください。</p>
                        
                        @php
                            $categories = config('chatbot.quick_reply_categories', []);
                        @endphp

                        @if(!empty($categories))
                            <div class="w-full max-w-lg space-y-3 text-left">
                                @foreach($categories as $catName => $queries)
                                    <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
                                        <div class="text-xs font-bold text-gray-600 mb-2 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                            {{ $catName }}
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($queries as $label => $query)
                                                <button
                                                    type="button"
                                                    class="text-xs bg-gray-50 text-gray-700 hover:bg-blue-50 hover:text-blue-700 border border-gray-200 rounded-full px-3 py-1.5 transition-colors"
                                                    aria-label="{{ $label }}の質問を送信"
                                                    @click="sendQuickReply('{{ $query }}')"
                                                    :disabled="loading"
                                                >
                                                    {{ $label }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex flex-wrap justify-center gap-2 max-w-md">
                                @foreach(config('chatbot.quick_replies', []) as $label => $query)
                                    <button
                                        type="button"
                                        class="text-xs bg-white text-gray-700 hover:bg-gray-100 border border-gray-300 rounded-full px-3 py-1.5 shadow-sm transition-colors"
                                        aria-label="{{ $label }}の質問を送信"
                                        @click="sendQuickReply('{{ $query }}')"
                                        :disabled="loading"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <form
                        @submit.prevent="send()"
                        class="flex gap-2 mt-4 relative"
                    >
                        <div class="relative flex-1">
                            <input
                                type="text"
                                x-model="input"
                                x-ref="input"
                                list="chatbot-suggestions-list"
                                autocomplete="off"
                                placeholder="例: 山田太郎の今月の請求額は？"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                x-bind:disabled="loading"
                                aria-label="チャットボットへのメッセージ入力"
                            />
                            <datalist id="chatbot-suggestions-list">
                                <template x-for="item in suggestions" :key="item">
                                    <option :value="item"></option>
                                </template>
                            </datalist>
                        </div>
                        <x-filament::button
                            type="submit"
                            x-bind:disabled="loading || input.trim() === ''"
                        >
                            送信
                        </x-filament::button>
                    </form>
                </div>
            </x-filament::section>
        </div>
    </div>

    <script>
        function chatbotWidget() {
            return {
                messages: [],
                input: '',
                loading: false,
                sessionId: null,
                suggestions: @json(config('chatbot.suggestions', [])),

                init() {
                    this.sessionId = localStorage.getItem('chatbot_session_id')
                        || crypto.randomUUID();
                    localStorage.setItem('chatbot_session_id', this.sessionId);
                },

                sendQuickReply(query) {
                    this.input = query;
                    this.send();
                },

                async send() {
                    const text = this.input.trim();
                    if (!text || this.loading) return;

                    this.messages.push({ id: Date.now(), role: 'user', text });
                    this.input = '';
                    this.loading = true;

                    try {
                        const response = await fetch("{{ route('chatbot.message') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                message: text,
                                session_id: this.sessionId,
                            }),
                        });

                        if (!response.ok) {
                            throw new Error('エラーが発生しました');
                        }

                        const data = await response.json();
                        this.messages.push({
                            id: Date.now() + 1,
                            role: 'bot',
                            text: data.reply,
                            quickReplies: data.quick_replies || null,
                            quickReplyCategories: data.quick_reply_categories || null,
                            chatLogId: data.chat_log_id || null,
                            actionLinks: data.action_links || [],
                            feedback: null,
                        });
                    } catch (error) {
                        this.messages.push({
                            id: Date.now() + 1,
                            role: 'bot',
                            text: 'エラーが発生しました。しばらくしてから再度お試しください。',
                        });
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => {
                            const container = this.$refs.messages;
                            if (container) {
                                container.scrollTop = container.scrollHeight;
                            }
                            this.$refs.input?.focus();
                        });
                    }
                },

                async sendFeedback(msg, feedbackType) {
                    if (!msg.chatLogId || msg.feedback) return;

                    try {
                        const response = await fetch("{{ route('chatbot.feedback') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                chat_log_id: msg.chatLogId,
                                feedback: feedbackType,
                            }),
                        });

                        if (response.ok) {
                            msg.feedback = feedbackType;
                        }
                    } catch (e) {
                        console.error('Feedback failed', e);
                    }
                },
            };
        }
    </script>
</x-filament-panels::page>
