<?php

namespace digitaldiff\diffbase;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Dashboard;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use digitaldiff\diffbase\widgets\NewsWidget;
use digitaldiff\diffbase\widgets\SupportWidget;
use digitaldiff\diffbase\widgets\TechWidget;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use digitaldiff\diffbase\models\Settings;
use digitaldiff\diffbase\services\ApiService;
use yii\base\Exception;

use digitaldiff\diffbase\widgets\ContactWidget;
use digitaldiff\diffbase\widgets\MessageWidget;

/**
 * Main plugin class for the "diff. base plugin".
 *
 * This class handles the initialization of the plugin, including:
 * - Registering template paths
 * - Defining custom URL rules for the site and control panel
 * - Adding a custom navigation item to the control panel (admin-only)
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
     * navigation item (visible only to admins).
     * @throws \Throwable
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
                $event->roots['diffbase'] = __DIR__ . '/templates'; // Registers the template directory
            }
        );

        // Register site URL rules for the plugin's API
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['api/info'] = 'diffbase/api/info'; // Bestehend
                $event->rules['diffbase/support/send-email'] = 'diffbase/support/send-email'; // Support E-Mail
//                $event->rules['actions/diffbase/update/composer-update'] = 'diffbase/update/composer-update'; // Neu
            }
        );

        // Register control panel URL rules for the plugin
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['diffbase'] = 'diffbase/cp/index'; // Maps 'diffbase' to CpController::actionIndex
                $event->rules['diffbase/<action:\w+>'] = 'diffbase/cp/<action>'; // Maps dynamic actions
            }
        );

        // Add a custom navigation item to the control panel (admin-only)
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                // Check if the current user is an admin
                if (Craft::$app->getUser()->getIsAdmin()) {
                    $event->navItems[] = [
                        'label' => 'diff. base plugin', // Label for the navigation item
                        'url' => 'diffbase', // URL for the navigation item
                        'icon' => '@digitaldiff/diffbase/icon-mask.svg', // Icon for the navigation item
                        'order' => 9999, // Position at the bottom of the navigation menu
                    ];
                }
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ContactWidget::class;
                $event->types[] = MessageWidget::class;
                $event->types[] = NewsWidget::class;
                $event->types[] = SupportWidget::class;
                $event->types[] = TechWidget::class;
            }
        );

        // Add the widget to the dashboard
        if (Craft::$app->getRequest()->isCpRequest) {
            $this->_addWidgetsToDashboard();
            $view = Craft::$app->getView();

            if (Craft::$app->getUser()->getIdentity()) {
                // Registriere Marker.io für Bug-Reporting im gesamten Control Panel
                $this->_registerMarkerIo();
            }

            /*$view->registerCss(<<<CSS
            #newwidgetmenubtn,
            #widgetManagerBtn {
                display: none;
            }
            CSS);*/
        }

    }

    /**
     * Registriert Marker.io Bug-Reporting-Tool im Control Panel
     */
    private function _registerMarkerIo(): void
    {
        $view = Craft::$app->getView();

        // Registriere das Marker.io Script
        $view->registerJs(<<<JS
window.markerConfig = {
    project: '69e9e09e6db37199a4367b01', 
    source: 'snippet'
};

!function(e,r,a){if(!e.__Marker){e.__Marker={};var t=[],n={__cs:t};["show","hide","isVisible","capture","cancelCapture","unload","reload","isExtensionInstalled","setReporter","clearReporter","setCustomData","on","off"].forEach(function(e){n[e]=function(){var r=Array.prototype.slice.call(arguments);r.unshift(e),t.push(r)}}),e.Marker=n;var s=r.createElement("script");s.async=1,s.src="https://edge.marker.io/latest/shim.js";var i=r.getElementsByTagName("script")[0];i.parentNode.insertBefore(s,i)}}(window,document);
JS
        , \yii\web\View::POS_HEAD);

        Craft::info('Marker.io Bug-Reporting-Tool wurde im Control Panel registriert', __METHOD__);
    }

    /**
     * @throws \Throwable
     */
    private function _addWidgetsToDashboard(): void
    {

        // Ensure a user is logged in before proceeding
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            Craft::warning('Attempted to add widgets to the dashboard without a logged-in user.', __METHOD__);
            return;
        }

        $dashboardService = Craft::$app->getDashboard();

        // Remove all existing widgets
        $existingWidgets = $dashboardService->getAllWidgets();
        foreach ($existingWidgets as $widget) {
            $dashboardService->deleteWidgetById($widget->id);
        }

        // Add the Messages Widget
        $messageWidget = new MessageWidget();
        $dashboardService->saveWidget($messageWidget);
        $dashboardService->changeWidgetColspan($messageWidget->id, 2);

        // Add the Contact Widget
        $contactWidget = new ContactWidget();
        $dashboardService->saveWidget($contactWidget);

        // Add the News Widget
        $newsWidget = new NewsWidget();
        $dashboardService->saveWidget($newsWidget);

/*        // Add the Support Widget
        $supportWidget = new SupportWidget();
        $dashboardService->saveWidget($supportWidget);
        $dashboardService->changeWidgetColspan($supportWidget->id, 1);*/


        for ($i = 1; $i <= 5; $i++) {
            // Add the Tech Widget
            $techWidget = new TechWidget();
            $dashboardService->saveWidget($techWidget);
            $dashboardService->changeWidgetColspan($techWidget->id, 1);
        }

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
