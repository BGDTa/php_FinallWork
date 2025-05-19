<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查是否已登录，未登录则重定向到登录页
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// 获取用户信息
$user_id = $_SESSION['user_id'];
$user = get_user_info($user_id);

if (!$user) {
    // 用户不存在，可能是会话已过期但Cookie仍存在
    header('Location: logout.php');
    exit;
}

// 获取用户参与的项目列表（可关联）
$sql = "SELECT p.id, p.title 
        FROM projects p 
        JOIN registrations r ON p.id = r.project_id 
        WHERE r.user_id = ? AND r.status IN ('已通过', '已参与', '已完成')
        ORDER BY p.title";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$projects_result = $stmt->get_result();

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : '其他';
    
    // 验证表单输入
    $errors = [];
    if (empty($title)) {
        $errors[] = "标题不能为空";
    } elseif (mb_strlen($title, 'UTF-8') > 100) {
        $errors[] = "标题不能超过100个字符";
    }
    
    if (empty($content)) {
        $errors[] = "内容不能为空";
    }
    
    // 处理封面图片上传
    $cover_image = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['cover_image']['name'];
        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        // 检查文件类型
        if (!in_array(strtolower($file_ext), $allowed)) {
            $errors[] = "请上传JPG、JPEG、PNG或GIF格式的图片";
        }
        
        // 检查文件大小
        if ($_FILES['cover_image']['size'] > 5242880) { // 5MB
            $errors[] = "图片大小不能超过5MB";
        }
        
        if (empty($errors)) {
            // 生成唯一文件名
            $new_filename = uniqid() . '.' . $file_ext;
            $upload_dir = '../uploads/stories/';
            
            // 确保目录存在
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                $cover_image = $upload_path;
            } else {
                $errors[] = "图片上传失败，请重试";
            }
        }
    }
    
    // 如果没有错误，保存故事
    if (empty($errors)) {
        $sql = "INSERT INTO stories (user_id, project_id, title, content, cover_image, category_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissss", $user_id, $project_id, $title, $content, $cover_image, $category_id);
        
        if ($stmt->execute()) {
            // 添加积分记录
            update_user_points($user_id, 30, '分享故事', '发布志愿故事《' . $title . '》');
            
            set_message('故事发布成功，审核通过后将在网站展示。', 'success');
            header('Location: stories.php');
            exit;
        } else {
            $errors[] = "故事发布失败，请重试";
        }
    }
}

$page_title = "发布志愿故事 - 爱心联萌";
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
        .form-container {
            max-width: 800px;
            margin: 40px auto;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            padding: 30px;
        }
        
        .form-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray-color);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 200px;
        }
        
        .form-text {
            margin-top: 5px;
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        .btn-container {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">分享志愿故事</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form action="add_story.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">故事标题</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                    <div class="form-text">不超过100个字符</div>
                </div>
                
                <div class="form-group">
                    <label for="project_id">关联项目（可选）</label>
                    <select id="project_id" name="project_id" class="form-control">
                        <option value="">- 不关联项目 -</option>
                        <?php while ($project = $projects_result->fetch_assoc()): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo (isset($project_id) && $project_id == $project['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-text">如果故事与您参与的某个项目相关，可以选择关联</div>
                </div>
                
                <div class="form-group">
                    <label for="category_id">故事分类</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="环保" <?php echo (isset($category_id) && $category_id == '环保') ? 'selected' : ''; ?>>环保</option>
                        <option value="教育" <?php echo (isset($category_id) && $category_id == '教育') ? 'selected' : ''; ?>>教育</option>
                        <option value="扶老" <?php echo (isset($category_id) && $category_id == '扶老') ? 'selected' : ''; ?>>扶老</option>
                        <option value="助残" <?php echo (isset($category_id) && $category_id == '助残') ? 'selected' : ''; ?>>助残</option>
                        <option value="救灾" <?php echo (isset($category_id) && $category_id == '救灾') ? 'selected' : ''; ?>>救灾</option>
                        <option value="社区服务" <?php echo (isset($category_id) && $category_id == '社区服务') ? 'selected' : ''; ?>>社区服务</option>
                        <option value="其他" <?php echo (isset($category_id) && $category_id == '其他') ? 'selected' : ''; ?>>其他</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cover_image">封面图片</label>
                    <input type="file" id="cover_image" name="cover_image" class="form-control">
                    <div class="form-text">支持JPG、JPEG、PNG和GIF格式，大小不超过5MB</div>
                </div>
                
                <div class="form-group">
                    <label for="content">故事内容</label>
                    <textarea id="content" name="content" class="form-control" required><?php echo isset($content) ? htmlspecialchars($content) : ''; ?></textarea>
                    <div class="form-text">分享您的志愿服务经历、感受和收获</div>
                </div>
                
                <div class="btn-container">
                    <a href="dashboard.php" class="btn btn-secondary">返回</a>
                    <button type="submit" class="btn btn-primary">提交故事</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 