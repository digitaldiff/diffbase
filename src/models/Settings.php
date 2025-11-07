<?php
namespace digitaldiff\diffbase\models;

use craft\base\Model;
use Random\RandomException;

/**
 * Class Settings
 *
 * This model represents the settings for the "diff. base plugin".
 * It is used to store and validate the plugin's configuration, such as the API key.
 *
 * @package digitaldiff\diffbase\models
 */
class Settings extends Model
{
    /**
     * @var string|null $apiKey The API key used for authentication. Defaults to null.
     */
    public ?string $apiKey = null;

    /**
     * Returns the validation rules for the model's attributes.
     *
     * @return array The validation rules for the `apiKey` attribute.
     */
    public function rules(): array
    {
        return [
            ['apiKey', 'string'], // Ensures the API key is a string.
            ['apiKey', 'default', 'value' => null], // Sets the default value of the API key to null.
        ];
    }

    /**
     * Generates a new random API key.
     *
     * This method uses `random_bytes` to generate a secure 32-byte random string,
     * which is then converted to a hexadecimal representation.
     *
     * @throws RandomException If an appropriate source of randomness cannot be found.
     * @return string The generated API key as a 64-character hexadecimal string.
     */
    public function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
