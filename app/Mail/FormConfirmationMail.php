<?php

namespace App\Mail;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FormConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $downloadUrl  資料等のダウンロードURL（署名付き）。catalog・diagnosis以外はnull
     */
    public function __construct(
        public readonly FormSubmission $submission,
        public readonly ?string $downloadUrl = null,
    ) {
    }

    public function build()
    {
        $subjects = [
            'catalog' => '【あんしん】資料請求を受け付けました',
            'demo' => '【あんしん】無料デモのお申し込みを受け付けました',
            'diagnosis' => '【あんしん】診断書フォームのダウンロード受付が完了しました',
            'prospect' => '【あんしん】入居相談・資料請求を受け付けました',
            'inquiry' => '【あんしん】お問い合わせを受け付けました',
        ];

        return $this->subject($subjects[$this->submission->type] ?? '【あんしん】お問い合わせを受け付けました')
            ->view('emails.form_confirmation')
            ->onQueue('emails');
    }
}
