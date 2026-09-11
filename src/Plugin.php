<?php

namespace digitaldiff\diffbase;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\base\WidgetInterface;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Dashboard;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;
use craft\widgets\MissingWidget;
use digitaldiff\diffbase\widgets\NewsWidget;
use digitaldiff\diffbase\widgets\SupportWidget;
use digitaldiff\diffbase\widgets\TechWidget;
use Throwable;
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
     * @var string[] The dashboard widget types provided by the plugin.
     */
    public const WIDGET_TYPES = [
        ContactWidget::class,
        MessageWidget::class,
        NewsWidget::class,
        SupportWidget::class,
        TechWidget::class,
    ];

    /**
     * @var string User preference holding the widgets a user added themselves, while the
     * plugin's widgets replace them on the dashboard.
     */
    private const WIDGET_BACKUP_PREFERENCE = 'diffbaseWidgets';

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
     * @throws Throwable
     */
    public function init(): void
    {
        parent::init();

        // Set an alias for the plugin's base path
        Craft::setAlias('@digitaldiff/diffbase', $this->getBasePath());

        // Register the template path for the plugin (site and CP)
        $templateRoot = function(RegisterTemplateRootsEvent $event) {
            $event->roots['diffbase'] = __DIR__ . '/templates';
        };
        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, $templateRoot);
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, $templateRoot);

        // Register site URL rules for the plugin's API
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['api/info'] = 'diffbase/api/info'; // API Endpoint for general info
                // $event->rules['diffbase/support/send-email'] = 'diffbase/support/send-email'; // Support E-Mail
                $event->rules['actions/diffbase/update/composer-update'] = 'diffbase/update/composer-update'; // Composer Update Action
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
                $event->types = array_merge($event->types, self::WIDGET_TYPES);
            }
        );

        // Add the widget to the dashboard, unless disabled in the plugin view
        if (Craft::$app->getRequest()->isCpRequest && !$this->getSettings()->disableWidgets) {
            $this->_addWidgetsToDashboard();
            $view = Craft::$app->getView();

            $view->registerCss(<<<CSS
            #newwidgetmenubtn,
            #widgetManagerBtn {
                display: none;
            }
            CSS);
        } elseif (Craft::$app->getRequest()->isCpRequest) {
            // Widgets disabled: clear the ones added before and restore the user's own widgets,
            // for each user on their next CP request
            $this->_removeWidgetsFromDashboard();
        }

        // Feedback button (Marker.io) for non-admins, independent of the widgets, unless disabled in the plugin view
        $user = Craft::$app->getUser();
        if (Craft::$app->getRequest()->isCpRequest && !$this->getSettings()->disableFeedback && $user->getIdentity() && !$user->getIsAdmin()) {
            $this->_registerMarkerIo();
        }
    }

    /**
     * Registers the feedback button on every control panel page. Marker.io (bug reporting tool)
     * is only loaded once the user clicks the button.
     *
     * Hooked into full page renders only, so AJAX responses (slideouts, modals) don't add the
     * button a second time.
     */
    private function _registerMarkerIo(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function () {
                $view = Craft::$app->getView();
                $user = Craft::$app->getUser()->getIdentity();

                // Prefills the reporter in the Marker.io form with the current Craft user. Without a
                // full name the system name is used, as Marker.io offers no way to enter it.
                $reporter = Json::encode([
                    'email' => $user->email,
                    'fullName' => $user->fullName ?? Craft::$app->getSystemName(),
                ]);

                // Feedback tab floating on the right edge, vertically centered
                $view->registerHtml(Html::button(Craft::t('diffbase', 'Feedback geben'), [
                    'class' => 'btn submit diffbase-feedback js-marker-feedback',
                ]));
                $view->registerCss(<<<CSS
                    .btn.diffbase-feedback {
                        position: fixed;
                        top: 50%;
                        right: 0;
                        z-index: 99;
                        transform: translateY(-50%) rotate(180deg);
                        writing-mode: vertical-rl;
                        height: auto;
                        padding: 14px 8px;
                        border-radius: 0 var(--large-border-radius) var(--large-border-radius) 0;
                    }
                CSS);

                // Load the Marker.io script on click, then hand over to Marker.io's own button
                $view->registerJs(<<<JS
                    $(document).on('click', '.js-marker-feedback', function (e) {
                        e.preventDefault();

                        // Still loading from an earlier click
                        if (window.Marker) {
                            return;
                        }

                        window.markerConfig = {
                            project: '69e9e09e6db37199a4367b01',
                            source: 'snippet'
                        };
                        !function(e,r,a){if(!e.__Marker){e.__Marker={};var t=[],n={__cs:t};["show","hide","isVisible","capture","cancelCapture","unload","reload","isExtensionInstalled","setReporter","clearReporter","setCustomData","on","off"].forEach(function(e){n[e]=function(){var r=Array.prototype.slice.call(arguments);r.unshift(e),t.push(r)}}),e.Marker=n;var s=r.createElement("script");s.async=1,s.src="https://edge.marker.io/latest/shim.js";var i=r.getElementsByTagName("script")[0];i.parentNode.insertBefore(s,i)}}(window,document);

                        // Marker.io replays queued calls before it has loaded the project, so queued
                        // setReporter()/capture() calls run too early. Wait for its "load" event instead.
                        Marker.on('load', function () {
                            Marker.setReporter({$reporter});

                            // Marker.io's floating button replaces ours from here on
                            Marker.show();
                            $('.js-marker-feedback').remove();

                            Marker.capture();
                        });
                    });
                JS);
            }
        );

        Craft::info('Marker.io Bug-Reporting-Tool wurde im Control Panel registriert', __METHOD__);
    }

    /**
     * @throws Throwable
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

        // On a user's first dashboard visit, getAllWidgets() adds Craft's default widgets —
        // those weren't added by the user and don't need to be remembered
        $isFirstDashboard = !$user->hasDashboard;

        // Remove all existing widgets, remembering the ones the user added themselves so they
        // can be restored when the plugin's widgets get disabled
        $userWidgets = [];
        foreach ($dashboardService->getAllWidgets() as $widget) {
            if (!$isFirstDashboard && !$this->_isPluginWidget($widget)) {
                $userWidgets[] = [
                    'type' => get_class($widget),
                    'settings' => $widget->getSettings(),
                    'colspan' => $widget->colspan,
                ];
            }
            $dashboardService->deleteWidgetById($widget->id);
        }

        if ($userWidgets) {
            $backup = $user->getPreference(self::WIDGET_BACKUP_PREFERENCE) ?? [];
            Craft::$app->getUsers()->saveUserPreferences($user, [
                self::WIDGET_BACKUP_PREFERENCE => array_merge($backup, $userWidgets),
            ]);
        }

        // Add the widgets, marked with `addedByPlugin` so they can be told apart from
        // widgets a user adds (see AddedByPluginTrait)

        // Add the Messages Widget
        $messageWidget = new MessageWidget(['addedByPlugin' => true]);
        $dashboardService->saveWidget($messageWidget);
        $dashboardService->changeWidgetColspan($messageWidget->id, 2);

        // Add the Contact Widget
        $contactWidget = new ContactWidget(['addedByPlugin' => true]);
        $dashboardService->saveWidget($contactWidget);

        // Add the News Widget
/*        $newsWidget = new NewsWidget(['addedByPlugin' => true]);
        $dashboardService->saveWidget($newsWidget);*/

