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
    public function __construct()
    {
        parent::__construct();
        $this->viewProperties['graph_limit'] = null;
        
        $labelIdx = array_search('label', $this->viewProperties['columns_to_display']);
        unset($this->viewProperties[$labelIdx]);
    }
    
    public function init($currentControllerName,
                           $currentControllerAction,
                           $apiMethodToRequestDataTable,
                           $controllerActionCalledWhenRequestSubTable = null,
                           $defaultProperties = array())
    {
        parent::init($currentControllerName,
                     $currentControllerAction,
                     $apiMethodToRequestDataTable,
                     $controllerActionCalledWhenRequestSubTable,
                     $defaultProperties);
        
        $columns = Piwik_Common::getRequestVar('columns', false);
        if ($columns !== false) {
            $columns = Piwik::getArrayFromApiParameter($columns);
        } else {
            $columns = $this->viewProperties['columns_to_display'];
        }
        
        // do not sort if sorted column was initially "label" or eg. it would make "Visits by Server time" not pretty
        if ($this->getSortedColumn() != 'label') {
            $firstColumn = reset($columns);
            if ($firstColumn == 'label') {
                $firstColumn = next($columns);
            }
            
            $this->setSortedColumn($firstColumn);
        }
        
        // selectable columns
        $selectableColumns = array('nb_visits', 'nb_actions');
        if (Piwik_Common::getRequestVar('period', false) == 'day') {
            $selectableColumns[] = 'nb_uniq_visitors';
        }
        
        $this->setSelectableColumns($selectableColumns);
    }

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
