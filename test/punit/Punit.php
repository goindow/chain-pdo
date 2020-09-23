<?php
// +--------------------------------------------------------------------------------------
// | Punit [单元测试脚手架 - 自动测试、简易断言库(不依赖)]
// +--------------------------------------------------------------------------------------
// | Time: 2020-07-06
// +--------------------------------------------------------------------------------------
// | Author: hyb <76788424@qq.com>
// +--------------------------------------------------------------------------------------
// | Usage: php $path/punit.php $testCaseDir
// |    eg: php ./punit/Punit.php /usr/local/var/php/chain-pdo/test/
// +--------------------------------------------------------------------------------------
// | Specification:
// | 1.TestXXX.php 被解析为测试用例类
// | 2.TestXXX.php 下的 testXXX 方法被解析为测试用例
// | 3.TestXXX.php 下的 before/after 方法分别在每个 testXXX 测试用例执行前/后执行
// | 4.只要 testXXX 抛出异常，失败用例 +1，否则，成功用例 +1
// +--------------------------------------------------------------------------------------
class Punit {

    // 是否显示测试进度，true - 每执行一个 case 都打结果，false - 只在全部 case 执行完打印
    private $isShowProcessing = true;

    // 测试用例目录，只解析当前目录（目录深度一级、不支持迭代解析）的名为 TestXXX.php
    private $caseFilesPath = '';

    // 测试用例文件信息
    private $caseFiles = [];

    // 测试报告
    private $report = [
        // 结果汇总
        'summary' => [
            'time'   => '',     // 总测试时间
            'total'  => 0,      // 总用例数
            'pass'   => 0,      // 测试通过用例数
            'fail'   => 0       // 测试失败用例数
        ],
        // 结果明细
        "list" => []
    ];

    public function __construct($caseFilesPath) {
        $this->caseFilesPath = rtrim($caseFilesPath, '/') . '/';
        $this->getCaseFiles();
    }

    /**
     * 运行测试
     */
    public function run() {
        $beginTime = microtime(true);
        foreach (array_keys($this->caseFiles) as $caseFileIndex) $this->runCaseFile($caseFileIndex);
        $this->report['summary']['time'] = $this->getTime($beginTime);
        $this->report();
    }

    /**
     * 运行测试文件
     */
    private function runCaseFile($caseFileIndex) {
        require($this->caseFiles[$caseFileIndex]['file']);
        $caseFileRefClass = new ReflectionClass($this->caseFiles[$caseFileIndex]['class']);
        $this->getCases($caseFileIndex, $caseFileRefClass);
        $this->runCases($caseFileIndex, $caseFileRefClass);
    }

    /**
     * 获取测试用例
     */
    private function getCases($caseFileIndex, $caseFileRefClass) {
        $this->caseFiles[$caseFileIndex]['cases'] = [];
        foreach ($caseFileRefClass->getMethods(ReflectionMethod::IS_PUBLIC) as $refMethod) {
            // 排除父类方法
            if ($refMethod->class != $this->caseFiles[$caseFileIndex]['class']) continue;
            // before
            if ($refMethod->name === 'before') $this->caseFiles[$caseFileIndex]['aop']['before'] = $refMethod;
            // after
            if ($refMethod->name === 'after') $this->caseFiles[$caseFileIndex]['aop']['after'] = $refMethod;
            // case
            if ($this->isStartsWith($refMethod->name, 'test')) array_push($this->caseFiles[$caseFileIndex]['cases'], $refMethod);
        }
    }

    /**
     * 运行所有测试用例
     */
    private function runCases($caseFileIndex, $caseFileRefClass) {
        if (empty($this->caseFiles[$caseFileIndex]['cases'])) return;
        $caseFileObject = $caseFileRefClass->newInstance();
        foreach (array_keys($this->caseFiles[$caseFileIndex]['cases']) as $caseIndex) {
            $this->runCaseBefore($caseFileIndex, $caseFileObject);
            $this->runCase($caseFileIndex, $caseFileObject, $caseIndex); 
            $this->runCaseAfter($caseFileIndex, $caseFileObject);
            $this->reportLastCase($caseFileIndex, $caseIndex); 
        }
    }

