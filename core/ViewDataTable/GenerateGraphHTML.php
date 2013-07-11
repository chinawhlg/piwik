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
 * This class generates the HTML code to embed graphs in the page.
 * It doesn't call the API but simply prints the html snippet.
 *
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */
abstract class Piwik_ViewDataTable_GenerateGraphHTML extends Piwik_ViewDataTable
{
    protected $width = '100%';
    protected $height = 250;
    protected $graphType = 'unknown';

    /**
     * Parameters to send to GenerateGraphData instance. Parameters are passed
     * via the $_GET array.
     *
     * @var array
     */
    protected $generateGraphDataParams = array();

    public function setAxisYUnit($unit)
    {
        $this->viewProperties['y_axis_unit'] = $unit;
    }

    /**
     * Sets the number max of elements to display (number of pie slice, vertical bars, etc.)
     * If the data has more elements than $limit then the last part of the data will be the sum of all the remaining data.
     *
     * @param int $limit
     */
    public function setGraphLimit($limit)
    {
        $this->viewProperties['graph_limit'] = $limit;
    }

    /**
     * Returns numbers of elemnts to display in the graph
     *
     * @return int
     */
    public function getGraphLimit()
    {
        return $this->viewProperties['graph_limit'];
    }

    /**
     * The percentage in tooltips is computed based on the sum of all values for the plotted column.
     * If the sum of the column in the data set is not the number of elements in the data set,
     * for example when plotting visits that have a given plugin enabled:
     * one visit can have several plugins, hence the sum is much greater than the number of visits.
     * In this case displaying the percentage doesn't make sense.
     */
    public function disallowPercentageInGraphTooltip()
    {
        $this->viewProperties['display_percentage_in_tooltip'] = false;
    }

    /**
     * Sets the columns that can be added/removed by the user
     * This is done on data level (not html level) because the columns might change after reloading via sparklines
     * @param array $columnsNames Array of column names eg. array('nb_visits','nb_hits')
     */
    public function setSelectableColumns($columnsNames)
    {
        // the array contains values if enableShowGoals() has been used
        // add $columnsNames to the beginning of the array
        $this->viewProperties['selectable_columns'] = array_merge($columnsNames, $this->viewProperties['selectable_columns']);
    }

    /**
     * The implementation of this method in Piwik_ViewDataTable passes to the graph whether the
     * goals icon should be displayed or not. Here, we use it to implicitly add the goal metrics
     * to the metrics picker.
     */
    public function enableShowGoals()
    {
        parent::enableShowGoals();

        $goalMetrics = array('nb_conversions', 'revenue');
        $this->viewProperties['selectable_columns'] = array_merge($this->viewProperties['selectable_columns'], $goalMetrics);

        $this->setColumnTranslation('nb_conversions', Piwik_Translate('Goals_ColumnConversions'));
        $this->setColumnTranslation('revenue', Piwik_Translate('General_TotalRevenue'));
    }

    /**
     * @see Piwik_ViewDataTable::init()
     * @param string $currentControllerName
     * @param string $currentControllerAction
     * @param string $apiMethodToRequestDataTable
     * @param null $controllerActionCalledWhenRequestSubTable
     */
    public function init($currentControllerName,
                  $currentControllerAction,
                  $apiMethodToRequestDataTable,
                  $controllerActionCalledWhenRequestSubTable = null)
    {
        parent::init($currentControllerName,
            $currentControllerAction,
            $apiMethodToRequestDataTable,
            $controllerActionCalledWhenRequestSubTable);

        $this->dataTableTemplate = '@CoreHome/_dataTableGraph';

        $this->disableOffsetInformationAndPaginationControls();
        $this->disableExcludeLowPopulation();
        $this->disableSearchBox();
        $this->enableShowExportAsImageIcon();

        $this->parametersToModify = array(
            'viewDataTable' => $this->getViewDataTableIdToLoad(),
            // in the case this controller is being executed by another controller
            // eg. when being widgetized in an IFRAME
            // we need to put in the URL of the graph data the real module and action
            'module'        => $currentControllerName,
            'action'        => $currentControllerAction,
        );
        
        $this->viewProperties['selectable_columns'] = array();
    }

