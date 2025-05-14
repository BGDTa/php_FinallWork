<?php
require_once 'config/database.php';

echo "项目状态检查 - " . date('Y-m-d H:i:s') . "\n";
echo "当前系统日期: " . date('Y-m-d') . "\n\n";

$result = $conn->query('SELECT id, title, start_date, end_date, status FROM projects ORDER BY id');

echo "ID | 标题 | 开始日期 | 结束日期 | 状态\n";
echo "----------------------------------------\n";

while($row = $result->fetch_assoc()) {
    echo $row['id'] . " | " . $row['title'] . " | " . $row['start_date'] . " | " . $row['end_date'] . " | " . $row['status'] . "\n";
}

echo "\n";
?> 