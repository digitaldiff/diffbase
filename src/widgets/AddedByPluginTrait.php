<?php

declare(strict_types=1);

namespace digitaldiff\diffbase\widgets;

/**
 * Marks whether a widget was added by the plugin or by a user.
 *
 * The flag is stored with the widget's settings:
 * - `true`: added by the plugin (`Plugin::_addWidgetsToDashboard()`)
 * - `false`: added by a user
 * - `null`: saved by a plugin version before this flag existed
 */
trait AddedByPluginTrait
{
    public ?bool $addedByPlugin = null;

    public function beforeSave(bool $isNew): bool
    {
        // Anything the plugin didn't mark itself was added by a user
        $this->addedByPlugin ??= false;

        return parent::beforeSave($isNew);
    }
}
