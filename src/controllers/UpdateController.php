<?php

namespace digitaldiff\diffbase\controllers;

use Craft;
use craft\web\Controller;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
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

        // exec()/shell_exec() sind auf Managed-/Shared-Hosting oft deaktiviert; proc_open() (das
        // Symfony\Process nutzt) ist häufig noch aktiv, u.a. weil Craft es selbst für den
        // Sendmail-Mailversand (Symfony\Component\Mailer\...\ProcessStream) benötigt.
        if (!function_exists('proc_open')) {
            return $this->asJson([
                'success' => false,
                'output' => 'proc_open() ist auf diesem Server deaktiviert. Composer-Update kann nicht ausgeführt werden.',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }

        $composerBinary = (new ExecutableFinder())->find('composer', null, [
            '/usr/local/bin',
            '/usr/bin',
            '/opt/homebrew/bin',
            getenv('HOME') . '/.composer/vendor/bin',
            getenv('HOME') . '/bin',
        ]);

        if (!$composerBinary) {
            return $this->asJson([
                'success' => false,
                'output' => 'composer-Binary konnte nicht gefunden werden (weder im PATH noch an gängigen Standardorten).',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }

        try {
            $process = new Process(
                [$composerBinary, 'update', 'digitaldiff/diffbase', '--no-interaction'],
                CRAFT_BASE_PATH,
                null,
                null,
                300
            );
            $process->run();
            $success = $process->isSuccessful();
            $output = $process->getOutput() . $process->getErrorOutput();
        } catch (Throwable $e) {
            $success = false;
            $output = 'Process-Ausführung fehlgeschlagen: ' . $e->getMessage();
        }

        return $this->asJson([
            'success' => $success,
            'output' => $output,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
