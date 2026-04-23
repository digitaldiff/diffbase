<?php

namespace digitaldiff\diffbase\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use yii\base\InvalidConfigException;
use yii\web\Response;
use craft\web\UploadedFile;

class SupportController extends Controller
{
    /**
     * Allow anonymous access to the send-email action
     */
    protected array|int|bool $allowAnonymous = ['send-email'];

    /**
     * @throws InvalidConfigException
     * @throws MissingComponentException
     */
    public function actionSendEmail(): Response
    {
        $request = Craft::$app->getRequest();

        // Get form data
        $name = $request->getBodyParam('name');
        $email = $request->getBodyParam('email');
        $message = $request->getBodyParam('message');
        $uploadedFile = UploadedFile::getInstanceByName('attachment');

        // Handle file upload with better error handling
        $filePath = null;
        $attachmentInfo = null;

        if ($uploadedFile) {
            try {
                // Validiere die Datei
                $maxSize = 10 * 1024 * 1024; // 10 MB
                if ($uploadedFile->size > $maxSize) {
                    Craft::$app->getSession()->setFlash('error', 'Die Datei ist zu groß. Maximum: 10 MB');
                    return $this->redirect($request->getReferrer());
                }

                // Erstelle einen sicheren Dateinamen
                $safeFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $uploadedFile->name);
                $tempPath = Craft::$app->getPath()->getTempPath();

                // Stelle sicher, dass das Temp-Verzeichnis existiert
                if (!is_dir($tempPath)) {
                    mkdir($tempPath, 0755, true);
                }

                $filePath = $tempPath . DIRECTORY_SEPARATOR . $safeFileName;

                // Speichere die Datei
                if (!$uploadedFile->saveAs($filePath)) {
                    Craft::error('Fehler beim Speichern der Datei: ' . $filePath, __METHOD__);
                    Craft::$app->getSession()->setFlash('error', 'Fehler beim Hochladen der Datei.');
                    return $this->redirect($request->getReferrer());
                }

                $attachmentInfo = [
                    'Name' => $uploadedFile->name,
                    'Typ' => $uploadedFile->type,
                    'Größe' => $this->formatFileSize($uploadedFile->size)
                ];

                Craft::info('Datei erfolgreich hochgeladen: ' . $uploadedFile->name . ' (' . $this->formatFileSize($uploadedFile->size) . ')', __METHOD__);

            } catch (\Exception $e) {
                Craft::error('Fehler beim File-Upload: ' . $e->getMessage(), __METHOD__);
                Craft::$app->getSession()->setFlash('error', 'Fehler beim Hochladen der Datei: ' . $e->getMessage());
                return $this->redirect($request->getReferrer());
            }
        }

        // Prepare email
        $currentSite = Craft::$app->sites->currentSite;
        $user = Craft::$app->user->identity;

