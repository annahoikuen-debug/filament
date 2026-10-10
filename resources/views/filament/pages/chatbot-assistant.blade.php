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
                            </div>
                        </template>
                    </div>

                    <div x-show="messages.length === 0" class="text-center text-gray-400 py-16">
                        入居者名や請求について質問してください。
                    </div>

                    <form
                        @submit.prevent="send()"
                        class="flex gap-2 mt-4"
                    >
                        <input
                            type="text"
                            x-model="input"
                            x-ref="input"
                            placeholder="例: 山田太郎の今月の請求額は？"
                            class="flex-1 rounded-lg border-gray-300"
                            x-bind:disabled="loading"
                        />
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

                init() {
                    this.sessionId = localStorage.getItem('chatbot_session_id')
                        || crypto.randomUUID();
                    localStorage.setItem('chatbot_session_id', this.sessionId);
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
            };
        }
    </script>
</x-filament-panels::page>
