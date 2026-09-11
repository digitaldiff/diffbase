<?php
namespace digitaldiff\diffbase\controllers;

use Craft;
use craft\errors\BusyResourceException;
use craft\errors\MissingComponentException;
use craft\errors\StaleResourceException;
use craft\web\Controller;
use digitaldiff\diffbase\models\Settings;
use digitaldiff\diffbase\Plugin;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class CpController
 *
 * This controller handles the Control Panel (CP) actions for the plugin.
 * It provides methods for managing plugin settings, such as generating or deleting API keys.
 *
 * @package digitaldiff\diffbase\controllers
 */
class CpController extends Controller
{
    /**
     * Renders the main settings page for the plugin.
     *
     * - Initializes the plugin and its settings.
     * - Automatically generates an API key if none exists.
     *
     * @throws MissingComponentException If a required component is missing.
     * @throws Exception If the plugin is not initialized.
     * @return Response The rendered settings page.
     */
    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        if (!$plugin) {
            throw new \Exception('Plugin nicht initialisiert');
        }

        $settings = $plugin->getSettings();
        if (!$settings) {
            // Fallback: neue Settings erstellen
            $settings = new Settings();
        }

        // API-Key automatisch generieren wenn keiner vorhanden
        if (!$settings->apiKey) {
            $settings->apiKey = $settings->generateApiKey();
            $this->saveSettings(['apiKey' => $settings->apiKey]);
            Craft::$app->getSession()->setNotice('API-Key wurde automatisch generiert.');
        }

        return $this->renderTemplate('diffbase/index', [
            'settings' => $settings
        ]);
    }

    /**
     * Retrieves the plugin settings.
     *
     * @return Settings The plugin settings model.
     */
    private function getSettings(): Settings
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        return $settings;
    }

    /**
     * Saves the given values on top of the current plugin settings.
     *
     * `savePluginSettings()` writes only the passed keys to the project config and drops
     * all others, so the full settings array has to be sent every time.
     *
     * @param array $values The settings to change.
     * @return bool Whether the settings were saved.
     */
    private function saveSettings(array $values): bool
    {
        $plugin = Plugin::getInstance();

        return Craft::$app->plugins->savePluginSettings($plugin, array_merge($this->getSettings()->toArray(), $values));
    }

    /**
     * Generates a new API key and saves it to the plugin settings.
     *
     * - Requires a POST request.
     * - Displays a success notice upon completion.
     *
     * @throws NotSupportedException If the operation is not supported.
     * @throws InvalidConfigException If the configuration is invalid.
     * @throws MissingComponentException If a required component is missing.
     * @throws MethodNotAllowedHttpException If the request method is not allowed.
     * @throws ServerErrorHttpException If a server error occurs.
     * @throws BadRequestHttpException If the request is invalid.
     * @throws StaleResourceException If the resource is stale.
     * @throws BusyResourceException If the resource is busy.
     * @throws ErrorException If an error occurs during execution.
     * @throws Exception If an unexpected error occurs.
     * @return Response A redirect response to the posted URL.
     */
    public function actionGenerateApiKey(): Response
    {
        $this->requirePostRequest();

        $settings = Plugin::getInstance()->getSettings();
        $newKey = $settings->generateApiKey();

        // Settings über Plugin speichern
        $this->saveSettings(['apiKey' => $newKey]);
        Craft::$app->getSession()->setNotice('Neuer API-Key wurde generiert.');

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes the existing API key from the plugin settings.
     *
     * - Requires a POST request.
     * - Displays a success notice upon completion.
     *
     * @throws MissingComponentException If a required component is missing.
     * @throws MethodNotAllowedHttpException If the request method is not allowed.
     * @throws BadRequestHttpException If the request is invalid.
     * @return Response A redirect response to the posted URL.
     */
    public function actionDeleteApiKey(): Response
    {
        $this->requirePostRequest();

        // Settings ber Plugin speichern
        $this->saveSettings(['apiKey' => null]);
        Craft::$app->getSession()->setNotice('API-Key wurde gelöscht.');

        return $this->redirectToPostedUrl();
    }

    /**
     * Saves a manual override for the composer binary path.
     *
     * Only needed when the automatic search (PATH + common install locations) can't
     * find composer on this specific host.
     *
     * @throws MissingComponentException If a required component is missing.
     * @throws MethodNotAllowedHttpException If the request method is not allowed.
     * @throws BadRequestHttpException If the request is invalid.
     * @return Response A redirect response to the posted URL.
     */
    public function actionSaveComposerPath(): Response
    {
        $this->requirePostRequest();

        $composerPath = Craft::$app->getRequest()->getBodyParam('composerPath');
        $composerPath = $composerPath !== '' ? $composerPath : null;

        $this->saveSettings(['composerPath' => $composerPath]);
        Craft::$app->getSession()->setNotice('Composer-Pfad gespeichert.');

        return $this->redirectToPostedUrl();
    }

    /**
     * Saves whether the plugin's own widgets should be added to the dashboard.
     *
     * - Applies to all users. Already added widgets are removed by `Plugin::init()`
     *   on each user's next control panel request.
     *
     * @throws MissingComponentException If a required component is missing.
     * @throws MethodNotAllowedHttpException If the request method is not allowed.
     * @throws BadRequestHttpException If the request is invalid.
     * @return Response A redirect response to the posted URL.
     */
    public function actionSaveWidgetSettings(): Response
    {
        $this->requirePostRequest();

        $disableWidgets = (bool)Craft::$app->getRequest()->getBodyParam('disableWidgets');

        $this->saveSettings(['disableWidgets' => $disableWidgets]);
        Craft::$app->getSession()->setNotice('Widget-Einstellung gespeichert.');

        return $this->redirectToPostedUrl();
    }

    /**
     * Saves whether the feedback button (Marker.io) is shown in the control panel.
     *
     * - Applies to all non-admin users, independent of the widget setting.
     *
     * @throws MissingComponentException If a required component is missing.
     * @throws MethodNotAllowedHttpException If the request method is not allowed.
     * @throws BadRequestHttpException If the request is invalid.
     * @return Response A redirect response to the posted URL.
     */
    public function actionSaveFeedbackSettings(): Response
    {
        $this->requirePostRequest();

        $disableFeedback = (bool)Craft::$app->getRequest()->getBodyParam('disableFeedback');

        $this->saveSettings(['disableFeedback' => $disableFeedback]);
        Craft::$app->getSession()->setNotice('Feedback-Einstellung gespeichert.');

        return $this->redirectToPostedUrl();
    }

    /**
     * Renders the settings page for the plugin.
     *
     * - Initializes the plugin and its settings.
     *
     * @throws \Exception If the plugin is not initialized.
     * @return Response The rendered settings page.
     */
    public function actionSettings(): Response
    {
        $plugin = Plugin::getInstance();
        if (!$plugin) {
            throw new \Exception('Plugin nicht initialisiert');
        }

        $settings = $plugin->getSettings();
        if (!$settings) {
            // Fallback: neue Settings erstellen
            $settings = new Settings();
        }

        return $this->renderTemplate('diffbase/index', [
            'plugin' => $plugin,
            'settings' => $settings,
        ]);
    }
}
