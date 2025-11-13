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
        $plugin = Craft::$app->getPlugins()->getPlugin('diffbase');
        $settings = $plugin->getSettings();

        $providedKey = Craft::$app->getRequest()->getParam('key') ??
            Craft::$app->getRequest()->getHeaders()->get('X-API-Key');

        if (!$providedKey || $providedKey !== $settings->apiKey) {
            return $this->asJson(['error' => 'Invalid API key'], 401);
        }

        set_time_limit(300);

        $command = 'cd ' . CRAFT_BASE_PATH . ' && composer update digitaldiff/diffbase 2>&1';
        $output = shell_exec($command);

        return $this->asJson([
            'success' => !str_contains($output, 'error'),
            'output' => $output,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
