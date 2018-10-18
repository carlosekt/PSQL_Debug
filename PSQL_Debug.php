<?php
/**
 * @package    PSQL_Debug
 * @author     Igor Shkunov <carlos@66.ru>
 * @version    0.1
 * @date       29.10.17
 */

namespace CarlosEkt\PSQL_Debug;

use Phalcon\Di;

/**
 * Class PSQL_Debug
 */
class PSQL_Debug
{
    /**
     * @var
     */
    private static $instance;

    /**
     * @var
     */
    private $systemStartTime;
    /**
     * @var
     */
    private $systemEndTime;

    /**
     * @var
     */
    private $queryStartTime;
    /**
     * @var
     */
    private $allQueriesTime;
    /**
     * @var array
     */
    private $queriesData = [];

    /**
     * @var bool
     */
    private $trace = false;

    /**
     *
     */
    private function __clone()
    {
    }

    /**
     * @return PSQL_Debug
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * PSQL_Debug constructor.
     */
    private function __construct()
    {
        // error_reporting(-1);
    }

    /**
     * @param $systemStartTime
     */
    public function init($systemStartTime)
    {
        $this->systemStartTime = $systemStartTime;
    }

    /**
     * @param $systemEndTime
     */
    public function end($systemEndTime)
    {
        $this->systemEndTime = $systemEndTime;

        $this->display();
    }

    /**
     * @param $time
     */
    public function queryStart($time)
    {
        $this->queryStartTime = $time;
    }

    /**
     * @param $sql
     * @param $time
     */
    public function queryEnd($sql, $time)
    {
        $queryTime = 0;
        if (!empty($this->queryStartTime)) {
            $queryTime = $time - $this->queryStartTime;
            $this->allQueriesTime += $queryTime;
        }

        $queryData['sql'] = $sql;
        $queryData['time'] = $queryTime;
        $trace = debug_backtrace();
        $queryData['trace'] = $this->filterTrace($trace);

        $this->queriesData[] = $queryData;
    }

    /**
     *
     */
    private function display()
    {
        $css = ' <style type="text/css">';
        $css .= '.debug_info { color: red; font-size:12px;text-align:left;clear:both;width:99%;}';
        $css .= '.debug_info .gen_info {color:000; background:LightGreen; padding:3px;margin:3px;}';
        $css .= '.debug_info .title {background: lightgreen; color:#000; padding:3px; margin:3px;}';
        $css .= '.debug_info div {background: lightgreen; color:#000; padding:5px; margin:3px;white-space: pre-line;word-wrap: break-word;width: 100%;}';
        $css .= '.debug_info div.error {background: pink;}';
        $css .= '.debug_info div.bad-query {background: lightsteelblue;}';
        $css .= '.debug_info div.separator {background: yellow;height:1px;font-size:10px;}';
        $css .= '.debug_info span.sql_error_info {height:10px;font-size:11px;display:block;color:red;}';
        $css .= '</style>';
        echo $css;

        $js = '<script>';
        $js .= '$(document).ready(function(){
                var body_height = $(".content").outerHeight(true);
                $(".debug_info").css("top", (body_height+300)+"px");
            });';
        $js .= '</script>';
        echo $js;

        echo '<div class="debug_info">';
        echo '<div class="gen_info">Memory: ' . number_format(memory_get_peak_usage() / (1024 * 1024), 3, '.', '') . 'M (limit ' . ini_get('memory_limit') . ')</div>';
        echo '<div class="gen_info">Time: ' . number_format($this->systemEndTime - $this->systemStartTime, 6, '.', '') . 's (limit ' . ini_get('max_execution_time') . 's)</div>';
        echo '<div class="gen_info">Included files: ' . count(get_included_files()) . '</div>';
        echo '<div class="gen_info">PHP version: ' . PHP_VERSION . '</div>';

        if (!empty($this->queriesData)) {
            echo '<div class="separator"></div>';
            echo '<div class="gen_info">Queries count: ' . count($this->queriesData) . '</div>';
            echo '<div class="gen_info">Queries time: ' . number_format($this->allQueriesTime, 6, '.', '') . 'c</div>';

            $i = 1;
            foreach ($this->queriesData as $v) {
                $status = $this->getQueryStatus($v);

                echo '<div' . $status . '>' . $i . ' - ' . number_format($v['time'], 6, '.', '') . ' - ' . $v['sql'];

                if (!empty($this->trace) && !empty($v['trace']) && is_array($v['trace'])) {
                    foreach ($v['trace'] as $trace) {
                        echo '<br>' . $trace['class'] . '::' . $trace['function'] . '()' . ($trace['line'] ? '::' . $trace['line'] : '');
                    }
                }

                if (!empty($v['errno'])) {
                    echo '<span class="sql_error_info">' . $v['errno'] . ' - ' . $v['error'] . '</span>';
                }
                echo '</div>';

                ++$i;
            }
            echo '<div class="separator"></div>';
        }

        echo '<div class="gen_info">' . session_name() . ': ' . session_id() . '</div>';
        echo '</div>';
    }

    /**
     * @param $query
     * @return string
     */
    private function getQueryStatus($query)
    {
        $status = '';
        if (!empty($query['errno']) || $query['time'] >= 0.5) {
            return ' class="error"';
        }
        if ($query['time'] >= 0.01) {
            return ' class="bad-query"';
        }

        return $status;
    }

    /**
     * @param array $trace
     * @return array
     */
    private function filterTrace(array $trace)
    {
        $result = [];
        $exceptions = [
            'Phalcon\Events\Manager',
            'Phalcon\Db\Adapter\Pdo',
            'Phalcon\Db\Adapter',
            'Phalcon\Mvc\Model\Query'
        ];

        foreach ($trace as $value) {
            if (!in_array($value['class'], $exceptions, true) && !empty($value['line'])) {
                $result[] = $value;
            }
        }

        return array_slice($result, 0, 5, true);
    }

    /**
     * @param $trace
     */
    public function setTrace($trace)
    {
        $this->trace = $trace;
    }

    /**
     * @param bool $return_query
     * @return string|null
     */
    public function getLastQuery($return_query = false)
    {
        $db = Di::getDefault()->get('db');
        $sql = $db->getSQLStatement();
        /** @var array $vars */
        $vars = $db->getSQLVariables();

        if (!empty($vars)) {
            krsort($vars, SORT_NATURAL);
            /**
             * @var string $placeholder
             * @var array $value
             */
            foreach ($vars as $placeholder => $value) {
                if (is_array($value)) {
                    krsort($value, SORT_NATURAL);
                    foreach ($value as $key => $val) {
                        $newPlaceholder = strpos($placeholder . $key, ':') !== false ? $placeholder . $key : ':' . $placeholder . $key;
                        $sql = str_replace($newPlaceholder, '"' . $val . '"', $sql);
                    }
                } else {
                    $placeholder = strpos($placeholder, ':') !== false ? $placeholder : ':' . $placeholder;
                    $sql = str_replace($placeholder, '"' . $value . '"', $sql);
                }
            }
        }

        if ($return_query === true) {
            return $sql;
        }

        echo $sql;
        die;
    }
}