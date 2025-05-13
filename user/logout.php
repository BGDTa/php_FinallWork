<?php
session_start();

// 清除所有会话变量
$_SESSION = array();

// 清除会话Cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 清除记住我Cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/');
}

// 销毁会话
session_destroy();

// 重定向到登录页面
header('Location: login.php');
exit;
?> 