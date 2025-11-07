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
use yii\base\Event;
use digitaldiff\diffbase\models\Settings;
use digitaldiff\diffbase\services\ApiService;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
//    public bool $hasCpSettings = true;
//    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'apiService' => ApiService::class,
            ],
        ];
    }

    public function init()
    {
        parent::init();

        Craft::setAlias('@digitaldiff/diffbase', $this->getBasePath());

        // Template-Pfad registrieren
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['_diffbase'] = $this->getBasePath() . '/templates';
            }
        );

        // Site URL-Regeln für API
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['api/info'] = '_diffbase/api/info';
            }
        );

        // CP URL-Regeln
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['_diffbase'] = '_diffbase/cp/index';
                $event->rules['_diffbase/settings'] = '_diffbase/cp/settings';
            }
        );

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => '_diffbase',
                    'label' => 'diff. base plugin',
                    'icon' => '@digitaldiff/diffbase/icon-mask.svg',
                    'weight' => 2, // Position im Hauptmenü
                ];
            }
        );

/*        // CP Navigation
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                foreach ($event->navItems as &$item) {
                    if ($item['url'] === 'settings') {
                        $item['subnav']['_diffbase'] = [
                            'label' => 'DiffBase',
                            'url' => '_diffbase',
                            'selected' => Craft::$app->getRequest()->getSegment(1) === '_diffbase'
                        ];
                        break;
                    }
                }
            }
        );*/
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('_diffbase/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
}
