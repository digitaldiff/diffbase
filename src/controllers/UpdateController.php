<?php

namespace digitaldiff\diffbase\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class UpdateController extends Controller
{
    protected array|bool|int $allowAnonymous = ['composer-update'];
    public $enableCsrfValidation = false;

    public function actionComposerUpdate(): Response
    {
        Craft::$app->getResponse()->getHeaders()->set('Access-Control-Allow-Origin', 'https://flow.diff.ch');

        $plugin = Craft::$app->getPlugins()->getPlugin('diffbase');
        $settings = $plugin->getSettings();

        $providedKey = Craft::$app->getRequest()->getParam('key') ??
            Craft::$app->getRequest()->getHeaders()->get('X-API-Key');

        if (!$providedKey || $providedKey !== $settings->apiKey) {
            Craft::$app->getResponse()->setStatusCode(401);
            return $this->asJson(['error' => 'Invalid API key']);
        }

        set_time_limit(300);

        $command = 'cd ' . CRAFT_BASE_PATH . ' && composer update digitaldiff/diffbase 2>&1';
        exec($command, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);

        return $this->asJson([
            'success' => $exitCode === 0,
            'output' => $output,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