    /**
     * 运行测试用例
     *
     * 只要 testXXX 测试用例抛出异常，失败用例 +1，否则，成功用例 +1（规范）
     */
    private function runCase($caseFileIndex, $caseFileObject, $caseIndex) {
        try {
            $beginTime = microtime(true);
            $this->caseFiles[$caseFileIndex]['cases'][$caseIndex]->invoke($caseFileObject);
            $this->setReport($this->caseFiles[$caseFileIndex]['cases'][$caseIndex], true, $this->getTime($beginTime));
        } catch (Exception $e) {
            $this->setReport($this->caseFiles[$caseFileIndex]['cases'][$caseIndex], false, $this->getTime($beginTime), $e->getMessage());
        }
    }

    /**
     * 运行测试用例前
     */
    private function runCaseBefore($caseFileIndex, $caseFileObject) {
        if (empty($this->caseFiles[$caseFileIndex]['aop']['before'])) return;
        $this->caseFiles[$caseFileIndex]['aop']['before']->invoke($caseFileObject);
    }

    /**
     * 运行测试用例后
     */
    private function runCaseAfter($caseFileIndex, $caseFileObject) {
        if (empty($this->caseFiles[$caseFileIndex]['aop']['after'])) return;
        $this->caseFiles[$caseFileIndex]['aop']['after']->invoke($caseFileObject);
    }

    /**
     * 汇总测试报告
     */
    private function setReport($case, $result, $time, $exception = '') {
        // 汇总报告
        $this->report['summary']['total']++;
        $result ? $this->report['summary']['pass']++ : $this->report['summary']['fail']++;
        // 记录明细
        array_push($this->report['list'], [
            "time"      => $time,
            "result"    => $result,
            "exception" => $exception,
            "method"    => $case->name,
            "class"     => $case->class,
            "toString"  => sprintf("%-20s%-18s%s.%s() %s", $result ? "\033[32mpass\033[0m" : "\033[31mfail\033[0m", 
                $time, 
                $case->class, 
                $case->name, 
                $exception
            )
        ]);
    }

    /**
     * 报告最新一条测试用例结果
     */
    private function reportLastCase($caseFileIndex, $caseIndex) {
        if (!$this->isShowProcessing) return;
        $report = '';
        if ($caseFileIndex == 0 && $caseIndex == 0) $report .= $this->getReportHeader();
        $report .= end($this->report['list'])['toString'] . "\n";
        echo $report;
    }

    /**
     * 报告测试结果
     */
    private function report() {
        if (empty($this->report['summary']['total'])) exit("No test cases were found.\n");
        $report = '';
        // list
        if (!$this->isShowProcessing) { // 是否显示测试进度，不显示的情况下，打印明细
            foreach ($this->report['list'] as $caseIndex => $caseResult) {
                if ($caseIndex == 0) $report .= $this->getReportHeader();
                $report .= $caseResult['toString'] . "\n";
            }
        }
        // summary
        $passRate = round($this->report['summary']['pass']/$this->report['summary']['total'], 4) * 100;
        $failRate = 100 - $passRate;
        $report .= "\nPass Rate: " . (empty($this->report['summary']['fail']) ? "\033[32m" : "\033[31m") . "{$passRate}%\033[0m"
                . "    Total/Pass/Fail: {$this->report['summary']['total']}/" 
                             . "\033[32m{$this->report['summary']['pass']}\033[0m/" 
                             . "\033[31m{$this->report['summary']['fail']}\033[0m"
                . "    Time: {$this->report['summary']['time']}\n";
        exit($report);
    }

    /**
     * 获取测试用例文件
     */
    private function getCaseFiles() {
        if (is_dir($this->caseFilesPath)) {
            if ($dh = opendir($this->caseFilesPath)) {
                while (($file = readdir($dh)) !== false) $this->pushCaseFile($file);
                closedir($dh);
            }
        }
    }

    private function pushCaseFile($file) {
        if (is_file($this->caseFilesPath . $file) && $this->isStartsWith($file, 'Test')) {
            array_push($this->caseFiles, [
                'file' => $this->caseFilesPath . $file,
                'class' => strstr($file, '.', true)
            ]);
        }
    }

    /**
     * 获取报告头
     */
    private function getReportHeader() { return sprintf("%-11s%-18s%s\n", 'Result', 'Time', 'CaseInfo'); }

    /**
     * 计算运行时间
     */
    private function getTime($time) { return sprintf("%.4f", (microtime(true) - $time) * 1000) . 'ms'; }

    /**
     * 判断字符串 $string 是否是以 $startStr 开头
     */
    private function isStartsWith($string, $startStr) { return strpos($string, $startStr) === 0; }

}

/**** main ****/
$caseFilesPath = empty($argv[1]) ? './' : $argv[1];
(new Punit($caseFilesPath))->run();
