<?php
namespace modules\diffbase\models;

use craft\base\Model;

class Settings extends Model
{
    public ?string $apiKey = null;

    public function rules(): array
    {
        return [
            [['apiKey'], 'string', 'max' => 255],
        ];
    }

    public function generateApiKey(): string
    {
        $this->apiKey = bin2hex(random_bytes(32));
        return $this->apiKey;
    }
}
