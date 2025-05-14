<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 获取项目ID
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 检查项目ID是否有效
if ($project_id <= 0) {
    set_message('无效的项目ID', 'error');
    header('Location: list.php');
    exit;
}

// 获取项目信息
$sql = "SELECT p.*, u.username as org_username, u.organization_name, u.avatar as org_avatar 
        FROM projects p 
        LEFT JOIN users u ON p.organization_id = u.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    set_message('项目不存在', 'error');
    header('Location: list.php');
    exit;
}

$project = $result->fetch_assoc();

// 检查用户是否已报名该项目
$already_registered = false;
if (is_logged_in()) {
    $sql = "SELECT id FROM registrations WHERE project_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $project_id, $_SESSION['user_id']);
    $stmt->execute();
    $already_registered = ($stmt->get_result()->num_rows > 0);
}

// 处理报名表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // 检查用户是否登录
    if (!is_logged_in()) {
        set_message('请先登录后再报名', 'error');
        header('Location: ../user/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    // 检查项目是否仍在招募中
    if ($project['status'] !== '招募中') {
        set_message('该项目不在招募期，无法报名', 'error');
        header('Location: detail.php?id=' . $project_id);
        exit;
    }
    
    // 检查是否已报名
    if ($already_registered) {
        set_message('您已报名该项目', 'info');
        header('Location: detail.php?id=' . $project_id);
        exit;
    }
    
    // 检查名额是否已满
    if ($project['registered'] >= $project['quota']) {
        set_message('该项目报名名额已满', 'error');
        header('Location: detail.php?id=' . $project_id);
        exit;
    }
    
    // 获取表单数据
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $id_card = isset($_POST['id_card']) ? sanitize($_POST['id_card']) : '';
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    
    // 验证表单数据
    $errors = [];
    
    if (empty($name)) {
        $errors[] = '请输入姓名';
    }
    
    if (empty($phone) || !preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $errors[] = '请输入有效的手机号码';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入有效的邮箱地址';
    }
    
    if (empty($errors)) {
        // 插入报名记录
        $sql = "INSERT INTO registrations (project_id, user_id, name, phone, email, id_card, status, feedback) 
                VALUES (?, ?, ?, ?, ?, ?, '待审核', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssss", $project_id, $_SESSION['user_id'], $name, $phone, $email, $id_card, $message);
        
        if ($stmt->execute()) {
            // 更新项目报名人数
            $sql = "UPDATE projects SET registered = registered + 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            // 增加用户爱心值
            update_user_points($_SESSION['user_id'], 10);
            
            set_message('报名成功！项目管理员会尽快审核您的申请。', 'success');
            header('Location: detail.php?id=' . $project_id);
            exit;
        } else {
            $errors[] = '报名失败，请稍后再试';
        }
    }
}

// 获取项目媒体资料
$sql = "SELECT * FROM media WHERE project_id = ? ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$media_result = $stmt->get_result();

// 获取相关项目
$sql = "SELECT * FROM projects 
        WHERE id != ? AND (location LIKE ? OR MATCH(title, description) AGAINST(?)) 
        AND status IN ('招募中', '进行中') 
        LIMIT 3";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("MySQL prepare error: " . $conn->error);
    $related_projects = false;
} else {
    $location_param = '%' . $conn->real_escape_string(explode(' ', $project['location'])[0]) . '%';
    $content_param = $project['title'];
    $stmt->bind_param("iss", $project_id, $location_param, $content_param);
    $stmt->execute();
    $related_projects = $stmt->get_result();
}

// 获取项目评论
$sql = "SELECT c.*, u.username, u.avatar FROM comments c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.project_id = ? AND c.status = '已审核' 
        ORDER BY c.created_at DESC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$comments_result = $stmt->get_result();

$page_title = $project['title'] . " - 爱心联萌";
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
        .project-detail-container {
            max-width: 1200px;
            margin: 40px auto;
        }
        
        .project-detail-grid {
            display: grid;
            grid-template-columns: 7fr 3fr;
            gap: 30px;
        }
        
        .project-main {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .project-cover {
            height: 400px;
            position: relative;
        }
        
        .project-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .project-status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .status-recruiting {
            background-color: var(--primary-color);
            color: white;
        }
        
        .status-ongoing {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .status-ended {
            background-color: var(--gray-color);
            color: white;
        }
        
        .project-content {
            padding: 30px;
        }
        
        .project-title {
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .project-meta {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            color: var(--gray-color);
        }
        
        .project-meta-item {
            display: flex;
            align-items: center;
        }
        
        .project-meta-item i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        .project-tabs {
            display: flex;
            border-bottom: 1px solid var(--light-gray-color);
            margin-bottom: 30px;
        }
        
        .project-tab {
            padding: 10px 20px;
            cursor: pointer;
            position: relative;
            margin-right: 10px;
        }
        
        .project-tab.active {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .project-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .project-description {
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .project-info-list {
            list-style: none;
            margin-bottom: 30px;
        }
        
        .project-info-list li {
            padding: 12px 0;
            display: flex;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .project-info-label {
            width: 100px;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .project-info-value {
            flex: 1;
        }
        
        .project-media {
            margin-bottom: 30px;
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .media-item {
            height: 150px;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }
        
        .media-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .media-item:hover img {
            transform: scale(1.05);
        }
        
        .video-item {
            position: relative;
        }
        
        .video-item::after {
            content: '\f144';
            font-family: 'Font Awesome 5 Free';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            color: white;
            opacity: 0.8;
            transition: var(--transition);
        }
        
        .video-item:hover::after {
            opacity: 1;
            color: var(--primary-color);
        }
        
        .project-sidebar {
            position: sticky;
            top: 90px;
            align-self: start;
        }
        
        .sidebar-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .sidebar-card-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .organization-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .organization-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .organization-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .organization-projects {
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .quota-info {
            margin-bottom: 20px;
        }
        
        .quota-progress {
            width: 100%;
            height: 8px;
            background-color: var(--light-gray-color);
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .quota-bar {
            height: 100%;
            background-color: var(--primary-color);
        }
        
        .quota-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .register-form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 100px;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            background-color: var(--primary-color);
        }
        
        .related-projects {
            margin-top: 50px;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 991px) {
            .project-detail-grid {
                grid-template-columns: 1fr;
            }
            
            .project-sidebar {
                position: static;
                margin-top: 0;
            }
            
            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .project-cover {
                height: 300px;
            }
            
            .related-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container project-detail-container">
        <?php echo display_message(); ?>
        
        <div class="project-detail-grid">
            <div class="project-main">
                <!-- 项目封面 -->
                <div class="project-cover">
                    <img src="<?php echo !empty($project['cover_image']) ? $project['cover_image'] : 'https://source.unsplash.com/random/800x400/?charity'; ?>" alt="<?php echo $project['title']; ?>">
                    <span class="project-status-badge <?php 
                        switch ($project['status']) {
                            case '招募中': echo 'status-recruiting'; break;
                            case '进行中': echo 'status-ongoing'; break;
                            default: echo 'status-ended';
                        }
                    ?>">
                        <?php echo $project['status']; ?>
                    </span>
                </div>
                
                <!-- 项目内容 -->
                <div class="project-content">
                    <h1 class="project-title"><?php echo $project['title']; ?></h1>
                    
                    <div class="project-meta">
                        <div class="project-meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo $project['location']; ?></span>
                        </div>
                        <div class="project-meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('Y-m-d', strtotime($project['start_date'])); ?> - <?php echo date('Y-m-d', strtotime($project['end_date'])); ?></span>
                        </div>
                        <div class="project-meta-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $project['registered']; ?>/<?php echo $project['quota']; ?> 人已报名</span>
                        </div>
                    </div>
                    
                    <!-- 项目标签页 -->
                    <div class="project-tabs">
                        <div class="project-tab active" data-tab="description">项目详情</div>
                        <div class="project-tab" data-tab="requirements">报名要求</div>
                        <div class="project-tab" data-tab="benefits">志愿者权益</div>
                        <div class="project-tab" data-tab="comments">评论反馈</div>
                    </div>
                    
                    <!-- 项目描述 -->
                    <div id="description" class="tab-content active">
                        <div class="project-description">
                            <?php echo nl2br($project['description']); ?>
                        </div>
                        
                        <div class="project-info">
                            <h3 class="section-title">基本信息</h3>
                            <ul class="project-info-list">
                                <li>
                                    <span class="project-info-label">活动时间</span>
                                    <span class="project-info-value"><?php echo date('Y-m-d', strtotime($project['start_date'])); ?> - <?php echo date('Y-m-d', strtotime($project['end_date'])); ?></span>
                                </li>
                                <li>
                                    <span class="project-info-label">活动地点</span>
                                    <span class="project-info-value"><?php echo $project['location']; ?></span>
                                </li>
                                <li>
                                    <span class="project-info-label">招募人数</span>
                                    <span class="project-info-value"><?php echo $project['quota']; ?> 人</span>
                                </li>
                                <li>
                                    <span class="project-info-label">联系人</span>
                                    <span class="project-info-value"><?php echo $project['contact_name']; ?></span>
                                </li>
                                <li>
                                    <span class="project-info-label">联系电话</span>
                                    <span class="project-info-value"><?php echo $project['contact_phone']; ?></span>
                                </li>
                                <li>
                                    <span class="project-info-label">联系邮箱</span>
                                    <span class="project-info-value"><?php echo $project['contact_email']; ?></span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- 项目媒体 -->
                        <?php if ($media_result->num_rows > 0): ?>
                            <div class="project-media">
                                <h3 class="section-title">项目图片</h3>
                                <div class="media-grid">
                                    <?php while ($media = $media_result->fetch_assoc()): ?>
                                        <?php if ($media['type'] == 'image'): ?>
                                            <div class="media-item">
                                                <img src="<?php echo $media['file_path']; ?>" alt="<?php echo $media['title']; ?>">
                                            </div>
                                        <?php elseif ($media['type'] == 'video'): ?>
                                            <div class="media-item video-item">
                                                <img src="<?php echo !empty($media['thumbnail']) ? $media['thumbnail'] : 'https://via.placeholder.com/200x150?text=视频'; ?>" alt="<?php echo $media['title']; ?>">
                                            </div>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 项目视频 -->
                        <?php if (!empty($project['video_url'])): ?>
                            <div class="project-video">
                                <h3 class="section-title">项目视频</h3>
                                <div class="video-container">
                                    <iframe width="100%" height="400" src="<?php echo $project['video_url']; ?>" frameborder="0" allowfullscreen></iframe>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 报名要求 -->
                    <div id="requirements" class="tab-content">
                        <div class="project-description">
                            <h3 class="section-title">报名要求</h3>
                            <?php if (!empty($project['requirements'])): ?>
                                <?php echo nl2br($project['requirements']); ?>
                            <?php else: ?>
                                <p>本项目暂无特殊报名要求，欢迎所有志愿者参与。</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 志愿者权益 -->
                    <div id="benefits" class="tab-content">
                        <div class="project-description">
                            <h3 class="section-title">志愿者权益</h3>
                            <?php if (!empty($project['benefits'])): ?>
                                <?php echo nl2br($project['benefits']); ?>
                            <?php else: ?>
                                <p>参与本项目的志愿者将获得：</p>
                                <ul>
                                    <li>志愿服务证书</li>
                                    <li>志愿服务时长记录</li>
                                    <li>爱心联萌平台积分奖励</li>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 评论反馈 -->
                    <div id="comments" class="tab-content">
                        <h3 class="section-title">志愿者评论</h3>
                        
                        <?php if ($comments_result->num_rows > 0): ?>
                            <div class="comments-list">
                                <?php while ($comment = $comments_result->fetch_assoc()): ?>
                                    <div class="comment-item">
                                        <div class="comment-user">
                                            <img src="<?php echo !empty($comment['avatar']) ? $comment['avatar'] : 'https://via.placeholder.com/40'; ?>" alt="用户头像" class="comment-avatar">
                                            <div>
                                                <div class="comment-username"><?php echo $comment['username']; ?></div>
                                                <div class="comment-date"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="comment-content">
                                            <?php echo nl2br($comment['content']); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">暂无评论，成为第一个评论者吧！</p>
                        <?php endif; ?>
                        
                        <?php if (is_logged_in()): ?>
                            <div class="comment-form">
                                <h4>发表评论</h4>
                                <form action="../ajax/add_comment.php" method="post">
                                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                    <div class="form-group">
                                        <textarea name="content" class="form-control" placeholder="分享您对该项目的看法..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">提交评论</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="comment-login">
                                <p>请<a href="../user/login.php">登录</a>后发表评论</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="project-sidebar">
                <!-- 项目发布机构 -->
                <div class="sidebar-card">
                    <h3 class="sidebar-card-title">发布机构</h3>
                    <div class="organization-info">
                        <img src="<?php echo !empty($project['org_avatar']) ? $project['org_avatar'] : 'https://via.placeholder.com/60'; ?>" alt="<?php echo $project['organization_name']; ?>" class="organization-avatar">
                        <div>
                            <div class="organization-name"><?php echo $project['organization_name']; ?></div>
                            <div class="organization-projects">
                                <?php
                                $sql = "SELECT COUNT(*) as count FROM projects WHERE organization_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $project['organization_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $projects_count = $result->fetch_assoc()['count'];
                                echo "发布项目：{$projects_count} 个";
                                ?>
                            </div>
                        </div>
                    </div>
                    <a href="../organization/detail.php?id=<?php echo $project['organization_id']; ?>" class="btn btn-outline">查看机构主页</a>
                </div>
                
                <!-- 报名信息 -->
                <div class="sidebar-card">
                    <h3 class="sidebar-card-title">报名信息</h3>
                    <div class="quota-info">
                        <div class="quota-progress">
                            <div class="quota-bar" style="width: <?php echo ($project['quota'] > 0) ? min(($project['registered'] / $project['quota'] * 100), 100) : 0; ?>%"></div>
                        </div>
                        <div class="quota-text">
                            <span>已报名：<?php echo $project['registered']; ?> 人</span>
                            <span>剩余名额：<?php echo max(0, $project['quota'] - $project['registered']); ?> 人</span>
                        </div>
                    </div>
                    
                    <?php if ($project['status'] === '招募中' && !$already_registered && $project['registered'] < $project['quota']): ?>
                        <?php if (is_logged_in()): ?>
                            <div class="register-form">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label for="name">姓名 <span class="required">*</span></label>
                                        <input type="text" id="name" name="name" class="form-control" required value="<?php echo $user['real_name']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="phone">手机号码 <span class="required">*</span></label>
                                        <input type="tel" id="phone" name="phone" class="form-control" required value="<?php echo $user['phone']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">邮箱 <span class="required">*</span></label>
                                        <input type="email" id="email" name="email" class="form-control" required value="<?php echo $user['email']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="id_card">身份证号</label>
                                        <input type="text" id="id_card" name="id_card" class="form-control" value="<?php echo $user['id_card']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="message">申请留言</label>
                                        <textarea id="message" name="message" class="form-control" placeholder="请简述您参与该项目的动机和相关经验..."></textarea>
                                    </div>
                                    
                                    <button type="submit" name="register" class="btn btn-register">立即报名</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="login-prompt">
                                <p>请先登录后再进行报名</p>
                                <a href="../user/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">立即登录</a>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($already_registered): ?>
                        <div class="already-registered">
                            <p>您已成功报名该项目</p>
                            <a href="../user/dashboard.php" class="btn btn-outline">查看我的报名</a>
                        </div>
                    <?php elseif ($project['status'] !== '招募中'): ?>
                        <div class="registration-closed">
                            <p>该项目<?php echo $project['status']; ?>，报名通道已关闭</p>
                        </div>
                    <?php else: ?>
                        <div class="quota-full">
                            <p>该项目报名名额已满</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 分享项目 -->
                <div class="sidebar-card">
                    <h3 class="sidebar-card-title">分享项目</h3>
                    <div class="share-buttons">
                        <a href="javascript:;" class="btn btn-sm btn-outline" onclick="window.open('https://service.weibo.com/share/share.php?url=' + encodeURIComponent(window.location.href) + '&title=' + encodeURIComponent('<?php echo $project['title']; ?> - 爱心联萌'), '_blank')"><i class="fab fa-weibo"></i> 微博</a>
                        <a href="javascript:;" class="btn btn-sm btn-outline" onclick="window.open('https://connect.qq.com/widget/shareqq/index.html?url=' + encodeURIComponent(window.location.href) + '&title=' + encodeURIComponent('<?php echo $project['title']; ?> - 爱心联萌'), '_blank')"><i class="fab fa-qq"></i> QQ</a>
                        <a href="javascript:;" class="btn btn-sm btn-outline" id="wechat-share"><i class="fab fa-weixin"></i> 微信</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 相关项目 -->
        <?php if ($related_projects && $related_projects->num_rows > 0): ?>
            <div class="related-projects">
                <h2 class="section-title">相关项目推荐</h2>
                <div class="related-grid">
                    <?php while ($related = $related_projects->fetch_assoc()): ?>
                        <div class="project-card">
                            <div class="project-image">
                                <img src="<?php echo !empty($related['cover_image']) ? $related['cover_image'] : 'https://source.unsplash.com/random/300x200/?charity'; ?>" alt="<?php echo $related['title']; ?>">
                                <span class="project-status-badge <?php 
                                    switch ($related['status']) {
                                        case '招募中': echo 'status-recruiting'; break;
                                        case '进行中': echo 'status-ongoing'; break;
                                        default: echo 'status-ended';
                                    }
                                ?>">
                                    <?php echo $related['status']; ?>
                                </span>
                            </div>
                            <div class="project-content">
                                <h3 class="project-title">
                                    <a href="detail.php?id=<?php echo $related['id']; ?>"><?php echo $related['title']; ?></a>
                                </h3>
                                <div class="project-meta">
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo $related['location']; ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($related['start_date'])); ?></span>
                                </div>
                                <a href="detail.php?id=<?php echo $related['id']; ?>" class="btn btn-sm">查看详情</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 标签页切换
            const tabs = document.querySelectorAll('.project-tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const target = this.getAttribute('data-tab');
                    
                    // 切换标签页激活状态
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // 切换内容区域
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === target) {
                            content.classList.add('active');
                        }
                    });
                });
            });
            
            // 微信分享弹窗
            const wechatShare = document.getElementById('wechat-share');
            if (wechatShare) {
                wechatShare.addEventListener('click', function() {
                    alert('请打开微信，使用"扫一扫"功能，扫描网页中的二维码来分享本项目。');
                });
            }
            
            // 表单验证
            const registerForm = document.querySelector('.register-form form');
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    const name = document.getElementById('name');
                    const phone = document.getElementById('phone');
                    const email = document.getElementById('email');
                    let isValid = true;
                    
                    if (!name.value.trim()) {
                        alert('请输入姓名');
                        isValid = false;
                    }
                    
                    if (!phone.value.trim() || !/^1[3-9]\d{9}$/.test(phone.value)) {
                        alert('请输入有效的手机号码');
                        isValid = false;
                    }
                    
                    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                        alert('请输入有效的邮箱地址');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html> 