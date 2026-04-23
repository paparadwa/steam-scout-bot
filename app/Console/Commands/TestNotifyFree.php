<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use Illuminate\Console\Command;
use Telegram\Bot\Api;

class TestNotifyFree extends Command
{
    protected $signature = 'bot:test-notify';

    protected $description = 'Отправка тестового уведомления подписанным пользователям';

    public function handle()
    {
        $subscribers = Subscriber::all();
        if ($subscribers->isEmpty()) {
            $this->info('Нет подписчиков.');

            return;
        }

        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $message = '*Тестовое уведомление!*';

        foreach ($subscribers as $subscriber) {
            try {
                $telegram->sendMessage([
                    'chat_id' => $subscriber->chat_id,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ]);
                $this->info("Уведомление отправлено {$subscriber->chat_id}");
            } catch (\Exception $e) {
                $this->error("Ошибка отправки {$subscriber->chat_id}: ".$e->getMessage());
            }
        }
    }
}
