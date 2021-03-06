<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_CoreAdminHome
 */

/**
 *
 * @package Piwik_CoreAdminHome
 */
class Piwik_CoreAdminHome extends Piwik_Plugin
{
    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getCssFiles'        => 'getCssFiles',
            'AssetManager.getJsFiles'         => 'getJsFiles',
            'AdminMenu.add'                   => 'addMenu',
            'TaskScheduler.getScheduledTasks' => 'getScheduledTasks',
        );
    }

    public function getScheduledTasks(&$tasks)
    {
        // general data purge on older archive tables, executed daily
        $purgeArchiveTablesTask = new Piwik_ScheduledTask ($this,
            'purgeOutdatedArchives',
            null,
            new Piwik_ScheduledTime_Daily(),
            Piwik_ScheduledTask::HIGH_PRIORITY);
        $tasks[] = $purgeArchiveTablesTask;

        // lowest priority since tables should be optimized after they are modified
        $optimizeArchiveTableTask = new Piwik_ScheduledTask ($this,
            'optimizeArchiveTable',
            null,
            new Piwik_ScheduledTime_Daily(),
            Piwik_ScheduledTask::LOWEST_PRIORITY);
        $tasks[] = $optimizeArchiveTableTask;
    }

    public function getCssFiles(&$cssFiles)
    {
        $cssFiles[] = "libs/jquery/themes/base/jquery-ui.css";
        $cssFiles[] = "plugins/CoreAdminHome/stylesheets/menu.less";
        $cssFiles[] = "plugins/Zeitgeist/stylesheets/base.less";
        $cssFiles[] = "plugins/CoreAdminHome/stylesheets/generalSettings.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "libs/jquery/jquery.js";
        $jsFiles[] = "libs/jquery/jquery-ui.js";
        $jsFiles[] = "libs/jquery/jquery.browser.js";
        $jsFiles[] = "libs/javascript/sprintf.js";
        $jsFiles[] = "plugins/Zeitgeist/javascripts/piwikHelper.js";
        $jsFiles[] = "plugins/Zeitgeist/javascripts/ajaxHelper.js";
        $jsFiles[] = "libs/jquery/jquery.history.js";
        $jsFiles[] = "plugins/CoreHome/javascripts/broadcast.js";
        $jsFiles[] = "plugins/CoreAdminHome/javascripts/generalSettings.js";
        $jsFiles[] = "plugins/CoreHome/javascripts/donate.js";
    }

    function addMenu()
    {
        Piwik_AddAdminSubMenu('CoreAdminHome_MenuManage', NULL, "", Piwik::isUserHasSomeAdminAccess(), $order = 1);
        Piwik_AddAdminSubMenu('CoreAdminHome_MenuCommunity', NULL, "", Piwik::isUserHasSomeAdminAccess(), $order = 3);
        Piwik_AddAdminSubMenu('CoreAdminHome_MenuDiagnostic', NULL, "", Piwik::isUserHasSomeAdminAccess(), $order = 20);
        Piwik_AddAdminSubMenu('General_Settings', NULL, "", Piwik::isUserHasSomeAdminAccess(), $order = 5);
        Piwik_AddAdminSubMenu('General_Settings', 'CoreAdminHome_MenuGeneralSettings',
            array('module' => 'CoreAdminHome', 'action' => 'generalSettings'),
            Piwik::isUserHasSomeAdminAccess(),
            $order = 6);
        Piwik_AddAdminSubMenu('CoreAdminHome_MenuManage', 'CoreAdminHome_TrackingCode',
            array('module' => 'CoreAdminHome', 'action' => 'trackingCodeGenerator'),
            Piwik::isUserHasSomeAdminAccess(),
            $order = 4);

    }

    function purgeOutdatedArchives()
    {
        $archiveTables = Piwik_DataAccess_ArchiveTableCreator::getTablesArchivesInstalled();
        foreach ($archiveTables as $table) {
            $date = Piwik_DataAccess_ArchiveTableCreator::getDateFromTableName($table);
            list($month, $year) = explode('_', $date);
            Piwik_DataAccess_ArchiveSelector::purgeOutdatedArchives(Piwik_Date::factory("$year-$month-15"));
        }
    }

    function optimizeArchiveTable()
    {
        $archiveTables = Piwik_DataAccess_ArchiveTableCreator::getTablesArchivesInstalled();
        Piwik_OptimizeTables($archiveTables);
    }
}
