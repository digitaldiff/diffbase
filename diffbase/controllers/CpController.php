<?php
namespace modules\diffbase\controllers;

use Craft;
use craft\errors\BusyResourceException;
use craft\errors\MissingComponentException;
use craft\errors\StaleResourceException;
use craft\web\Controller;
use modules\diffbase\models\Settings;
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
        $settings = $this->getSettings();

        // API-Key automatisch generieren wenn keiner vorhanden
        if (!$settings->apiKey) {
            $settings->apiKey = $settings->generateApiKey();
            Craft::$app->getProjectConfig()->set('diffbase.apiKey', $settings->apiKey);
            Craft::$app->getSession()->setNotice('API-Key wurde automatisch generiert.');
        }

        return $this->renderTemplate('diffbase/index', [
            'settings' => $settings
        ]);
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

        $settings = $this->getSettings();
        $newKey = $settings->generateApiKey();

        Craft::$app->getProjectConfig()->set('diffbase.apiKey', $newKey);
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

        Craft::$app->getProjectConfig()->remove('diffbase.apiKey');
        Craft::$app->getSession()->setNotice('API-Key wurde gelöscht.');

        return $this->redirectToPostedUrl();
    }

    private function getSettings(): Settings
    {
        $settings = new Settings();
        $settings->apiKey = Craft::$app->getProjectConfig()->get('diffbase.apiKey');

        return $settings;
    }
}
