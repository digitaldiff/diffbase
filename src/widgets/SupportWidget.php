<?php

namespace digitaldiff\diffbase\widgets;

use Craft;
use craft\base\Widget;

class SupportWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Hilfe & Support');
    }

    public static function iconPath(): ?string
    {
        return null; // Optionally provide a custom icon path
    }

    /**
     * @throws \Throwable
     */
    public function getBodyHtml(): ?string
    {
        $actionUrl = Craft::$app->getUrlManager()->createUrl(['diffbase/support/send-email']);
        $csrfTokenName = Craft::$app->getConfig()->getGeneral()->csrfTokenName;
        $csrfTokenValue = Craft::$app->getRequest()->getCsrfToken();

        // Get flash messages
        $successMessage = Craft::$app->getSession()->getFlash('success');
        $errorMessage = Craft::$app->getSession()->getFlash('error');

        $statusMessage = '';
        if ($successMessage) {
            $statusMessage = '<div class="notice success" style="margin-bottom: 20px;"><p>' . htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') . '</p></div>';
        } elseif ($errorMessage) {
            $statusMessage = '<div class="notice error" style="margin-bottom: 20px;"><p>' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</p></div>';
        }

        // Get the current user
        $currentUser = Craft::$app->getUser()->getIdentity();
        $name = $currentUser ? $currentUser->fullName : '';
        $email = $currentUser ? $currentUser->email : '';

        return '
        ' . $statusMessage . '
        <p>Haben Sie Fragen oder benötigen Unterstützung? Füllen Sie einfach das Formular aus, und wir melden uns so schnell wie möglich bei Ihnen.</p>
        <form method="post" action="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" enctype="multipart/form-data">
            <input type="hidden" name="' . htmlspecialchars($csrfTokenName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($csrfTokenValue, ENT_QUOTES, 'UTF-8') . '">
            <input type="hidden" name="action" value="diffbase/support/send-email">
            
                <div class="field">
                    <div class="heading">
                        <label for="name">Dein Name</label>
                    </div>
                    <div class="input">
                        <input type="text" class="text fullwidth" id="name" name="name" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" required>
                    </div>
                </div>

                <div class="field">
                    <div class="heading">
                        <label for="email">Deine E-Mail</label>
                    </div>
                    <div class="input">
                        <input type="email" class="text fullwidth" id="email" name="email" value="' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '" required>
                    </div>
                </div>

                <div class="field">
                    <div class="heading">
                        <label for="message">Beschreibung</label>
                    </div>
                    <div class="input">
                        <textarea id="message" name="message" class="text fullwidth" rows="5" required></textarea>
                    </div>
                </div>
            
                <div class="field">
                    <div class="heading">
                        <label for="attachment">Anhänge</label>
                        <div class="instructions"><p>Optional: Fügen Sie Screenshots oder andere Dateien hinzu (max. 10 MB)</p></div>
                    </div>
                    <div class="input">
                        <input type="file" id="attachment" name="attachment" class="text fullwidth" accept="image/*,.pdf,.doc,.docx,.txt,.zip" />
                    </div>
                </div>
    
                <button type="submit" class="btn submit">Support Ticket eröffnen</button>
        </form>
    ';
    }
}

