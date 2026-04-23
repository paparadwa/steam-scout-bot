<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class BotController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        try {
            $update = $telegram->getWebhookUpdate();
            $message = $update->getMessage();

            if ($message) {
                $chatId = $message->getChat()->getId();
                $text = $message->getText();

                if ($text == '/start') {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Привет! Я бот для поиска выгодных предложений в Steam!🎮'."\n\n".
                            'Вот что я умею:'."\n".
                            "/sale - текущие распродажи \n/help - список команд",
                    ]);

                } elseif ($text == '/help') {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "*Список доступных команд:*\n\n".
                            "/start - приветственное сообщение\n".
                            "/help - список команд\n".
                            '/sale - текущие распродажи в Steam',
                        'parse_mode' => 'Markdown',
                    ]);

                } elseif ($text == '/sale') {
                    Log::info("Запрос распродаж от {$chatId}");

                    try {
                        $client = new Client;
                        $response = $client->get('https://monic-viki-toreutic.ngrok-free.dev', [
                            'timeout' => 5,
                            'verify' => false,
                        ]);

                        $games = json_decode($response->getBody(), true);

                        if (isset($games['error'])) {
                            $messageText = 'Ошибка: '.$games['error'];
                        } elseif (empty($games)) {
                            $messageText = '📭 Сейчас нет распродаж';
                        } else {
                            $messageText = "🎮 *Распродажи в Steam:*\n\n";
                            $count = 0;

                            foreach ($games as $game) {
                                if ($count >= 10) {
                                    break;
                                }

                                $messageText .= "*{$game['name']}*\n";
                                $messageText .= "└ Скидка: *-{$game['discount']}%*\n";
                                $messageText .= "└ Цена: {$game['final_price']}\n";
                                $messageText .= "└ [Ссылка]({$game['url']})\n\n";

                                $count++;
                            }

                            if (count($games) > 10) {
                                $messageText .= '_...и ещё '.(count($games) - 10).' игр_';
                            }
                        }
                    } catch (\Exception $e) {
                        $messageText = 'Сервер Steam временно недоступен';
                        Log::error('Ошибка при получении распродаж: '.$e->getMessage());
                    }

                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $messageText,
                        'parse_mode' => 'Markdown',
                    ]);

                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Неизвестная команда 🤯 \nВот что я умею: \n/start - начать общение \n/sale - текущие распродажи",
                    ]);
                }

                Log::info("Сообщение от {$chatId}: {$text}");
            }
        } catch (\Exception $e) {
            Log::error('Ошибка в вебхуке: '.$e->getMessage());
        }

        return response()->json(['ok' => true]);
    }
}
