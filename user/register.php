<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查是否已经登录
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// 判断注册类型 (志愿者/机构)
$register_type = isset($_GET['type']) && $_GET['type'] === 'organization' ? 'organization' : 'volunteer';

// 处理注册表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;
    
    // 根据注册类型获取额外字段
    if ($register_type === 'volunteer') {
        $real_name = sanitize($_POST['real_name']);
        $role = 'volunteer';
        $status = '已审核'; // 志愿者账户无需审核，直接激活
    } else {
        $organization_name = sanitize($_POST['organization_name']);
        $organization_intro = sanitize($_POST['organization_intro']);
        $role = 'organization';
        $status = '待审核'; // 机构账户需要审核
    }
    
    // 验证输入
    $errors = [];
    
    // 验证用户名
    if (empty($username)) {
        $errors[] = '请输入用户名';
    } elseif (strlen($username) < 4 || strlen($username) > 20) {
        $errors[] = '用户名长度应在4-20个字符之间';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = '用户名只能包含字母、数字和下划线';
    } else {
        // 检查用户名是否已存在
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = '该用户名已被注册';
        }
    }
    
    // 验证邮箱
    if (empty($email)) {
        $errors[] = '请输入邮箱';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入有效的邮箱地址';
    } else {
        // 检查邮箱是否已存在
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = '该邮箱已被注册';
        }
    }
    
    // 验证密码
    if (empty($password)) {
        $errors[] = '请输入密码';
    } elseif (strlen($password) < 6) {
        $errors[] = '密码长度至少为6个字符';
    }
    
    // 验证确认密码
    if ($password !== $confirm_password) {
        $errors[] = '两次输入的密码不匹配';
    }
    
    // 验证手机号（中国手机号格式）
    if (!empty($phone) && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $errors[] = '请输入有效的手机号码';
    }
    
    // 针对机构的验证
    if ($register_type === 'organization') {
        if (empty($organization_name)) {
            $errors[] = '请输入机构名称';
        }
        if (empty($organization_intro)) {
            $errors[] = '请输入机构简介';
        }
    }
    
    // 验证是否同意条款
    if (!$agree_terms) {
        $errors[] = '您必须同意用户条款和隐私政策';
    }
    
    // 如果没有错误，创建用户
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($register_type === 'volunteer') {
            $sql = "INSERT INTO users (username, email, password, phone, real_name, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $username, $email, $hashed_password, $phone, $real_name, $role, $status);
        } else {
            $sql = "INSERT INTO users (username, email, password, phone, organization_name, organization_intro, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $username, $email, $hashed_password, $phone, $organization_name, $organization_intro, $role, $status);
        }
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            if ($register_type === 'volunteer') {
                // 志愿者直接登录
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                
                // 记录登录活动
                $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    // 记录错误但继续执行，避免中断注册流程
                    error_log("数据库错误: " . $conn->error);
                } else {
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }
                
                // 设置欢迎消息
                set_message('注册成功！欢迎加入爱心联萌公益志愿平台。', 'success');
                
                // 重定向到个人中心
                header('Location: dashboard.php');
                exit;
            } else {
                // 机构账户需要审核
                set_message('感谢您的注册！您的机构账户已提交，等待管理员审核。审核结果将通过邮件通知。', 'info');
                
                // 重定向到登录页
                header('Location: login.php');
                exit;
            }
        } else {
            $errors[] = '注册失败，请稍后再试';
        }
    }
}

$page_title = $register_type === 'organization' ? "机构入驻 - 爱心联萌" : "志愿者注册 - 爱心联萌";
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
        .register-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 30px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .register-form {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-col {
            flex: 1;
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
        
        .agree-terms {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .agree-terms input {
            margin-right: 10px;
            margin-top: 5px;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            background-color: var(--primary-color);
        }
        
        .register-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .role-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .role-tab {
            padding: 10px 20px;
            margin: 0 10px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .role-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .role-tab:hover {
            color: var(--primary-color);
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
        
        textarea.form-control {
            padding: 12px 15px;
            height: 120px;
            resize: vertical;
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <?php if ($register_type === 'volunteer'): ?>
                    <h2>成为志愿者</h2>
                    <p>加入爱心联萌，开启您的公益之旅</p>
                <?php else: ?>
                    <h2>机构入驻</h2>
                    <p>成为爱心联萌平台的合作伙伴，一起传递爱心</p>
                <?php endif; ?>
            </div>
            
            <div class="role-tabs">
                <a href="register.php" class="role-tab <?php echo $register_type === 'volunteer' ? 'active' : ''; ?>">志愿者注册</a>
                <a href="register.php?type=organization" class="role-tab <?php echo $register_type === 'organization' ? 'active' : ''; ?>">机构入驻</a>
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
            
            <form class="register-form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="username">用户名 <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="username" name="username" class="form-control" placeholder="请输入用户名" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
                            </div>
                            <small class="form-text">4-20个字符，只能包含字母、数字和下划线</small>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="email">邮箱 <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control" placeholder="请输入邮箱" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="password">密码 <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control" placeholder="请输入密码" required>
                            </div>
                            <small class="form-text">至少6个字符</small>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="confirm_password">确认密码 <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="请再次输入密码" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($register_type === 'volunteer'): ?>
                <!-- 志愿者注册表单字段 -->
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="real_name">真实姓名</label>
                            <div class="input-group">
                                <i class="fas fa-id-card"></i>
                                <input type="text" id="real_name" name="real_name" class="form-control" placeholder="请输入真实姓名" value="<?php echo isset($real_name) ? htmlspecialchars($real_name) : ''; ?>">
                            </div>
                            <small class="form-text">用于志愿服务证明，建议填写真实姓名</small>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="phone">手机号码</label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder="请输入手机号码" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- 机构注册表单字段 -->
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="organization_name">机构名称 <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-building"></i>
                                <input type="text" id="organization_name" name="organization_name" class="form-control" placeholder="请输入机构全称" value="<?php echo isset($organization_name) ? htmlspecialchars($organization_name) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="phone">联系电话 <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder="请输入联系电话" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="organization_intro">机构简介 <span class="required">*</span></label>
                    <textarea id="organization_intro" name="organization_intro" class="form-control" placeholder="请简要介绍机构的性质、规模、主要业务等" required><?php echo isset($organization_intro) ? htmlspecialchars($organization_intro) : ''; ?></textarea>
                    <small class="form-text">200-500字为宜</small>
                </div>
                <?php endif; ?>
                
                <div class="agree-terms">
                    <input type="checkbox" id="agree_terms" name="agree_terms" value="1" <?php echo isset($agree_terms) && $agree_terms ? 'checked' : ''; ?> required>
                    <label for="agree_terms">我已阅读并同意 <a href="/not_found.php" target="_blank">用户服务条款</a> 和 <a href="/not_found.php" target="_blank">隐私政策</a></label>
                </div>
                
                <button type="submit" class="btn btn-register">
                    <?php echo $register_type === 'volunteer' ? '注册' : '提交申请'; ?>
                </button>
            </form>
            
            <div class="register-footer">
                <p>已有账户？<a href="login.php">立即登录</a></p>
            </div>
        </div>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 