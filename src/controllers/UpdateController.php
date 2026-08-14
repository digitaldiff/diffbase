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

        $composerBinary = $settings->composerPath && is_executable($settings->composerPath)
            ? $settings->composerPath
            : (new ExecutableFinder())->find('composer', null, $this->composerSearchDirs());

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

    /**
     * Common locations for the composer binary on Managed-/Shared-Hosting.
     *
     * `getenv('HOME')` is unreliable under PHP-FPM (often empty even though the CLI/SSH
     * session for the same user has it set), so the home directory is resolved via
     * `posix_getpwuid()` instead, which reads it directly from the OS user database.
     */
    private function composerSearchDirs(): array
    {
        $home = null;
        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $home = posix_getpwuid(posix_getuid())['dir'] ?? null;
        }
        $home ??= getenv('HOME') ?: null;

        $dirs = [
            '/usr/local/bin',
            '/usr/bin',
            '/opt/homebrew/bin',
            '/opt/cpanel/composer/bin', // cPanel Composer Manager
        ];

        if ($home) {
            $dirs[] = $home . '/bin'; // cyon und viele weitere Hoster
            $dirs[] = $home . '/.composer/vendor/bin';
            $dirs[] = $home . '/.local/bin';
        }

        return $dirs;
    }
}
