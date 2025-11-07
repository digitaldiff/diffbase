<?php
namespace digitaldiff\diffbase\models;

use craft\base\Model;

class Settings extends Model
{
    public ?string $apiKey = null;

    public function rules(): array
    {
        return [
            ['apiKey', 'string'],
            ['apiKey', 'default', 'value' => null],
        ];
    }

    public function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
