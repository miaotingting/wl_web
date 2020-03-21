<?php
include_once __DIR__ . '/PHPExcel.php';
include_once __DIR__ . '/PHPExcelReadFilter.php';   //实现接口实现自定义得去多少行


$startRow  = 2;
$endRow    = 1000;
$excelFile = __DIR__ . '/data/payment.xlsx';

$result = readFromExcel($excelFile, null, $startRow, $endRow);
echo "<pre>";
print_r($result);





/**
 * 读取excel转换成数组
 * 
 * @param string $excelFile 文件路径
 * @param string $excelType excel后缀格式
 * @param int $startRow 开始读取的行数
 * @param int $endRow 结束读取的行数
 * @retunr array
 */
function readFromExcel($excelFile, $excelType = null, $startRow = 1, $endRow = null) {
    include_once __DIR__ . '/PHPExcel.php';

    $excelReader = \PHPExcel_IOFactory::createReader("Excel2007");
    $excelReader->setReadDataOnly(true);

    //如果有指定行数，则设置过滤器
    if ($startRow && $endRow) {
        $perf           = new PHPExcelReadFilter();
        $perf->startRow = $startRow;
        $perf->endRow   = $endRow;
        $excelReader->setReadFilter($perf);
    }

    $phpexcel    = $excelReader->load($excelFile);
    $activeSheet = $phpexcel->getActiveSheet();
    if (!$endRow) {
        $endRow = $activeSheet->getHighestRow(); //总行数
    }

    $highestColumn      = $activeSheet->getHighestColumn(); //最后列数所对应的字母，例如第2行就是B
    $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn); //总列数

    $data = array();
    for ($row = $startRow; $row <= $endRow; $row++) {
        for ($col = 0; $col < $highestColumnIndex; $col++) {
            $data[$row][] = (string) $activeSheet->getCellByColumnAndRow($col, $row)->getValue();
        }
    }
    return $data;
}
