<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * Reads data from the API and prepares data to give to the renderer Piwik_Visualization_Chart.
 * This class is used to generate the data for the graphs.
 * You can set the number of elements to appear in the graph using: setGraphLimit();
 * Example:
 * <pre>
 *    function getWebsites( $fetch = false)
 *    {
 *        $view = Piwik_ViewDataTable::factory();
 *        $view->init( $this->pluginName, 'getWebsites', 'Referers.getWebsites', 'getUrlsFromWebsiteId' );
 *        $view->setColumnsToDisplay( array('label','nb_visits') );
 *        $view->setLimit(10);
 *        $view->setGraphLimit(12);
 *        return $this->renderView($view, $fetch);
 *    }
 * </pre>
 *
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */
abstract class Piwik_ViewDataTable_GenerateGraphData extends Piwik_ViewDataTable
{

    /**
     * Used in initChartObjectData to add the series picker config to the view object
     * @param bool $multiSelect
     */
    protected function addSeriesPickerToView($multiSelect = true)
    {
        if (count($this->selectableColumns)
            && Piwik_Common::getRequestVar('showSeriesPicker', 1) == 1
        ) {
            // build the final configuration for the series picker
            $columnsToDisplay = $this->getColumnsToDisplay();
            $selectableColumns = array();

            foreach ($this->selectableColumns as $column) {
                $selectableColumns[] = array(
                    'column'      => $column,
                    'translation' => $this->getColumnTranslation($column),
                    'displayed'   => in_array($column, $columnsToDisplay)
                );
            }
            $this->view->setSelectableColumns($selectableColumns, $multiSelect);
        }
    }

    protected function getUnitsForColumnsToDisplay()
    {
        // derive units from column names
        $idSite = Piwik_Common::getRequestVar('idSite', null, 'int');
        $units = $this->deriveUnitsFromRequestedColumnNames($this->getColumnsToDisplay(), $idSite);
        if (!empty($this->yAxisUnit)) {
            // force unit to the value set via $this->setAxisYUnit()
            foreach ($units as &$unit) {
                $unit = $this->yAxisUnit;
            }
        }

        return $units;
    }

    protected function deriveUnitsFromRequestedColumnNames($requestedColumnNames, $idSite)
    {
        $units = array();
        foreach ($requestedColumnNames as $columnName) {
            $derivedUnit = Piwik_Metrics::getUnit($columnName, $idSite);
            $units[$columnName] = empty($derivedUnit) ? false : $derivedUnit;
        }
        return $units;
    }

    public function main()
    {
    }

}
