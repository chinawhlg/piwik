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
    protected $properties;
    
    /**
     * TODO
     */
    protected $visualization;
    
    /**
     * TODO
     */
    public static function factory($type, $properties)
    {
        switch ($type) { // TODO: move to private functions
            case 'evolution':
                return new Piwik_JqplotDataGenerator_Evolution($properties);
            case 'pie':
                $visualization = new Piwik_Visualization_Chart_Pie();
                $generator = new Piwik_JqplotDataGenerator($visualization, $properties);
                $generator->properties['graph_limit'] = 6;
                $generator->properties['allow_multi_select_series_picker'] = false;
                return $generator;
            case 'bar':
                $visualization = new Piwik_Visualization_Chart_VerticalBar();
                $generator = new Piwik_JqplotDataGenerator($visualization, $properties);
                $generator->properties['graph_limit'] = 6;
                return $generator;
            default:
                throw new Exception("Unknown JqplotDataGenerator type '$type'.");
        }
    }
    
    /**
     * TODO
     */
    public function __construct($visualization, $properties)
    {
        $this->visualization = $visualization;
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
            
            $this->initChartObjectData($dataTable);
        }
        
        $this->visualization->customizeChartProperties();
        
        return $this->visualization->render();
    }

    protected function initChartObjectData($dataTable)
    {
        $dataTable->applyQueuedFilters();

        // We apply a filter to the DataTable, decoding the label column (useful for keywords for example)
        $dataTable->filter('ColumnCallbackReplace', array('label', 'urldecode'));

        $xLabels = $dataTable->getColumn('label');
        
        $columnNames = $this->properties['columns_to_display'];
        if (($labelColumnIndex = array_search('label', $columnNames)) !== false) {
            unset($columnNames[$labelColumnIndex]);
        }

        $columnNameToTranslation = $columnNameToValue = array();
        foreach ($columnNames as $columnName) {
            $columnNameToTranslation[$columnName] = @$this->properties['translations'][$columnName];
            
            $columnNameToValue[$columnName] = $dataTable->getColumn($columnName);
        }
        
        $visualization = $this->visualization;
        $visualization->setAxisXLabels($xLabels);
        $visualization->setAxisYValues($columnNameToValue);
        $visualization->setAxisYLabels($columnNameToTranslation);
        $visualization->setAxisYUnit($this->properties['y_axis_unit']);
        $visualization->setDisplayPercentageInTooltip($this->properties['display_percentage_in_tooltip']);

        // show_all_ticks is not real query param, it is set by GenerateGraphHTML.
        if ($this->properties['show_all_ticks']) {
            $visualization->showAllTicks();
        }

        $units = $this->getUnitsForColumnsToDisplay();
        $visualization->setAxisYUnits($units);

        $this->addSeriesPickerToView();
    }

    protected function getUnitsForColumnsToDisplay()
    {
        // derive units from column names
        $units = $this->deriveUnitsFromRequestedColumnNames();
        if (!empty($this->properties['y_axis_unit'])) {
            // force unit to the value set via $this->setAxisYUnit()
            foreach ($units as &$unit) {
                $unit = $this->properties['y_axis_unit'];
            }
        }
        
        // the bar charts contain the labels a first series
        // this series has to be removed from the units
        if ($this->visualization instanceof Piwik_Visualization_Chart_VerticalBar) {
            array_shift($units);
        }
        
        return $units;
    }

    private function deriveUnitsFromRequestedColumnNames()
    {
        $idSite = Piwik_Common::getRequestVar('idSite', null, 'int');
        
        $units = array();
        foreach ($this->properties['columns_to_display'] as $columnName) {
            $derivedUnit = Piwik_Metrics::getUnit($columnName, $idSite);
            $units[$columnName] = empty($derivedUnit) ? false : $derivedUnit;
        }
        return $units;
    }

    /**
     * Used in initChartObjectData to add the series picker config to the view object
     * @param bool $multiSelect
     */
    protected function addSeriesPickerToView()
    {
        if (count($this->properties['selectable_columns'])
            && Piwik_Common::getRequestVar('showSeriesPicker', 1) == 1
        ) {
            $selectableColumns = array();
            foreach ($this->properties['selectable_columns'] as $column) {
                $selectableColumns[] = array(
                    'column'      => $column,
                    'translation' => @$this->properties['translations'][$column],
                    'displayed'   => in_array($column, $this->properties['columns_to_display'])
                );
            }
            
            $this->visualization->setSelectableColumns(
                $selectableColumns, $this->properties['allow_multi_select_series_picker']);
        }
    }
}
