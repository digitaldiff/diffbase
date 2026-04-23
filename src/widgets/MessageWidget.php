<?php

namespace digitaldiff\diffbase\widgets;

use Craft;
use craft\base\Widget;

class MessageWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Wichtige Informationen');
    }

    public static function iconPath(): ?string
    {
        return null;
    }

    public function getBodyHtml(): ?string
    {
        $url = 'https://www.diff.ch/cpwidgets/important';
        $html = '';

        try {
            // Fetch the HTML content
            $response = file_get_contents($url);

            if ($response === false || empty(trim($response))) {
                // Return null if the content is empty or fetching fails
                return null;
            }

            // Fetch the alert icon
            $iconPath = Craft::getAlias('@app/icons/alert.svg');
            $icon = file_exists($iconPath) ? file_get_contents($iconPath) : '';

            // Add a width of 50px to the SVG
            if (!empty($icon)) {
                $icon = str_replace('<svg', '<svg style="width:50px;fill:var(--primary-button-bg);"', $icon);
            }

            // Build the HTML with the icon and fetched content
            $html .= '<div class="message-widget" style="display:flex;gap:30px;justify-content: space-between;">';
            $html .= '    <div class="content">' . $response . '</div>';
            $html .= '    <div class="icon">' . $icon . '</div>';
            $html .= '</div>';
        } catch (\Exception $e) {
            // Return null if an exception occurs
            return null;
        }

        return $html;
    }
}