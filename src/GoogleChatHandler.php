<?php

namespace Nick\GoogleChatLog;

use Exception;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Logger;
use Monolog\Utils;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class GoogleChatHandler extends AbstractProcessingHandler
{
    use \Illuminate\Support\Traits\Macroable;

    /**
     * @var NormalizerFormatter
     */
    private $normalizerFormatter;

    /**
     * Room channel
     * @var string
     */
    private $channel;

    public function __construct(
        ?string $url = null,
        $level = Logger::CRITICAL,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->setChannel($url);
        $this->normalizerFormatter = new NormalizerFormatter();
    }

    /**
     * Channel used by the bot when posting
     *
     * @param ?string $channel
     *
     * @return static
     */
    public function setChannel(?string $channel = null): self
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Get the card content.
     *
     * @return array
     */
    public function getRequestContent(): array
    {
        if (!app()->runningInConsole()) {
            $request = request();
            return [
                'URI' => $request->fullUrl(),
                'HTTP_METHOD' => $request->getMethod(),
                'REQ_ATTR' => substr(json_encode($request->all(), JSON_PRETTY_PRINT), 0, 4096)
            ];
        }

        return [
            'ARGV' => json_encode($_SERVER['argv'] ?? '', JSON_PRETTY_PRINT),
        ];
    }

    /**
     * Writes the record down to the log of the implementing handler.
     *
     * @param  array  $record
     *
     * @throws \Exception
     */
    protected function write(array $record): void
    {
        Http::post($this->getWebhookUrl(), $this->getRequestBody($record));
    }

    /**
     * Get the request body content.
     *
     * @param  array  $record
     * @return array
     */
    protected function getRequestBody(array $record): array
    {
        $color = [
            Logger::DEBUG => '#000000',
            Logger::INFO => '#48d62f',
            Logger::NOTICE => '#00aeff',
            Logger::WARNING => '#ffc400',
            Logger::ERROR => '#ff1100',
            Logger::CRITICAL => '#ff1100',
            Logger::ALERT => '#ff1100',
            Logger::EMERGENCY => '#ff1100',
        ][$record['level']] ?? '#ff1100';

        $now = Carbon::now(config('app.timezone'))->toDateTimeString();

        Arr::set($attachment, 'fields', []);
        Arr::set($record, 'requestContent', $this->getRequestContent());

        foreach (array('requestContent', 'extra', 'context') as $key) {
            if (empty($record[$key])) {
                continue;
            }

            // Add all extra fields as individual fields in attachment
            $attachment['fields'] = array_merge(
                $attachment['fields'],
                $this->generateAttachmentFields($record[$key])
            );
        }

        return [
            'text' => "at : {$now} \n" . 'message : ' . substr($record['message'], 0, 4096),
            'cards' => [
                [
                    'sections' => [
                        'widgets' => [
                            [
                                'keyValue' => [
                                    'topLabel' => 'ENV',
                                    'content' => config('app.env'),
                                ],
                            ],
                            [
                                'keyValue' => [
                                    'topLabel' => 'Level',
                                    'content' => "<font color='{$color}'>{$record['level_name']}</font>",
                                ],
                            ],
                            $attachment['fields']
                        ],

                    ],

                ],
            ],
        ];
    }

    /**
     * Get the webhook url.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function getWebhookUrl()
    {
        if (!$this->channel) {
            throw new Exception('Google chat webhook url is not configured.');
        }

        return $this->channel;
    }


    /**
     * Generates attachment field
     *
     * @param string|array $value
     */
    private function generateAttachmentField(string $title, $value): array
    {
        $value = is_array($value)
            ? sprintf('<i>%s</i>', substr($this->stringify($value), 0, 1990))
            : (string) $value;

        return array(
            'keyValue' => [
                'topLabel' => ucfirst($title),
                'content' => $value,
                'contentMultiline' => true,
            ]
        );
    }

    /**
     * Generates a collection of attachment fields from array
     */
    private function generateAttachmentFields(array $data): array
    {
        $fields = array();

        foreach ($this->normalizerFormatter->format($data) as $key => $value) {
            $fields[] = $this->generateAttachmentField((string) $key, $value);
        }

        return $fields;
    }


    /**
     * Stringifies an array of key/value pairs to be used in attachment fields
     */
    public function stringify(array $fields): string
    {
        $normalized = $this->normalizerFormatter->format($fields);

        $hasSecondDimension = count(array_filter($normalized, 'is_array'));
        $hasNonNumericKeys = !count(array_filter(array_keys($normalized), 'is_numeric'));

        return $hasSecondDimension || $hasNonNumericKeys
            ? Utils::jsonEncode($normalized, JSON_PRETTY_PRINT | Utils::DEFAULT_JSON_FLAGS)
            : Utils::jsonEncode($normalized, Utils::DEFAULT_JSON_FLAGS);
    }
}