        // Sammle umfassende Kontextinformationen
        $contextInfo = [
            'Site Informationen' => [
                'Site Name' => $currentSite->name,
                'Base URL' => $currentSite->baseUrl,
                'Language' => $currentSite->language,
                'Handle' => $currentSite->handle,
            ],
            'Benutzer Information' => [
                'Name' => $user ? $user->fullName : 'Nicht angemeldet',
                'Email' => $user ? $user->email : 'N/A',
                'Username' => $user ? $user->username : 'N/A'
            ],
            'Craft CMS' => [
                'Version' => Craft::$app->getVersion(),
                'Edition' => Craft::$app->getEditionName(),
                'Environment' => Craft::$app->getConfig()->getGeneral()->devMode ? 'Development' : 'Production',
            ],
            'Server' => [
                'IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            ],
            'Request' => [
                'URL' => $request->getAbsoluteUrl(),
                'Referrer' => $request->getReferrer() ?? 'Direct',
            ],
            'Browser' => [
                'User Agent' => $request->getUserAgent() ?? 'Unknown',
                'Accept Language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown',
            ]
        ];

        // Füge Anhang-Info hinzu, falls vorhanden
        if ($attachmentInfo) {
            $contextInfo['Anhang'] = $attachmentInfo;
        }

        // Formatiere die Informationen für die E-Mail
        $additionalContext = "\n\n" . str_repeat('=', 60) . "\n";
        $additionalContext .= "SYSTEM INFORMATIONEN\n";
        $additionalContext .= str_repeat('=', 60) . "\n\n";

        foreach ($contextInfo as $section => $data) {
            $additionalContext .= strtoupper($section) . ":\n";
            $additionalContext .= str_repeat('-', 60) . "\n";
            foreach ($data as $key => $value) {
                $additionalContext .= sprintf("  %-20s: %s\n", $key, $value);
            }
            $additionalContext .= "\n";
        }

        $mailer = Craft::$app->getMailer();
        $emailMessage = $mailer->compose()
            ->setTo('ladner@diff.ch')
            ->setFrom(Craft::$app->getProjectConfig()->get('email.fromEmail'))
            ->setReplyTo([$email => $name])
            ->setSubject('Neue Supportanfrage von ' . $currentSite->name)
            ->setTextBody($message . $additionalContext);

        // Attach file if uploaded
        if ($filePath && file_exists($filePath)) {
            Craft::info('Versuche Datei anzuhängen: ' . $filePath . ' (Größe: ' . filesize($filePath) . ' bytes)', __METHOD__);
            Craft::info('Original Dateiname: ' . $uploadedFile->name, __METHOD__);
            Craft::info('Content-Type: ' . $uploadedFile->type, __METHOD__);

            try {
                // Nutze die einfachere attach-Methode von Swift Mailer
                $emailMessage->attach($filePath, [
                    'fileName' => $uploadedFile->name,
                    'contentType' => $uploadedFile->type ?: 'application/octet-stream'
                ]);
                Craft::info('Datei erfolgreich an E-Mail angehängt', __METHOD__);
            } catch (\Exception $e) {
                Craft::error('Fehler beim Anhängen der Datei: ' . $e->getMessage(), __METHOD__);
            }
        } else {
            if ($filePath) {
                Craft::warning('Datei existiert nicht: ' . $filePath, __METHOD__);
            } else {
                Craft::info('Keine Datei zum Anhängen vorhanden', __METHOD__);
            }
        }

        // Send email
        $success = false;
        try {
            Craft::info('Sende E-Mail...', __METHOD__);
            $success = $emailMessage->send();

            if ($success) {
                Craft::info('Support-E-Mail erfolgreich versendet an ladner@diff.ch' . ($filePath ? ' (mit Anhang)' : ''), __METHOD__);
                Craft::$app->getSession()->setFlash('success', 'Ihre Nachricht wurde erfolgreich versendet!');
            } else {
                Craft::error('E-Mail konnte nicht versendet werden', __METHOD__);
                Craft::$app->getSession()->setFlash('error', 'Die Nachricht konnte nicht versendet werden.');
            }
        } catch (\Exception $e) {
            Craft::error('Fehler beim E-Mail-Versand: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getSession()->setFlash('error', 'Fehler beim Versenden: ' . $e->getMessage());
        }

        // Cleanup: Lösche temporäre Datei NACH dem Versand
        if ($filePath && file_exists($filePath)) {
            try {
                // Kleine Verzögerung um sicherzustellen, dass die E-Mail komplett gesendet wurde
                sleep(1);
                unlink($filePath);
                Craft::info('Temporäre Datei gelöscht: ' . $filePath, __METHOD__);
            } catch (\Exception $e) {
                Craft::warning('Konnte temporäre Datei nicht löschen: ' . $filePath . ' - ' . $e->getMessage(), __METHOD__);
            }
        }

        // Redirect back to the referrer or dashboard
        $referrer = $request->getReferrer();
        if (!$referrer || $referrer === $request->getAbsoluteUrl()) {
            // Fallback zum Dashboard, wenn kein Referrer vorhanden
            $referrer = Craft::$app->getConfig()->getGeneral()->cpTrigger . '/dashboard';
        }

        return $this->redirect($referrer);
    }

    /**
     * Formatiert Dateigröße in lesbare Form
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

