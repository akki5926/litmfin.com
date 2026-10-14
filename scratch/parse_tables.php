<?php
$sqlFile = 'c:/wamp64/www/litmfin.com/sql_dump/lfin4_db_17-09-26.sql';
$fp = fopen($sqlFile, 'r');
$currentTable = '';
$tableColumns = [];

if ($fp) {
    while (($line = fgets($fp)) !== false) {
        if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`/', $line, $m)) {
            $currentTable = $m[1];
            $tableColumns[$currentTable] = [];
        } elseif ($currentTable && strpos($line, ');') === 0) {
            $currentTable = '';
        } elseif ($currentTable && preg_match('/`([^`]+)`/', $line, $cm)) {
            $tableColumns[$currentTable][] = $cm[1];
        }
    }
    fclose($fp);
}

$userTables = [];
foreach ($tableColumns as $table => $cols) {
    $matched = array_intersect($cols, ['CLIENT_CODE', 'client_code', 'user_id', 'head_UCC', 'member_UCC', 'ucc', 'UCC']);
    if (!empty($matched)) {
        $userTables[$table] = array_values($matched);
    }
}

print_r($userTables);
