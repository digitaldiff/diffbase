<?php
namespace digitaldiff\diffbase\controllers;

use Craft;
use craft\web\Controller;
use digitaldiff\diffbase\Plugin;

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
            'users' => $this->getUsersInfo(),
            'queue' => $this->getQueueInfo(),
            'mail' => $this->getMailInfo(),
            'formie' => $this->getFormieInfo(),
            'updates' => $this->getUpdatesInfo()
        ]);
    }

    private function validateApiKey(): bool
    {
        $providedKey = Craft::$app->getRequest()->getParam('key');
        $settings = Plugin::getInstance()->getSettings();
        return $settings->apiKey && $providedKey === $settings->apiKey;
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
            $reachability = $site->hasUrls ? $this->checkSiteReachability($site->baseUrl) : ['reachable' => null, 'status_code' => null];

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
                'sort_order' => $site->sortOrder,
                'reachable' => $reachability['reachable'],
                'http_status' => $reachability['status_code'],
            ];
        }

        return $sites;
    }

    private function checkSiteReachability(string $url): array
    {
        try {
            $client = Craft::createGuzzleClient([
                'timeout' => 5,
                'connect_timeout' => 3,
                'allow_redirects' => true,
                'http_errors' => false,
            ]);
            $response = $client->head($url);
            $statusCode = $response->getStatusCode();

            return [
                'reachable' => $statusCode >= 200 && $statusCode < 400,
                'status_code' => $statusCode,
            ];
        } catch (\Throwable) {
            return [
                'reachable' => false,
                'status_code' => null,
            ];
        }
    }

    private function getUsersInfo(): array
    {
        try {
            $query = \craft\elements\User::find()->status(null);

            $lastAdminLogin = \craft\elements\User::find()
                ->status(null)
                ->admin()
                ->orderBy(['lastLoginDate' => SORT_DESC])
                ->one();

            return [
                'total' => (clone $query)->count(),
                'admins' => (clone $query)->admin()->count(),
                'pending' => (clone $query)->status(\craft\elements\User::STATUS_PENDING)->count(),
                'suspended' => (clone $query)->status(\craft\elements\User::STATUS_SUSPENDED)->count(),
                'locked' => (clone $query)->status(\craft\elements\User::STATUS_LOCKED)->count(),
                'last_admin_login' => $lastAdminLogin?->lastLoginDate?->format('c'),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Could not load user info: ' . $e->getMessage()
            ];
        }
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
                'installed' => $plugin->isInstalled,
                'update_pending' => Craft::$app->getPlugins()->isPluginUpdatePending($plugin),
                'version_changed' => Craft::$app->getPlugins()->hasPluginVersionNumberChanged($plugin),
                'schema_version' => $plugin->schemaVersion,
                'developer' => $plugin->developer ?? null,
                'developer_url' => $plugin->developerUrl ?? null,
                'description' => $plugin->description ?? null,
                'documentation_url' => $plugin->documentationUrl ?? null,
                'package_name' => $plugin->packageName ?? null,
                'has_issues' => Craft::$app->getPlugins()->hasIssues($plugin->handle)
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
        $db = Craft::$app->getDb();

        try {
            $base = (new \yii\db\Query())->from('{{%queue}}');

            $total = (int) (clone $base)->count('*', $db);
            $waiting = (int) (clone $base)->where(['fail' => false, 'timeUpdated' => null])->count('*', $db);
            $reserved = (int) (clone $base)->where(['fail' => false])->andWhere(['not', ['timeUpdated' => null]])->count('*', $db);
            $failed = (int) (clone $base)->where(['fail' => true])->count('*', $db);

            return [
                'total_jobs' => $total,
                'waiting_jobs' => $waiting,
                'reserved_jobs' => $reserved,
                'failed_jobs' => $failed,
                'queue_class' => get_class($queue),
                'is_running' => $reserved > 0,
                'recent_failed_jobs' => $this->getRecentFailedJobs()
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Could not load queue info: ' . $e->getMessage(),
                'queue_class' => get_class($queue)
            ];
        }
    }

    private function getRecentFailedJobs(int $limit = 5): array
    {
        try {
            $jobs = (new \yii\db\Query())
                ->select(['id', 'description', 'dateFailed', 'error'])
                ->from('{{%queue}}')
                ->where(['fail' => true])
                ->orderBy(['dateFailed' => SORT_DESC])
                ->limit($limit)
                ->all(Craft::$app->getDb());

            return array_map(function ($job) {
                return [
                    'id' => (int) $job['id'],
                    'description' => $job['description'],
                    'date_failed' => $job['dateFailed'],
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
        $transportType = $config['transportType'] ?? \craft\mail\transportadapters\Sendmail::class;
        $settings = $config['transportSettings'] ?? [];

        // Nur sichere Einstellungen zurückgeben, keine Passwörter
        // transportType ist der volle Klassenname des Transport-Adapters (z.B. craft\mail\transportadapters\Smtp)
        return match ($transportType) {
            \craft\mail\transportadapters\Smtp::class => [
                'host' => $settings['host'] ?? null,
                'port' => $settings['port'] ?? null,
                'username' => $settings['username'] ?? null,
                'encryption_method' => $settings['encryptionMethod'] ?? null,
                'timeout' => $settings['timeout'] ?? null,
                'auth_required' => !empty($settings['username'])
            ],
            \craft\mail\transportadapters\Gmail::class => [
                'username' => $settings['username'] ?? null,
                'timeout' => $settings['timeout'] ?? null
            ],
            \craft\mail\transportadapters\Sendmail::class => [
                'command' => $settings['command'] ?? '/usr/sbin/sendmail -bs'
            ],
            default => [],
        };
    }

    private function getUpdatesInfo(): array
    {
        try {
            $rawUpdates = Craft::$app->getUpdates()->getUpdates(true);

            return [
                'craft' => $this->processCraftUpdates($rawUpdates->cms ?? null),
                'plugins' => $this->processPluginUpdates($rawUpdates->plugins ?? []),
                'summary' => $this->getUpdatesSummary($rawUpdates)
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Could not load update info: ' . $e->getMessage()
            ];
        }
    }

    private function processCraftUpdates($cmsUpdate): array
    {
        if (!$cmsUpdate || !($cmsUpdate instanceof \craft\models\Update)) {
            return [
                'update_available' => false,
                'current_version' => Craft::$app->getVersion(),
            ];
        }

        $hasUpdates = !empty($cmsUpdate->releases);
        $latestRelease = $hasUpdates ? $cmsUpdate->releases[0] : null;

        return [
            'update_available' => $hasUpdates,
            'current_version' => Craft::$app->getVersion(),
            'latest_version' => $latestRelease->version ?? null,
            'latest_release_date' => $latestRelease->date ?? null,
            'critical_update' => $latestRelease->critical ?? false,
            'status' => $cmsUpdate->status ?? 'unknown',
            'package_name' => $cmsUpdate->packageName ?? 'craftcms/cms',
            'php_constraint' => $cmsUpdate->phpConstraint ?? null,
            'total_releases' => count($cmsUpdate->releases ?? []),
            //'releases' => array_slice($cmsUpdate->releases ?? [], 0, 3)
        ];
    }

    private function processPluginUpdates(array $pluginsData): array
    {
        $processedPlugins = [];

        foreach ($pluginsData as $handle => $pluginUpdate) {
            if (!($pluginUpdate instanceof \craft\models\Update)) {
                continue;
            }

            $hasUpdates = !empty($pluginUpdate->releases);
            $latestRelease = $hasUpdates ? $pluginUpdate->releases[0] : null;

            $processedPlugins[$handle] = [
                'update_available' => $hasUpdates,
                'latest_version' => $latestRelease->version ?? null,
                'latest_release_date' => $latestRelease->date ?? null,
                'critical_update' => $latestRelease->critical ?? false,
                'status' => $pluginUpdate->status ?? 'unknown',
                'package_name' => $pluginUpdate->packageName ?? null,
                'php_constraint' => $pluginUpdate->phpConstraint ?? null,
//                'total_releases' => count($pluginUpdate->releases ?? []),
                'abandoned' => $pluginUpdate->abandoned ?? false,
                'replacement_name' => $pluginUpdate->replacementName ?? null
            ];
        }

        return $processedPlugins;
    }

    private function getUpdatesSummary($rawUpdates): array
    {
        $craftHasUpdates = false;
        $pluginUpdatesCount = 0;
        $criticalUpdates = 0;

        // Craft Updates prüfen
        if (isset($rawUpdates->cms) && !empty($rawUpdates->cms->releases)) {
            $craftHasUpdates = true;
            if (isset($rawUpdates->cms->releases[0]->critical) && $rawUpdates->cms->releases[0]->critical) {
                $criticalUpdates++;
            }
        }

        // Plugin Updates zählen
        foreach ($rawUpdates->plugins ?? [] as $pluginUpdate) {
            if (!empty($pluginUpdate->releases)) {
                $pluginUpdatesCount++;

                if (isset($pluginUpdate->releases[0]->critical) && $pluginUpdate->releases[0]->critical) {
                    $criticalUpdates++;
                }
            }
        }

        return [
            'craft_update_available' => $craftHasUpdates,
            'plugin_updates_available' => $pluginUpdatesCount,
            'total_updates_available' => ($craftHasUpdates ? 1 : 0) + $pluginUpdatesCount,
            'critical_updates_available' => $criticalUpdates,
            'has_updates' => $craftHasUpdates || $pluginUpdatesCount > 0
        ];
    }




}
