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
use yii\web\ServerErrorHttpException;

class CpController extends Controller
{

    /**
     * @throws NotSupportedException
     * @throws InvalidConfigException
     * @throws MissingComponentException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws Exception
     */
    public function actionIndex(): \yii\web\Response
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
            Craft::$app->plugins->savePluginSettings($plugin, ['apiKey' => $settings->apiKey]);
            Craft::$app->getSession()->setNotice('API-Key wurde automatisch generiert.');
        }

        return $this->renderTemplate('diffbase/index', [
            'settings' => $settings
        ]);
    }

    private function getSettings(): Settings
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        return $settings;
    }

    /**
     * @throws NotSupportedException
     * @throws InvalidConfigException
     * @throws MissingComponentException
     * @throws MethodNotAllowedHttpException
     * @throws ServerErrorHttpException
     * @throws BadRequestHttpException
     * @throws StaleResourceException
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws Exception
     */
    public function actionGenerateApiKey(): \yii\web\Response
    {
        $this->requirePostRequest();

        $settings = Plugin::getInstance()->getSettings();
        $newKey = $settings->generateApiKey();

        // Settings über Plugin speichern
        Craft::$app->plugins->savePluginSettings(Plugin::getInstance(), ['apiKey' => $newKey]);
        Craft::$app->getSession()->setNotice('Neuer API-Key wurde generiert.');

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws MissingComponentException
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     */
    public function actionDeleteApiKey(): \yii\web\Response
    {
        $this->requirePostRequest();

        // Settings ber Plugin speichern
        Craft::$app->plugins->savePluginSettings(Plugin::getInstance(), ['apiKey' => null]);
        Craft::$app->getSession()->setNotice('API-Key wurde gelscht.');

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws \Exception
     */
    public function actionSettings(): \yii\web\Response
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
