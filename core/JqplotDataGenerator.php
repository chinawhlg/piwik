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
 * TODO
 */
class Piwik_JqplotDataGenerator
{
    /**
     * TODO
     */
    private $properties;
    
    /**
     * TODO
     */
    public static function factory($type)
    {
        switch ($type) {
            case 'evolution':
                return new Piwik_JqplotDataGenerator_Evolution();
            case 'pie':
                return new Piwik_JqplotDataGenerator_Pie();
            case 'bar':
                return new Piwik_JqplotDataGenerator_VerticalBar();
            default:
                throw new Exception("Unknown JqplotDataGenerator type '$type'.");
        }
    }
    
    /**
     * TODO
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
    }
    
    /**
     * TODO
     */
    public function generate($dataTable)
    {
        if (!empty($this->properties['graph_limit'])) {
            $offsetStartSummary = $this->properties['graph_limit'] - 1;
            $sortColumn = $dataTable->getSortedByColumnName()
                        ? $dataTable->getSortedByColumnName()
                        : Piwik_Metrics::INDEX_NB_VISITS;
            
            $dataTable->filter(
                'AddSummaryRow', array($offsetStartSummary, Piwik_Translate('General_Others'), $sortColumn));
        }

        if ($dataTable->getRowsCount() > 0) {
            // if addTotalRow was called in GenerateGraphHTML, add a row containing totals of
            // different metrics
            if (!empty($this->properties['add_total_row'])) {
                $dataTable->queueFilter('AddSummaryRow', array(0, Piwik_Translate('General_Total'), null, false));
            }
            
            $this->initChartObjectData($dataTable); // TODO
        }
        $this->view->customizeChartProperties(); // TODO
    }

    protected function initChartObjectData($dataTable)
    {
        $dataTable->applyQueuedFilters();

        // We apply a filter to the DataTable, decoding the label column (useful for keywords for example)
        $dataTable->filter('ColumnCallbackReplace', array('label', 'urldecode'));

        $xLabels = $dataTable->getColumn('label');
        $columnNames = parent::getColumnsToDisplay();
        if (($labelColumnFound = array_search('label', $columnNames)) !== false) {
            unset($columnNames[$labelColumnFound]);
        }

        $columnNameToTranslation = $columnNameToValue = array();
        foreach ($columnNames as $columnName) {
            $columnNameToTranslation[$columnName] = $this->getColumnTranslation($columnName);
            $columnNameToValue[$columnName] = $this->dataTable->getColumn($columnName);
        }
        $this->view->setAxisXLabels($xLabels);
        $this->view->setAxisYValues($columnNameToValue);
        $this->view->setAxisYLabels($columnNameToTranslation);
        $this->view->setAxisYUnit($this->yAxisUnit);
        $this->view->setDisplayPercentageInTooltip($this->displayPercentageInTooltip);

        // show_all_ticks is not real query param, it is set by GenerateGraphHTML.
        if (Piwik_Common::getRequestVar('show_all_ticks', 0) == 1) {
            $this->view->showAllTicks();
        }

        $units = $this->getUnitsForColumnsToDisplay();
        $this->view->setAxisYUnits($units);

        $this->addSeriesPickerToView();
    }
}