/*        // Add the Support Widget
        $supportWidget = new SupportWidget(['addedByPlugin' => true]);
        $dashboardService->saveWidget($supportWidget);
        $dashboardService->changeWidgetColspan($supportWidget->id, 1);*/


        for ($i = 0; $i < 8; $i++) {
            $techWidget = new TechWidget(['addedByPlugin' => true]);
            $techWidget->offset = $i;
            $dashboardService->saveWidget($techWidget);
            $dashboardService->changeWidgetColspan($techWidget->id, 1);
        }

    }

    /**
     * Removes the plugin's widgets from the current user's dashboard and restores the
     * widgets the user had added themselves before `_addWidgetsToDashboard()` replaced them.
     *
     * @throws Throwable
     */
    private function _removeWidgetsFromDashboard(): void
    {
        // The dashboard service needs a logged-in user (not the case on the CP login page)
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            return;
        }

        $dashboardService = Craft::$app->getDashboard();

        foreach ($dashboardService->getAllWidgets() as $widget) {
            if ($this->_isPluginWidget($widget)) {
                $dashboardService->deleteWidgetById($widget->id);
            }
        }

        $backup = $user->getPreference(self::WIDGET_BACKUP_PREFERENCE);
        if (!$backup) {
            return;
        }

        foreach ($backup as $config) {
            $widget = $dashboardService->createWidget([
                'type' => $config['type'],
                'settings' => $config['settings'],
            ]);

            // Skip widgets whose type is no longer installed, or that don't validate anymore
            if ($widget instanceof MissingWidget || !$dashboardService->saveWidget($widget)) {
                continue;
            }

            if ($config['colspan']) {
                $dashboardService->changeWidgetColspan($widget->id, $config['colspan']);
            }
        }

        Craft::$app->getUsers()->saveUserPreferences($user, [self::WIDGET_BACKUP_PREFERENCE => null]);
    }

    /**
     * Returns whether a widget was added by the plugin (`addedByPlugin` true) or saved by a
     * plugin version before that flag existed (null). Widgets a user added themselves (false) —
     * including diff. widgets — and widgets of other types are not.
     */
    private function _isPluginWidget(WidgetInterface $widget): bool
    {
        return in_array(get_class($widget), self::WIDGET_TYPES, true) && $widget->addedByPlugin !== false;
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
