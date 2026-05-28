<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Telegram\Bot\Api;

class BotPoll extends Command
{
    protected $signature = 'bot:poll';

    protected $description = 'Запуск Telegram бота';

    public function handle()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $offset = 0;

        $this->info('Бот запущен. Ждём сообщений');

        while (true) {
            try {
                $updates = $telegram->getUpdates([
                    'offset' => $offset,
                    'timeout' => 30,
                ]);

                foreach ($updates as $update) {
                    $offset = $update->getUpdateId() + 1;

                    $message = $update->getMessage();
                    if ($message) {
                        $chatId = $message->getChat()->getId();
                        $text = $message->getText();

                        if ($text == '/start') {
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => 'Привет! Я бот для поиска выгодных предложений в Steam!🎮' . "\n\n" .
                                    'Вот что я умею:' . "\n" .
                                    "/sale - текущие распродажи \n/free - бесплатные игры \n/subscribe - подписаться на уведомления \n/unsubscribe - отписаться \n/help - список команд",
                            ]);

                        } elseif ($text == '/help') {
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => "*Список доступных команд:*\n\n" .
                                    "/start - приветственное сообщение\n" .
                                    "/help - список команд\n" .
                                    "/sale - текущие распродажи в Steam\n" .
                                    "/free - бесплатные игры в Steam\n" .
                                    "/subscribe - подписаться на уведомления о новых бесплатных играх\n" .
                                    "/unsubscribe - отписаться от уведомлений\n" .
                                    '/search {название} - поиск игры',
                                'parse_mode' => 'Markdown',
                            ]);

                        } elseif ($text == '/sale') {
                            $this->info("Запрос распродаж от {$chatId}");

                            try {
                                $client = new Client;
                                $response = $client->get('https://eve-unputrefiable-monika.ngrok-free.dev/steam/sales', [
                                    'timeout' => 5,
                                    'verify' => false,
                                ]);

                                $games = json_decode($response->getBody(), true);

                                if (isset($games['error'])) {
                                    $messageText = 'Ошибка: ' . $games['error'];
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
                                        $messageText .= '_...и ещё ' . (count($games) - 10) . ' игр_';
                                    }
                                }

                            } catch (\Exception $e) {
                                $messageText = 'Сервер Steam временно недоступен';
                                $this->error('Ошибка: ' . $e->getMessage());
                            }

                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $messageText,
                                'parse_mode' => 'Markdown',
                            ]);

                        } elseif ($text == '/free') {
                            $this->info("Запрос бесплатных игр от {$chatId}");

                            try {
                                $client = new Client;
                                $response = $client->get('https://eve-unputrefiable-monika.ngrok-free.dev/steam/free', [
                                    'timeout' => 5,
                                    'verify' => false,
                                ]);

                                $games = json_decode($response->getBody(), true);

                                if (isset($games['error'])) {
                                    $messageText = 'Ошибка: ' . $games['error'];
                                } elseif (empty($games)) {
                                    $messageText = 'Сейчас нет бесплатных игр';
                                } else {
                                    $messageText = "🎁 *Бесплатные игры в Steam:*\n\n";
                                    $count = 0;

                                    foreach ($games as $game) {
                                        if ($count >= 10) {
                                            break;
                                        }

                                        $messageText .= "*{$game['name']}*\n";
                                        $messageText .= "└ Цена: {$game['final_price']}\n";
                                        $messageText .= "└ [Ссылка]({$game['url']})\n\n";

                                        $count++;
                                    }

                                    if (count($games) > 10) {
                                        $messageText .= '_...и ещё ' . (count($games) - 10) . ' игр_';
                                    }
                                }

                            } catch (\Exception $e) {
                                $messageText = 'Сервер Steam временно недоступен';
                                $this->error('Ошибка: ' . $e->getMessage());
                            }

                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $messageText,
                                'parse_mode' => 'Markdown',
                            ]);
                        } elseif (str_starts_with($text, '/search')) {
                            $searchQuery = trim(substr($text, 7)); // Убираем "/search"

                            if (empty($searchQuery)) {
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => '❌ Укажите название игры. Пример: /search Witcher 3',
                                ]);
                            } else {
                                $this->info("Поиск игры '{$searchQuery}' от {$chatId}");

                                try {
                                    $client = new Client;

                                    // Пробуем найти игру в распродажах
                                    $response = $client->get('https://eve-unputrefiable-monika.ngrok-free.dev/steam/sales', [
                                        'timeout' => 5,
                                        'verify' => false,
                                    ]);

                                    $games = json_decode($response->getBody(), true);
                                    $found = null;

                                    if (is_array($games) && !isset($games['error'])) {
                                        foreach ($games as $game) {
                                            if (stripos($game['name'], $searchQuery) !== false) {
                                                $found = $game;
                                                break;
                                            }
                                        }
                                    }

                                    if ($found) {
                                        $messageText = "🔍 *Найдена игра:*\n\n";
                                        $messageText .= "*{$found['name']}*\n";
                                        $messageText .= "└ Скидка: *-{$found['discount']}%*\n";
                                        $messageText .= "└ Цена со скидкой: {$found['final_price']}\n";
                                        if (isset($found['original_price'])) {
                                            $messageText .= "└ Обычная цена: {$found['original_price']}\n";
                                        }
                                        $messageText .= "└ [Ссылка в Steam]({$found['url']})";
                                    } else {
                                        $messageText = "❌ Игра \"{$searchQuery}\" не найдена в текущих распродажах.\n\nПопробуйте другое название или проверьте позже.";
                                    }

                                } catch (\Exception $e) {
                                    $messageText = 'Сервер временно недоступен';
                                    $this->error('Ошибка: ' . $e->getMessage());
                                }

                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => $messageText,
                                    'parse_mode' => 'Markdown',
                                ]);

                            }
                        } elseif ($text == '/subscribe') {
                            $exists = Subscriber::where('chat_id', $chatId)->exists();
                            if (!$exists) {
                                Subscriber::create(['chat_id' => $chatId]);
                                $reply = 'Вы подписались на уведомления о новых бесплатных играх!';
                            } else {
                                $reply = 'Вы уже подписаны.';
                            }
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $reply,
                            ]);

                        } elseif
                        ($text == '/unsubscribe') {
                            $deleted = Subscriber::where('chat_id', $chatId)->delete();
                            if ($deleted) {
                                $reply = 'Вы отписались от уведомлений.';
                            } else {
                                $reply = 'Вы не были подписаны.';
                            }
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $reply,
                            ]);

                        } else {
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => "Неизвестная команда 🤯 \nВот что я умею: \n/start - начать общение \n/sale - текущие распродажи \n/free - бесплатные игры \n/search {название} - поиск игры \n/subscribe - подписаться \n/unsubscribe - отписаться",
                            ]);
                        }

                        $this->info("Сообщение от {$chatId}: {$text}");
                    }
                }
            } catch
            (\Exception $e) {
                $this->error('Ошибка: ' . $e->getMessage());
                sleep(5);
            }
        }
    }
}
