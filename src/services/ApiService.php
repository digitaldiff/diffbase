<?php
namespace digitaldiff\diffbase\services;

use craft\base\Component;
use digitaldiff\diffbase\Plugin;

/**
 * Class ApiService
 *
 * This service handles API-related business logic for the plugin.
 * It provides methods for validating API keys and retrieving system data.
 *
 * @package digitaldiff\diffbase\services
 */
class ApiService extends Component
{
    /**
     * Validates the provided API key against the stored plugin settings.
     *
     * @param string $key The API key to validate.
     * @return bool True if the API key matches the stored key, false otherwise.
     */
    public function validateApiKey(string $key): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        return $settings->apiKey === $key;
    }

    /**
     * Retrieves system data for the API, including plugin and API status.
     *
     * @return array An associative array containing:
     * - `api_enabled` (bool): Whether the API is enabled (based on the presence of an API key).
     * - `version` (string): The current version of the plugin.
     */
    public function getSystemData(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        return [
            'api_enabled' => !empty($settings->apiKey),
            'version' => Plugin::getInstance()->getVersion()
        ];
    }
}
