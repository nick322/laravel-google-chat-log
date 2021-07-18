<?php

namespace Nick\GoogleChatLog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Arr;
use Nick\GoogleChatLog\GoogleChatHandler;

class LogServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     */
    public function register()
    {
        $config = $this->app['config'];

        if (!empty($channel = $config->get('logging-google-chat.channel'))) {
            collect($channel)->map(function ($val, $channel_name) use ($config) {
                $config->set("logging.channels.$channel_name", [
                    'driver' => 'monolog',
                    'level' => Arr::get($val, 'level', 'debug'),
                    'handler' => GoogleChatHandler::class,
                    'handler_with' => [
                        'url' => Arr::get($val, 'url', ''),
                        'bubble' => Arr::get($val, 'bubble', true),
                    ]
                ]);

                if (Arr::get($val, 'append-stack-channels', false)) {
                    $config->set(
                        'logging.channels.stack.channels',
                        array_merge(
                            (array) $config->get('logging.channels.stack.channels'),
                            (array) $channel_name
                        )
                    );
                }
            });
        }
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/logging-google-chat.php' => config_path('logging-google-chat.php'),
            ], 'logging-google-chat:config');
        }
    }
}
