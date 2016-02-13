<?php
require_once __DIR__ . '/test/CsvFileIteratorTest.php';
ini_set('display_errors', '1');

$case = new CsvFileIteratorTest();
$data = $case->provider_suspending();
foreach ($data as list($path, $expectedMax, $expectedCursors)) {
    $case->test_suspending_中断して再開する($path, $expectedMax, $expectedCursors);
}

