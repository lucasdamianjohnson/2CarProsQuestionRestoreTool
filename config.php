<?php
function getConfig(){
return  array (
  'questions-view-slug-prefix' => 'http://dev.2carpros.com/questions/',
  'data-base-1' => 'dev_2carpros_production',
  'data-base-2' => 'app_2carpros_production',
  'question-limit' => 100,
  'advanced' => false,
  'advanced-options' => 
  array (
    'raw-data' => true,
    'database-analysis-data' => true,
    'metrics' => true,
    'metric-time-calc-time-zone' => 'America/New_York',
    'find-people' => true,
    'ignore-archive-user' => true,
    'question-data-bar-top' => true,
    'question-data-bar-bottom' => true,
    'php-error-reporting-on' => true,
  ),
  'dev' => false,
  'accetpable-databases' => 
  array (
    0 => 'dev_2carpros_production',
    1 => 'app_2carpros_production',
  ),
);
}