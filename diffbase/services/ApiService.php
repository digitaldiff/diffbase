<?php

namespace modules\diffbase\services;

use craft\base\Component;
use diffbase\Module;

class ApiService extends Component
{
    public function validateApiKey(string $key): bool
    {
        $settings = Module::getInstance()->getSettings();
        return $settings->apiKey === $key;
    }

    public function getCompanyData(): array
    {
        $settings = Module::getInstance()->getSettings();
        return [
            'company' => $settings->companyName,
            'api_enabled' => $settings->apiEnabled,
            'version' => '1.0.0'
        ];
    }
}

