<?php

namespace digitaldiff\diffbase\widgets;

use Craft;
use craft\base\Widget;

class NewsWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('app', 'News');
    }

    public static function iconPath(): ?string
    {
        return null; // Optionally provide a custom icon path
    }

    public function getBodyHtml(): ?string
    {
        $url = 'https://www.diff.ch/cpwidgets/news';
        $html = '';

        try {
            // Fetch the HTML content
            $response = file_get_contents($url);

            if ($response === false) {
                throw new \Exception('Failed to fetch news.');
            }

            // Directly append the fetched HTML content
            $html .= $response;
        } catch (\Exception $e) {
            $html .= '<p>Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return $html;
    }
}