    public function enableShowExportAsImageIcon()
    {
        $this->viewProperties['show_export_as_image_icon'] = true;
    }

    public function addRowEvolutionSeriesToggle($initiallyShowAllMetrics)
    {
        $this->viewProperties['externalSeriesToggle'] = 'RowEvolutionSeriesToggle';
        $this->viewProperties['externalSeriesToggleShowAll'] = $initiallyShowAllMetrics;
    }

    /**
     * Sets parameters to modify in the future generated URL
     * @param array $array array('nameParameter' => $newValue, ...)
     */
    public function setParametersToModify($array)
    {
        $this->parametersToModify = array_merge($this->parametersToModify, $array);
    }

    /**
     * Show every x-axis tick instead of just every other one.
     */
    public function showAllTicks()
    {
        $this->generateGraphDataParams['show_all_ticks'] = 1;
    }

    /**
     * Adds a row to the report containing totals for contained metrics. Mainly useful
     * for evolution graphs where displaying the totals w/ the metrics is useful.
     */
    public function addTotalRow()
    {
        $this->generateGraphDataParams['add_total_row'] = 1;
    }

    /**
     * We persist the parametersToModify values in the javascript footer.
     * This is used by the "export links" that use the "date" attribute
     * from the json properties array in the datatable footer.
     * @return array
     */
    protected function getJavascriptVariablesToSet()
    {
        $original = parent::getJavascriptVariablesToSet();
        $originalViewDataTable = $original['viewDataTable'];

        $result = $this->parametersToModify + $original;
        $result['viewDataTable'] = $originalViewDataTable;

        return $result;
    }

    /**
     * @see Piwik_ViewDataTable::main()
     * @return null
     */
    public function main()
    {
        if ($this->mainAlreadyExecuted) {
            return;
        }
        $this->mainAlreadyExecuted = true;

        // Graphs require the full dataset, so no filters
        $this->disableGenericFilters();
        
        // the queued filters will be manually applied later. This is to ensure that filtering using search
        // will be done on the table before the labels are enhanced (see ReplaceColumnNames)
        $this->disableQueuedFilters();

        // throws exception if no view access
        $this->loadDataTableFromAPI();
        $this->checkStandardDataTable();
        $this->postDataTableLoadedFromAPI();
        
        $this->view = $this->buildView();
    }

    protected function buildView()
    {
        // access control
        $idSite = Piwik_Common::getRequestVar('idSite', 1, 'int');
        Piwik_API_Request::reloadAuthUsingTokenAuth();
        if (!Piwik::isUserHasViewAccess($idSite)) {
            throw new Exception(Piwik_TranslateException('General_ExceptionPrivilegeAccessWebsite', array("'view'", $idSite)));
        }

        // collect data
        $this->parametersToModify['action'] = $this->currentControllerAction;
        $this->parametersToModify = array_merge($this->variablesDefault, $this->parametersToModify);
        $this->graphData = $this->getGraphData($this->dataTable);

        // build view
        $view = new Piwik_View($this->dataTableTemplate);

        $view->width = $this->width;
        $view->height = $this->height;
        $view->graphType = $this->graphType;

        $view->data = $this->graphData;
        $view->isDataAvailable = strpos($this->graphData, '"series":[]') === false;

        $view->javascriptVariablesToSet = $this->getJavascriptVariablesToSet();
        $view->properties = $this->getViewProperties();

        $view->reportDocumentation = $this->getReportDocumentation();

        // if it's likely that the report data for this data table has been purged,
        // set whether we should display a message to that effect.
        $view->showReportDataWasPurgedMessage = $this->hasReportBeenPurged();
        $view->deleteReportsOlderThan = Piwik_GetOption('delete_reports_older_than');

        return $view;
    }

    protected function getGraphData($dataTable)
    {
        $dataGenerator = JqplotDataGenerator::factory($this->graphType); // TODO
        $dataGenerator->setProperties(array_merge($this->viewProperties, $this->parametersToModify, $this->generateGraphDataparams));
        
        $jsonData = $dataGenerator->generate($dataTable);
        return str_replace(array("\r", "\n"), '', $jsonData);
    }
}
