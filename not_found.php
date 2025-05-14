<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = "页面未完成 - 爱心联萌";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .not-found-container {
            text-align: center;
            padding: 80px 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .not-found-icon {
            font-size: 80px;
            color: #f8b400;
            margin-bottom: 30px;
        }
        .not-found-title {
            font-size: 32px;
            margin-bottom: 20px;
            color: #333;
        }
        .not-found-message {
            font-size: 18px;
            margin-bottom: 30px;
            color: #666;
            line-height: 1.6;
        }
        .back-link {
            display: inline-block;
            padding: 12px 25px;
            background-color: #4caf50;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .back-link:hover {
            background-color: #388e3c;
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include 'includes/header.php'; ?>

    <div class="not-found-container">
        <div class="not-found-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1 class="not-found-title">此功能正在建设中</h1>
        <div class="not-found-message">
            <p>很抱歉，您访问的页面或功能尚未完成。我们的开发团队正在努力工作，将尽快完成此部分内容。</p>
            <p>感谢您的理解与支持！</p>
        </div>
        <a href="/" class="back-link">返回首页</a>
    </div>

    <!-- 底部信息 -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html> 