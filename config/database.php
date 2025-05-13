<?php
// 数据库连接配置
$servername = "localhost";
$username = "root";
$password = "123456";
$dbname = "volunteer_platform";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 设置字符集
mysqli_set_charset($conn, "utf8mb4");

/**
 * 安全过滤输入数据
 * @param string $data 需要过滤的数据
 * @return string 过滤后的数据
 */
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}
?> 