<?php

namespace digitaldiff\diffbase;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use digitaldiff\diffbase\models\Settings;
use digitaldiff\diffbase\services\ApiService;
use yii\base\Exception;

/**
 * Main plugin class for the "diff. base plugin".
 *
 * This class handles the initialization of the plugin, including:
 * - Registering template paths
 * - Defining custom URL rules for the site and control panel
 * - Adding a custom navigation item to the control panel
 * - Providing a settings model and rendering the settings page
 */
class Plugin extends BasePlugin
{
    /**
     * @var string The schema version of the plugin.
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Indicates whether the plugin has a control panel settings page.
     */
    public bool $hasCpSettings = true;

    /**
     * Configures the plugin's components.
     *
     * @return array The configuration array for the plugin's components.
     */
    public static function config(): array
    {
        return [
            'components' => [
                'apiService' => ApiService::class, // Registers the ApiService for business logic
            ],
        ];
    }

    /**
     * Initializes the plugin.
     *
     * This method is called automatically when the plugin is loaded. It sets up
     * aliases, registers template paths, defines URL rules, and adds a control panel
     * navigation item.
     */
    public function init(): void
    {
        parent::init();

        // Set an alias for the plugin's base path
        Craft::setAlias('@digitaldiff/diffbase', $this->getBasePath());

        // Register the template path for the plugin
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['diffbase'] = __DIR__ . 'src/templates'; // Registers the template directory
            }
        );

        // Register site URL rules for the plugin's API
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['api/info'] = 'diffbase/api/info'; // Maps 'api/info' to the ApiController
            }
        );

        // Register control panel URL rules for the plugin
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['diffbase'] = 'diffbase/cp/index'; // Maps 'diffbase' to CpController::actionIndex
                $event->rules['diffbase/test'] = 'diffbase/cp/test'; // Maps 'diffbase/test' to CpController::actionTest
                $event->rules['diffbase/<action:\w+>'] = 'diffbase/cp/<action>'; // Maps dynamic actions
            }
        );

        // Add a custom navigation item to the control panel
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'label' => 'diff. base plugin', // Label for the navigation item
                    'url' => 'diffbase', // URL for the navigation item
                    'icon' => '@digitaldiff/diffbase/icon-mask.svg', // Icon for the navigation item
                    'weight' => 3, // Position in the navigation menu
                ];
            }
        );
    }

    /**
     * Creates the settings model for the plugin.
     *
     * @return Settings The settings model instance.
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * Renders the settings page for the plugin.
     *
     * @return string|null The rendered HTML for the settings page.
     *
     * @throws SyntaxError If there is a syntax error in the Twig template.
     * @throws Exception If an error occurs during rendering.
     * @throws RuntimeError If a runtime error occurs in the Twig template.
     * @throws LoaderError If the Twig template cannot be loaded.
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('diffbase/settings', [
            'plugin' => $this, // Passes the plugin instance to the template
            'settings' => $this->getSettings(), // Passes the settings model to the template
        ]);
    }
}
