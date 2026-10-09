<x-filament-panels::page>
    <!-- Progress Bar -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-2">
            <h1 class="text-2xl font-bold text-gray-900">セットアップウィザード</h1>
            <span class="text-sm text-gray-500">ステップ {{ $currentStep }} / {{ $totalSteps }}</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                 style="width: {{ ($currentStep / $totalSteps) * 100 }}%"></div>
        </div>
        <div class="flex justify-between mt-2 text-xs text-gray-500">
            <span class="{{ $currentStep >= 1 ? 'text-blue-600 font-medium' : '' }}">1. 施設情報</span>
            <span class="{{ $currentStep >= 2 ? 'text-blue-600 font-medium' : '' }}">2. 口座情報</span>
            <span class="{{ $currentStep >= 3 ? 'text-blue-600 font-medium' : '' }}">3. 品目マスタ</span>
            <span class="{{ $currentStep >= 4 ? 'text-blue-600 font-medium' : '' }}">4. 会計連携</span>
            <span class="{{ $currentStep >= 5 ? 'text-blue-600 font-medium' : '' }}">5. 完了</span>
        </div>
    </div>

    <!-- Step Content -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="p-6">
            @if($currentStep === 1)
                <div class="space-y-6">
                    <div class="text-center mb-6">
                        <h2 class="mt-2 text-xl font-semibold text-gray-900">施設情報の設定</h2>
                        <p class="mt-1 text-gray-500">まずは施設の基本情報を入力してください</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">施設名 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="formData.facility_name" class="fi-input block w-full border-gray-300 rounded-md shadow-sm" placeholder="例：社会福祉法人○○会 特別養護老人ホーム">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">運営法人名 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="formData.operator" class="fi-input block w-full border-gray-300 rounded-md shadow-sm" placeholder="例：社会福祉法人○○会">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">適格請求書登録番号</label>
                            <input type="text" wire:model="formData.invoice_registration_number" class="fi-input block w-full border-gray-300 rounded-md shadow-sm" placeholder="T1234567890123">
                            <p class="mt-1 text-sm text-gray-500">T + 13桁数字（インボイス制度対応）</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">入居定員</label>
                            <input type="number" wire:model="formData.capacity" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">電話番号</label>
                            <input type="tel" wire:model="formData.phone" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                            <input type="email" wire:model="formData.email" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">郵便番号</label>
                            <input type="text" wire:model="formData.postal_code" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">住所</label>
                            <input type="text" wire:model="formData.address" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                </div>

            @elseif($currentStep === 2)
                <div class="space-y-6">
                    <div class="text-center mb-6">
                        <h2 class="mt-2 text-xl font-semibold text-gray-900">口座情報の設定</h2>
                        <p class="mt-1 text-gray-500">請求書に表示する口座情報を入力してください</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">金融機関名</label>
                            <input type="text" wire:model="formData.bank_name" class="fi-input block w-full border-gray-300 rounded-md shadow-sm" placeholder="例：○○銀行">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">支店名</label>
                            <input type="text" wire:model="formData.bank_branch" class="fi-input block w-full border-gray-300 rounded-md shadow-sm" placeholder="例：○○支店">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">口座種別</label>
                            <select wire:model="formData.bank_account_type" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="普通">普通預金</option>
                                <option value="当座">当座預金</option>
                                <option value="貯蓄">貯蓄預金</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">口座番号</label>
                            <input type="text" wire:model="formData.bank_account_number" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">口座名義</label>
                            <input type="text" wire:model="formData.bank_account_holder" class="fi-input block w-full border-gray-300 rounded-md shadow-sm" placeholder="例：ｼｬｶｲﾌｸｼﾎｳｼﾞﾝ○○ｶｲ">
                        </div>
                    </div>
                </div>

            @elseif($currentStep === 3)
                <div class="space-y-6">
                    <div class="text-center mb-6">
                        <h2 class="mt-2 text-xl font-semibold text-gray-900">品目マスタの設定</h2>
                        <p class="mt-1 text-gray-500">日々の自費記録で使用する品目を選択してください</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($presetItems as $item)
                            <label class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox"
                                       wire:model="formData.preset_items"
                                       value="{{ $item['name'] }}"
                                       class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $item['name'] }}</div>
                                    <div class="text-sm text-gray-500">¥{{ number_format($item['default_price']) }} / {{ $item['category'] }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

            @elseif($currentStep === 4)
                <div class="space-y-6">
                    <div class="text-center mb-6">
                        <h2 class="mt-2 text-xl font-semibold text-gray-900">会計ソフト連携</h2>
                        <p class="mt-1 text-gray-500">会計ソフトへのCSV出力設定を行います</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">会計ソフト</label>
                            <select wire:model="formData.accounting_software" class="fi-input block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="freee">freee</option>
                                <option value="mf">MFクラウド会計</option>
                                <option value="yayoi">弥生会計</option>
                                <option value="kanjobugyo">勘定奉行</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">プロファイル名</label>
                            <input type="text" wire:model="formData.accounting_profile_name" class="fi-input block w-full border-gray-300 rounded-md shadow-sm" placeholder="例：本番用">
                        </div>
                    </div>
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <strong>※</strong> 会計ソフト連携は後からでも設定可能です。<br>
                            設定しない場合は、デフォルトプロファイルが使用されます。
                        </p>
                    </div>
                </div>

            @elseif($currentStep === 5)
                <div class="space-y-6">
                    <div class="text-center py-8">
                        <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h2 class="mt-4 text-2xl font-bold text-gray-900">セットアップ完了！</h2>
                        <p class="mt-2 text-gray-500">これで請求管理を開始できます</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">次のステップ</h3>
                        <div class="space-y-3">
                            <div class="flex items-start">
                                <span class="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-medium">1</span>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">入居者を登録する</p>
                                    <p class="text-sm text-gray-500">入居者マスタから部屋番号・氏名・家賃・管理費を登録</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <span class="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-medium">2</span>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">日々の自費記録を入力する</p>
                                    <p class="text-sm text-gray-500">日々の自費記録から利用日・品目・単価・数量を記録</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <span class="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-medium">3</span>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">月次請求を生成する</p>
                                    <p class="text-sm text-gray-500">ダッシュボードから「今月の請求を生成」をクリック</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="mt-6 flex items-center justify-between">
        <x-filament::button
            wire:click="previousStep"
            color="gray"
            icon="heroicon-o-arrow-left"
            :disabled="$currentStep === 1"
        >
            戻る
        </x-filament::button>

        <div class="flex items-center gap-3">
            @if($currentStep < $totalSteps)
                <x-filament::button
                    wire:click="skipStep"
                    color="gray"
                >
                    スキップ
                </x-filament::button>
            @endif

            <x-filament::button
                wire:click="nextStep"
                color="primary"
                icon="heroicon-o-arrow-right"
                icon-position="after"
            >
                {{ $currentStep === $totalSteps ? '完了' : '次へ' }}
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>