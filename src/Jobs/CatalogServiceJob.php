<?php
/**
 * This file is part of bigperson/laravel-exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\LaravelExchange1C\Jobs;

use Bigperson\Exchange1C\Services\AuthService;
use Bigperson\Exchange1C\Services\CatalogService;
use Bigperson\Exchange1C\Services\CategoryService;
use Bigperson\Exchange1C\Services\OfferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Session\Store as SessionStore;

class CatalogServiceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60 * 10;

    private $requestData;
    private $sessionData;

    public function __construct(array $requestData, array $sessionData)
    {
        $this->requestData = $requestData;
        $this->sessionData = $sessionData;
    }

    public function handle(): void
    {
        $mode = $this->requestData['mode'];

        // Создаём новый Request и наполняем его данными
        $request = (new Request())->replace($this->requestData);

        // Создаём Laravel-сессию
        /** @var SessionStore $session */
        $session = app()->make(SessionStore::class);
        $session->start();
        $request->setLaravelSession($session);

        // Добавляем sessionData
        $request->session()->replace($this->sessionData);

        // Регистрируем fakeRequest в контейнере
        app()->instance('fakeRequest', $request);

        // Привязываем fakeRequest к нужным сервисам
        app()
            ->when(AuthService::class)
            ->needs(Request::class)
            ->give('fakeRequest');

        app()
            ->when(CatalogService::class)
            ->needs(Request::class)
            ->give('fakeRequest');

        app()
            ->when(CategoryService::class)
            ->needs(Request::class)
            ->give('fakeRequest');

        app()
            ->when(OfferService::class)
            ->needs(Request::class)
            ->give('fakeRequest');

        // Вызываем метод из нужного сервиса
        $service = app()->make(CatalogService::class);
        $service->$mode();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return ['1cExchange', 'mode: '.$this->requestData['mode'].', file: '.$this->requestData['filename']];
    }
}
