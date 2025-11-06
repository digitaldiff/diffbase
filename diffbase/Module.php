<?php
namespace modules\diffbase;

use Craft;
use yii\base\Module as BaseModule;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use yii\base\Event;

class Module extends BaseModule
{
    public function init()
    {
        Craft::setAlias('@modules/diffbase', __DIR__);

        parent::init();

        // Template-Pfad registrieren - WICHTIG!
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['diffbase'] = __DIR__ . '/templates';
            }
        );

        // Register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function($event) {
                $event->rules['diffbase/settings'] = 'diffbase/settings/edit';
            }
        );

        // Make settings accessible
        Craft::$app->view->hook('cp.settings.diffbase', function() {
            return Craft::$app->getModules()->getModule('diffbase')->getSettingsHtml();
        });

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['api/info'] = 'diffbase/api/info';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['diffbase'] = 'diffbase/cp/index';
                $event->rules['diffbase/settings'] = 'diffbase/cp/settings';
            }
        );

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                foreach ($event->navItems as &$item) {
                    if ($item['url'] === 'settings') {
                        $item['subnav']['diffbase'] = [
                            'label' => 'diff. base module',
                            'url' => 'diffbase',
                            'selected' => Craft::$app->getRequest()->getSegment(1) === 'diffbase'
                        ];
                        break;
                    }
                }
            }
        );
    }
}
