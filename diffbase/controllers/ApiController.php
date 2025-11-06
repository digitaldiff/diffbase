<?php
namespace modules\diffbase\controllers;

use Craft;
use craft\web\Controller;

class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionInfo(): \yii\web\Response
    {
        if (!$this->validateApiKey()) {
            return $this->asErrorJson('Unauthorized: Invalid or missing API key');
        }

        return $this->asJson([
            'status' => 'ok',
            'timestamp' => date('c'),
            'craft' => $this->getCraftInfo(),
            'php' => $this->getPhpInfo(),
            'server' => $this->getServerInfo(),
            'database' => $this->getDatabaseInfo(),
            'plugins' => $this->getPluginsInfo(),
            'modules' => $this->getModulesInfo(),
            'config' => $this->getConfigInfo(),
            'sites' => $this->getSitesInfo(),
            'queue' => $this->getQueueInfo(),
            'mail' => $this->getMailInfo(),
            'formie' => $this->getFormieInfo()
        ]);
    }

    private function validateApiKey(): bool
    {
        $providedKey = Craft::$app->getRequest()->getParam('key');
        $storedKey = Craft::$app->getProjectConfig()->get('diffbase.apiKey');
        return $storedKey && $providedKey === $storedKey;
    }

    private function getCraftInfo(): array
    {
        $config = Craft::$app->getConfig()->getGeneral();
        return [
            'version' => Craft::$app->getVersion(),
            'edition' => Craft::$app->getEdition(),
            'licensedEdition' => Craft::$app->getLicensedEdition(),
            'environment' => $config->devMode ? 'dev' : 'production',
            'timezone' => Craft::$app->getTimeZone(),
            'locale' => Craft::$app->getLocale()->id
        ];
    }

    private function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
            'sapi' => PHP_SAPI,
            'max_upload_size' => ini_get('upload_max_filesize'),
            'proc_open_available' => function_exists('proc_open'),
            'proc_close_available' => function_exists('proc_close')
        ];
    }

    private function getSitesInfo(): array
    {
        $sites = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $sites[] = [
                'id' => $site->id,
                'handle' => $site->handle,
                'name' => $site->name,
                'language' => $site->language,
                'primary' => $site->primary,
                'enabled' => $site->enabled,
                'base_url' => $site->baseUrl,
                'has_urls' => $site->hasUrls,
                'group_id' => $site->groupId,
                'sort_order' => $site->sortOrder
            ];
        }

        return $sites;
    }

    private function getServerInfo(): array
    {
        return [
            'ip_address' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'os' => PHP_OS,
            'hostname' => gethostname(),
            'disk_free_space' => $this->formatBytes(disk_free_space('.')),
            'disk_total_space' => $this->formatBytes(disk_total_space('.')),
        ];
    }

    private function getDatabaseInfo(): array
    {
        $db = Craft::$app->getDb();
        return [
            'driver' => $db->getDriverName(),
            'version' => $db->getServerVersion(),
            'charset' => $db->charset,
            'table_prefix' => $db->tablePrefix
        ];
    }

    private function getPluginsInfo(): array
    {
        $plugins = [];

        foreach (Craft::$app->getPlugins()->getAllPlugins() as $plugin) {
            $plugins[] = [
                'handle' => $plugin->handle,
                'name' => $plugin->name,
                'version' => $plugin->getVersion(),
                'enabled' => Craft::$app->getPlugins()->isPluginEnabled($plugin->handle),
                'installed' => $plugin->isInstalled
            ];
        }

        return $plugins;
    }

    private function getModulesInfo(): array
    {
        $modules = [];

        foreach (Craft::$app->getModules() as $moduleId => $module) {
            // Plugins überspringen, da diese bereits separat erfasst werden
            if ($module instanceof \craft\base\PluginInterface) {
                continue;
            }

            $moduleData = [
                'handle' => $moduleId
                ];

            $modules[] = $moduleData;
        }

        return $modules;
    }

    private function getConfigInfo(): array
    {
        $config = Craft::$app->getConfig()->getGeneral();
        return [
            'dev_mode' => $config->devMode,
            'disallow_robots' => $config->disallowRobots,
            'cache_duration' => $config->cacheDuration,
            'max_upload_size' => $config->maxUploadFileSize,
            'csrf_protection' => $config->enableCsrfProtection
        ];
    }

    private function formatBytes($size, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }

    private function getFormieInfo(): ?array
    {
        $formiePlugin = Craft::$app->getPlugins()->getPlugin('formie');

        if (!$formiePlugin || !Craft::$app->getPlugins()->isPluginEnabled('formie')) {
            return null;
        }

        try {
            $settings = $formiePlugin->getSettings();

            return [
                'plugin_name' => $formiePlugin->name,
                'plugin_version' => $formiePlugin->getVersion(),
                'settings' => [
                    'plugin_name' => $settings->pluginName ?? null,
                    'use_queue_for_notifications' => $settings->useQueueForNotifications ?? null,
                    'use_queue_for_integrations' => $settings->useQueueForIntegrations ?? null,
                ],
                'forms_count' => $this->getFormieFormsCount(),
                'submissions_count' => $this->getFormieSubmissionsCount()
            ];
        } catch (\Exception $e) {
            return [
                'plugin_name' => $formiePlugin->name,
                'plugin_version' => $formiePlugin->getVersion(),
                'error' => 'Could not load settings: ' . $e->getMessage()
            ];
        }
    }

    private function getFormieFormsCount(): int
    {
        try {
            return (int) Craft::$app->getDb()
                ->createCommand('SELECT COUNT(*) FROM {{%formie_forms}}')
                ->queryScalar();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getFormieSubmissionsCount(): int
    {
        try {
            return (int) Craft::$app->getDb()
                ->createCommand('SELECT COUNT(*) FROM {{%formie_submissions}}')
                ->queryScalar();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getQueueInfo(): array
    {
        $queue = Craft::$app->getQueue();

        try {
            return [
                'total_jobs' => $this->getQueueJobsCount('total'),
                'waiting_jobs' => $this->getQueueJobsCount('waiting'),
                'reserved_jobs' => $this->getQueueJobsCount('reserved'),
                'done_jobs' => $this->getQueueJobsCount('done'),
                'failed_jobs' => $this->getQueueJobsCount('failed'),
                'queue_class' => get_class($queue),
                'is_running' => method_exists($queue, 'getHasReservedJobs') ? $queue->getHasReservedJobs() : null,
                'recent_failed_jobs' => $this->getRecentFailedJobs()
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Could not load queue info: ' . $e->getMessage(),
                'queue_class' => get_class($queue)
            ];
        }
    }

    private function getQueueJobsCount(string $type): int
    {
        try {
            $query = Craft::$app->getDb()->createCommand();

            switch ($type) {
                case 'total':
                    $query->select('COUNT(*)')->from('{{%queue}}');
                    break;
                case 'waiting':
                    $query->select('COUNT(*)')->from('{{%queue}}')
                        ->where(['fail' => false, 'timeUpdated' => null]);
                    break;
                case 'reserved':
                    $query->select('COUNT(*)')->from('{{%queue}}')
                        ->where(['fail' => false])
                        ->andWhere(['not', ['timeUpdated' => null]])
                        ->andWhere(['timePushed' => null]);
                    break;
                case 'done':
                    $query->select('COUNT(*)')->from('{{%queue}}')
                        ->where(['fail' => false])
                        ->andWhere(['not', ['timePushed' => null]]);
                    break;
                case 'failed':
                    $query->select('COUNT(*)')->from('{{%queue}}')
                        ->where(['fail' => true]);
                    break;
                default:
                    return 0;
            }

            return (int) $query->queryScalar();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getRecentFailedJobs(int $limit = 5): array
    {
        try {
            $jobs = Craft::$app->getDb()->createCommand()
                ->select(['id', 'job', 'description', 'timeFailed', 'error'])
                ->from('{{%queue}}')
                ->where(['fail' => true])
                ->orderBy(['timeFailed' => SORT_DESC])
                ->limit($limit)
                ->queryAll();

            return array_map(function($job) {
                return [
                    'id' => (int) $job['id'],
                    'description' => $job['description'],
                    'time_failed' => $job['timeFailed'],
                    'error' => $job['error'] ? substr($job['error'], 0, 200) . '...' : null
                ];
            }, $jobs);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getMailInfo(): array
    {
        try {
            $mailer = Craft::$app->getMailer();
            $config = Craft::$app->getProjectConfig()->get('email') ?? [];

            return [
                'transport_type' => $config['transportType'] ?? 'sendmail',
                'transport_settings' => $this->getTransportSettings($config),
                'from_email' => $config['fromEmail'] ?? null,
                'from_name' => $config['fromName'] ?? null,
                'template' => $config['template'] ?? null,
                'mailer_class' => get_class($mailer)
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Could not load mail info: ' . $e->getMessage()
            ];
        }
    }

    private function getTransportSettings(array $config): array
    {
        $transportType = $config['transportType'] ?? 'sendmail';
        $settings = $config['transportSettings'] ?? [];

        // Nur sichere Einstellungen zurückgeben, keine Passwörter
        switch ($transportType) {
            case 'smtp':
                return [
                    'host' => $settings['host'] ?? null,
                    'port' => $settings['port'] ?? null,
                    'username' => $settings['username'] ?? null,
                    'encryption_method' => $settings['encryptionMethod'] ?? null,
                    'timeout' => $settings['timeout'] ?? null,
                    'auth_required' => !empty($settings['username'])
                ];
            case 'gmail':
                return [
                    'username' => $settings['username'] ?? null,
                    'timeout' => $settings['timeout'] ?? null
                ];
            case 'sendmail':
                return [
                    'command' => $settings['command'] ?? '/usr/sbin/sendmail -bs'
                ];
            default:
                return [];
        }
    }



}
