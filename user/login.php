<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查是否已经登录
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? 1 : 0;
    
    // 验证输入
    $errors = [];
    
    if (empty($username)) {
        $errors[] = '请输入用户名';
    }
    
    if (empty($password)) {
        $errors[] = '请输入密码';
    }
    
    // 如果没有错误，验证用户
    if (empty($errors)) {
        $sql = "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            die('准备语句失败: ' . $conn->error);
        }
        
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // 检查用户状态
                if ($user['status'] == '已审核') {
                    // 登录成功
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // 如果选择了"记住我"选项，设置cookie
                    if ($remember) {
                        $token = generate_random_string(32);
                        $expires = time() + (30 * 24 * 60 * 60); // 30天
                        
                        // 存储token到数据库（简单实现，实际项目中应该使用专门的表存储token）
                        $token_hash = password_hash($token, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET remember_token = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $token_hash, $user['id']);
                        $stmt->execute();
                        
                        // 设置cookie
                        setcookie('remember_token', $token, $expires, '/');
                        setcookie('user_id', $user['id'], $expires, '/');
                    }
                    
                    // 记录登录活动
                    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    
                    if ($stmt === false) {
                        // 准备语句失败，记录错误但继续执行
                        error_log('准备语句失败: ' . $conn->error);
                    } else {
                        $stmt->bind_param("i", $user['id']);
                        $stmt->execute();
                    }
                    
                    // 根据用户角色跳转到不同页面
                    if ($user['role'] == 'admin' || $user['role'] == 'organization') {
                        // 管理员和机构用户跳转到后台管理
                        header('Location: ../admin/index.php');
                    } else {
                        // 志愿者跳转到个人中心
                        header('Location: dashboard.php');
                    }
                    exit;
                    
                } else {
                    $errors[] = '您的账户正在等待审核，请耐心等待或联系管理员';
                }
            } else {
                $errors[] = '用户名或密码错误';
            }
        } else {
            $errors[] = '用户名或密码错误';
        }
    }
}

$page_title = "用户登录 - 爱心联萌";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .login-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .login-form {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
            outline: none;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input {
            margin-right: 10px;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            background-color: var(--primary-color);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .login-footer p {
            margin-bottom: 10px;
        }
        
        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }
        
        .social-login a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: #f8f9fa;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .social-login a:hover {
            transform: translateY(-3px);
        }
        
        .social-login i {
            font-size: 1.2rem;
        }
        
        .errors {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #f5c6cb;
        }
        
        .errors ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h2>欢迎回来</h2>
                <p>登录您的账户以继续</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form class="login-form" method="post" action="">
                <div class="form-group">
                    <label for="username">用户名或邮箱</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" class="form-control" placeholder="请输入用户名或邮箱" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="请输入密码" required>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">记住我</label>
                </div>
                
                <button type="submit" class="btn btn-login">登录</button>
            </form>
            
            <div class="login-footer">
                <p><a href="forgot_password.php">忘记密码？</a></p>
                <p>没有账户？<a href="register.php">立即注册</a></p>
                <p>机构入驻？<a href="register.php?type=organization">机构注册</a></p>
                
                <div class="social-login">
                    <a href="#" title="微信登录"><i class="fab fa-weixin" style="color: #07C160;"></i></a>
                    <a href="#" title="QQ登录"><i class="fab fa-qq" style="color: #12B7F5;"></i></a>
                    <a href="#" title="微博登录"><i class="fab fa-weibo" style="color: #E6162D;"></i></a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 