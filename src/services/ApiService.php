<?php
namespace digitaldiff\diffbase\services;

use craft\base\Component;
use digitaldiff\diffbase\Plugin;

class ApiService extends Component
{
    public function validateApiKey(string $key): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        return $settings->apiKey === $key;
    }

    public function getSystemData(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        return [
            'api_enabled' => !empty($settings->apiKey),
            'version' => Plugin::getInstance()->getVersion()
        ];
    }
}
