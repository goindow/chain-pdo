<?php
// +--------------------------------------------------------------------------------------
// | Punit [单元测试框架 - 定义测试类、批量自动测试、报告结果]
// +--------------------------------------------------------------------------------------
// | Time: 2020-07-06
// +--------------------------------------------------------------------------------------
// | Author: hyb <76788424@qq.com>
// +--------------------------------------------------------------------------------------
// | Usage: php $path/punit.php $testCaseDir
// |    eg: php ./punit/Punit.php /usr/local/var/php/chain-pdo/test/
// +--------------------------------------------------------------------------------------
// | Specification:
// | 1.TestXXX.php 被解析为测试类
// | 2.TestXXX.php 下的 testXXX 方法被解析为测试用例
// | 3.只要 testXXX 抛出异常，失败用例 +1，否则，成功用例 +1
// | 4.自动测试引导类 Bootstrap，接受一个参数（测试用例目录），只解析该目录下的测试类，不会迭代解析子目录
// +--------------------------------------------------------------------------------------
class Punit {

    // 测试用例目录，只解析当前目录（目录深度一级、不支持迭代解析）的名为 TestXXX.php（规范）
    private $caseFilesPath = '';

    // 测试用例文件
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
     * 报告测试结果
     */
    public function report() {
        $report = '';
        if (empty($this->report['summary']['total'])) $report = "No test cases were found.\n";
        // list
        foreach ($this->report['list'] as $caseIndex => $caseResult) {
            if ($caseIndex == 0) $report .= "Result    Time          CaseInfo\n"; 
            $report .= $caseResult['toString'] . "\n";
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
     * 运行测试
     */
    public function run() {
        $beginTime = microtime(true);
        foreach ($this->caseFiles as $caseFileIndex => $caseFile) { 
            $this->runCaseFile($caseFileIndex, $caseFile); 
        }
        $this->report['summary']['time'] = $this->getTime($beginTime, microtime(true));
        $this->report();
    }

    /**
     * 运行测试文件
     */
    private function runCaseFile($caseFileIndex, $caseFile) {
        require($caseFile['file']);
        $caseFileRefClass = new ReflectionClass($caseFile['class']);
        $cases = $this->getCases($caseFileIndex, $caseFileRefClass->getMethods(ReflectionMethod::IS_PUBLIC));
        $this->runCases($caseFileRefClass, $cases);
    }

    /**
     * 获取测试用例
     */
    private function getCases($caseFileIndex, $refMethods) {
        $cases = [];
        foreach ($refMethods as $refMethod) {
            // 排除父类的 public 方法
            if ($refMethod->class != $this->caseFiles[$caseFileIndex]['class']) continue;
            // 排除非 testXXX 开头的 public 方法
            if (!$this->isStartsWith($refMethod->name, 'test')) continue;
            array_push($cases, $refMethod);
        }
        return $cases;
    }

    /**
     * 运行所有测试用例
     */
    private function runCases($caseFileRefClass, $cases) {
        if (empty($cases)) return;
        $caseFileObject = $caseFileRefClass->newInstance();
        foreach ($cases as $case) {
            $this->runCase($caseFileObject, $case); 
        }
    }

    /**
     * 运行测试用例
     *
     * 只要 testXXX 测试用例抛出异常，失败用例 +1，否则，成功用例 +1（规范）
     */
    private function runCase($caseFileObject, $case) {
        try {
            $beginTime = microtime(true);
            $case->invoke($caseFileObject);
            $this->sum($case, true, $beginTime, microtime(true));
        } catch (Exception $e) {
            $this->sum($case, false, $beginTime, microtime(true), $e->getMessage());
        }
    }

    /**
     * 汇总测试报告
     */
    private function sum($case, $result, $beginTime, $endTime, $exception = '') {
        // 汇总报告
        $this->report['summary']['total']++;
        $result ? $this->report['summary']['pass']++ : $this->report['summary']['fail']++;
        // 记录明细
        $time = $this->getTime($beginTime, $endTime);
        array_push($this->report['list'], [
            "toString"  => ($result ? "\033[32mpass\033[0m" : "\033[31mfail\033[0m") . "      {$time}      {$case->class}.{$case->name}() {$exception}",
            "class"     => $case->class, 
            "method"    => $case->name,
            "exception" => $exception,
            "result"    => $result,
            "time"      => $time
        ]);
    }

    /**
     * 获取测试用例文件
     */
    private function getCaseFiles() {
        if (is_dir($this->caseFilesPath)) {
            if ($dh = opendir($this->caseFilesPath)) {
                while (($file = readdir($dh)) !== false) { $this->pushCaseFile($file); }
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

    private function getTime($beginTime, $endTime) { return sprintf("%.4f", ($endTime - $beginTime) * 1000) . 'ms'; }

    /**
     * 判断字符串 $string 是否是以 $startStr 开头
     */
    private function isStartsWith($string, $startStr) { return strpos($string, $startStr) === 0; }

}

/**** main ****/
$caseFilesPath = empty($argv[1]) ? './' : $argv[1];
(new Punit($caseFilesPath))->run();