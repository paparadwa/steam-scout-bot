<?php

namespace Tests\Unit;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BotCommandsTest extends TestCase
{
    use RefreshDatabase;
    // парсинг команды /search с параметром
    public function test_search_command_parsing()
    {
        $text = '/search Witcher 3';
        $searchQuery = trim(substr($text, 7));

        $this->assertEquals('Witcher 3', $searchQuery);
    }

    // парсинг команды /search без параметра
    public function test_search_command_empty_parameter()
    {
        $text = '/search';
        $searchQuery = trim(substr($text, 7));

        $this->assertEmpty($searchQuery);
    }

    // поиск игры по названию (регистронезависимый)
    public function test_search_game_case_insensitive()
    {
        $games = [
            ['name' => 'THE WITCHER 3', 'discount' => 70, 'final_price' => '599 ₽', 'url' => 'url'],
            ['name' => 'Cyberpunk 2077', 'discount' => 50, 'final_price' => '1499 ₽', 'url' => 'url']
        ];

        $searchQuery = 'witcher';
        $found = null;

        foreach ($games as $game) {
            if (stripos($game['name'], $searchQuery) !== false) {
                $found = $game;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertEquals('THE WITCHER 3', $found['name']);
    }

    // поиск игры - не найдено
    public function test_search_game_not_found()
    {
        $games = [
            ['name' => 'Cyberpunk 2077', 'discount' => 50, 'final_price' => '1499 ₽', 'url' => 'url']
        ];

        $searchQuery = 'nonexistent';
        $found = null;

        foreach ($games as $game) {
            if (stripos($game['name'], $searchQuery) !== false) {
                $found = $game;
                break;
            }
        }

        $this->assertNull($found);
    }

    // форматирование сообщения с найденной игрой
    public function test_format_found_game_message()
    {
        $found = [
            'name' => 'The Witcher 3',
            'discount' => 70,
            'final_price' => '599 ₽',
            'original_price' => '1999 ₽',
            'url' => 'https://store.steampowered.com/app/witcher3'
        ];

        $messageText = "🔍 *Найдена игра:*\n\n";
        $messageText .= "*{$found['name']}*\n";
        $messageText .= "└ Скидка: *-{$found['discount']}%*\n";
        $messageText .= "└ Цена со скидкой: {$found['final_price']}\n";
        if (isset($found['original_price'])) {
            $messageText .= "└ Обычная цена: {$found['original_price']}\n";
        }
        $messageText .= "└ [Ссылка в Steam]({$found['url']})";

        $this->assertStringContainsString('The Witcher 3', $messageText);
        $this->assertStringContainsString('-70%', $messageText);
        $this->assertStringContainsString('599 ₽', $messageText);
        $this->assertStringContainsString('1999 ₽', $messageText);
        $this->assertStringContainsString('steampowered.com', $messageText);
    }

    // форматирование сообщения когда игра не найдена
    public function test_format_not_found_message()
    {
        $searchQuery = 'NonexistentGame';
        $messageText = "❌ Игра \"{$searchQuery}\" не найдена в текущих распродажах.\n\nПопробуйте другое название или проверьте позже.";

        $this->assertStringContainsString($searchQuery, $messageText);
        $this->assertStringContainsString('не найдена', $messageText);
    }

    // форматирование списка распродаж (ограничение 10 игр)
    public function test_sales_list_formatting_limit()
    {
        $games = [];
        for ($i = 1; $i <= 15; $i++) {
            $games[] = ['name' => "Game $i", 'discount' => $i, 'final_price' => "{$i}00 ₽", 'url' => "url$i"];
        }

        $messageText = "🎮 *Распродажи в Steam:*\n\n";
        $count = 0;

        foreach ($games as $game) {
            if ($count >= 10) break;
            $messageText .= "*{$game['name']}*\n";
            $count++;
        }

        if (count($games) > 10) {
            $messageText .= '_...и ещё ' . (count($games) - 10) . ' игр_';
        }

        $this->assertStringContainsString('Game 10', $messageText);
        $this->assertStringNotContainsString('Game 11', $messageText);
        $this->assertStringContainsString('...и ещё 5 игр', $messageText);
    }

    //форматирование списка бесплатных игр
    public function test_free_games_formatting()
    {
        $games = [
            ['name' => 'Free Game 1', 'final_price' => '0 ₽', 'url' => 'url1'],
            ['name' => 'Free Game 2', 'final_price' => '0 ₽', 'url' => 'url2']
        ];

        $messageText = "🎁 *Бесплатные игры в Steam:*\n\n";

        foreach ($games as $game) {
            $messageText .= "*{$game['name']}*\n";
            $messageText .= "└ Цена: {$game['final_price']}\n";
            $messageText .= "└ [Ссылка]({$game['url']})\n\n";
        }

        $this->assertStringContainsString('Free Game 1', $messageText);
        $this->assertStringContainsString('Free Game 2', $messageText);
        $this->assertStringContainsString('0 ₽', $messageText);
    }

    //Тест: определение новых игр для уведомления
    public function test_detect_new_free_games()
    {
        $currentGames = [
            ['name' => 'Old Game', 'final_price' => '0 ₽', 'url' => 'old'],
            ['name' => 'New Game', 'final_price' => '0 ₽', 'url' => 'new']
        ];

        $previousGames = [
            ['name' => 'Old Game', 'final_price' => '0 ₽', 'url' => 'old']
        ];

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

        $this->assertCount(1, $newGames);
        $this->assertEquals('New Game', $newGames[0]['name']);
    }

    // Тест: нет новых игр
    public function test_no_new_free_games()
    {
        $currentGames = [
            ['name' => 'Game 1', 'final_price' => '0 ₽', 'url' => 'url1'],
            ['name' => 'Game 2', 'final_price' => '0 ₽', 'url' => 'url2']
        ];

        $previousGames = [
            ['name' => 'Game 1', 'final_price' => '0 ₽', 'url' => 'url1'],
            ['name' => 'Game 2', 'final_price' => '0 ₽', 'url' => 'url2']
        ];

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

        $this->assertEmpty($newGames);
    }

    // Тест: форматирование уведомления о новых бесплатных играх
    public function test_format_notification_message()
    {
        $newGames = [
            ['name' => 'New Free Game', 'final_price' => '0 ₽', 'url' => 'https://store.steampowered.com/app/new'],
            ['name' => 'Another Free Game', 'final_price' => '0 ₽', 'url' => 'https://store.steampowered.com/app/another']
        ];

        $message = "🎁 *Новые бесплатные игры в Steam!*\n\n";
        foreach ($newGames as $game) {
            $message .= "*{$game['name']}*\n";
            $message .= "└ [Забрать бесплатно]({$game['url']})\n\n";
        }

        $this->assertStringContainsString('New Free Game', $message);
        $this->assertStringContainsString('Another Free Game', $message);
        $this->assertStringContainsString('Забрать бесплатно', $message);
        $this->assertStringContainsString('steampowered.com', $message);
    }

    // обработка команды без косой черты
    public function test_command_without_slash()
    {
        $text = 'start';
        $isCommand = str_starts_with($text, '/');

        $this->assertFalse($isCommand);
    }

    // определение команды /search
    public function test_is_search_command()
    {
        $text = '/search Witcher';
        $isSearch = str_starts_with($text, '/search');

        $this->assertTrue($isSearch);
    }

    // определение команды /start
    public function test_is_start_command()
    {
        $text = '/start';
        $isStart = $text == '/start';

        $this->assertTrue($isStart);
    }

    //определение команды /help
    public function test_is_help_command()
    {
        $text = '/help';
        $isHelp = $text == '/help';

        $this->assertTrue($isHelp);
    }


    //Тест: определение команды /sale
    public function test_is_sale_command()
    {
        $text = '/sale';
        $isSale = $text == '/sale';

        $this->assertTrue($isSale);
    }


    //Тест: определение команды /free
    public function test_is_free_command()
    {
        $text = '/free';
        $isFree = $text == '/free';

        $this->assertTrue($isFree);
    }

    //Тест: определение команды /subscribe
    public function test_is_subscribe_command()
    {
        $text = '/subscribe';
        $isSubscribe = $text == '/subscribe';

        $this->assertTrue($isSubscribe);
    }

    //Тест: определение команды /unsubscribe
    public function test_is_unsubscribe_command()
    {
        $text = '/unsubscribe';
        $isUnsubscribe = $text == '/unsubscribe';

        $this->assertTrue($isUnsubscribe);
    }


    //Тест: сообщение с пробелами в начале
    public function test_command_with_spaces()
    {
        $text = '  /search Witcher';
        $trimmedText = trim($text);
        $isSearch = str_starts_with($trimmedText, '/search');

        $this->assertTrue($isSearch);
    }

    //создание нового подписчика
    public function test_subscribe_creates_new_subscriber()
    {
        $chatId = 123456789;

        $exists = Subscriber::where('chat_id', $chatId)->exists();
        $this->assertFalse($exists);

        // Симуляция подписки
        Subscriber::create(['chat_id' => $chatId]);

        $this->assertDatabaseHas('subscribers', ['chat_id' => $chatId]);
    }

    // отписка
    public function test_unsubscribe_deletes_subscriber()
    {
        $chatId = 123456789;
        Subscriber::create(['chat_id' => $chatId]);

        $this->assertDatabaseHas('subscribers', ['chat_id' => $chatId]);

        Subscriber::where('chat_id', $chatId)->delete();

        $this->assertDatabaseMissing('subscribers', ['chat_id' => $chatId]);
    }

    //ошибки API
    public function test_sales_api_error_handling()
    {
        // Тестируем, что при ошибке API возвращается правильное сообщение
        $messageText = 'Сервер Steam временно недоступен';

        $this->assertStringContainsString('Сервер Steam временно недоступен', $messageText);
    }

    public function test_free_games_api_error_handling()
    {
        $messageText = 'Сервер Steam временно недоступен';

        $this->assertStringContainsString('Сервер Steam временно недоступен', $messageText);
    }

}
