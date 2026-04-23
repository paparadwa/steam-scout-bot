<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use Illuminate\Console\Command;
use Telegram\Bot\Api;
use GuzzleHttp\Client;

class NotifyFreeGames extends Command
{
    protected $signature = 'bot:notify-free';
    protected $description = 'Проверка новых бесплатных игр и уведомление подписчиков';

    public function handle()
    {
        $client = new Client();
        $response = $client->get('https://eve-unputrefiable-monika.ngrok-free.dev/steam/free', [
            'timeout' => 5,
            'verify' => false,
        ]);

        $currentGames = json_decode($response->getBody(), true);

        // храним, что уже отправляли
        $previousGames = cache('last_free_games_notified', []);

        // поиск новых игр
        $newGames = [];
        foreach ($currentGames as $game) {
            $found = false;
            foreach ($previousGames as $old) {
                if ($old['name'] == $game['name']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $newGames[] = $game;
            }
        }

        // новые игры — рассылаем
        if (!empty($newGames)) {
            $subscribers = Subscriber::all();  // ← как в вашем коде

            if ($subscribers->isEmpty()) {
                $this->info('Нет подписчиков');
                return;
            }

            $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

            // сообщение о новых играх
            $message = "🎁 *Новые бесплатные игры в Steam!*\n\n";
            foreach ($newGames as $game) {
                $message .= "*{$game['name']}*\n";
                $message .= "└ [Забрать бесплатно]({$game['url']})\n\n";
            }

            foreach ($subscribers as $subscriber) {
                try {
                    $telegram->sendMessage([
                        'chat_id' => $subscriber->chat_id,
                        'text' => $message,
                        'parse_mode' => 'Markdown',
                    ]);
                    $this->info("Уведомление отправлено {$subscriber->chat_id}");
                } catch (\Exception $e) {
                    $this->error("Ошибка отправки {$subscriber->chat_id}: " . $e->getMessage());
                }
            }

            cache(['last_free_games_notified' => $currentGames], now()->addHours(6));

            $this->info("Отправлено уведомлений: " . count($subscribers));
        } else {
            $this->info("Новых бесплатных игр нет");
        }
    }
}
