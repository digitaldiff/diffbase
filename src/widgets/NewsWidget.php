<?php

declare(strict_types=1);

namespace digitaldiff\diffbase\widgets;

use Craft;
use craft\base\Widget;

class NewsWidget extends Widget
{
    public int $offset = 0;

    private ?string $_html = null;
    private ?string $_entryTitle = null;

    public static function displayName(): string
    {
        return Craft::t('app', 'diff. News');
    }

    public static function iconPath(): ?string
    {
        return null;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['offset'], 'integer', 'min' => 0],
            [['offset'], 'default', 'value' => 0],
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'diffbase/widgets/_news-settings',
            ['widget' => $this]
        );
    }

    public function getTitle(): ?string
    {
        $this->_fetch();
        return $this->_entryTitle ?? static::displayName();
    }

    public function getBodyHtml(): ?string
    {
        $this->_fetch();
        return $this->_html;
    }

    private function _fetch(): void
    {
        if ($this->_html !== null) {
            return;
        }

        $url = 'https://www.diff.ch/cpwidgets/news?offset=' . $this->offset;

        try {
            $response = file_get_contents($url);

            if ($response === false) {
                throw new \Exception('Failed to fetch news.');
            }

            if (preg_match('/<data class="widget-title" value="([^"]*)"/', $response, $matches)) {
                $this->_entryTitle = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $this->_html = $response;
        } catch (\Exception $e) {
            $this->_html = '<p>Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }
}
