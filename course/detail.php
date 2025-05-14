<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 获取课程ID
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 检查课程ID是否有效
if ($course_id <= 0) {
    set_message('无效的课程ID', 'error');
    header('Location: index.php');
    exit;
}

// 获取课程信息
$sql = "SELECT c.*, u.username as author_name, u.organization_name, u.avatar as author_avatar 
        FROM courses c 
        LEFT JOIN users u ON c.author_id = u.id 
        WHERE c.id = ? AND c.status = '已发布'";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    set_message('系统错误，请稍后再试', 'error');
    header('Location: index.php');
    exit;
}
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    set_message('课程不存在或已下线', 'error');
    header('Location: index.php');
    exit;
}

$course = $result->fetch_assoc();

// 更新浏览次数
$sql = "UPDATE courses SET views = views + 1 WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("MySQL prepare error (更新浏览次数): " . $conn->error);
} else {
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
}

// 记录学习进度（如果用户已登录）
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    
    // 检查是否已有学习记录
    $sql = "SELECT * FROM course_progress WHERE user_id = ? AND course_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("MySQL prepare error (查询学习记录): " . $conn->error);
    } else {
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $progress_result = $stmt->get_result();
        
        if ($progress_result->num_rows == 0) {
            // 创建新的学习记录
            $sql = "INSERT INTO course_progress (user_id, course_id, last_position, status, started_at) 
                    VALUES (?, ?, 0, '进行中', NOW())";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("MySQL prepare error (创建学习记录): " . $conn->error);
            } else {
                $stmt->bind_param("ii", $user_id, $course_id);
                $stmt->execute();
            }
        } else {
            // 更新最后访问时间
            $sql = "UPDATE course_progress SET last_accessed_at = NOW() WHERE user_id = ? AND course_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("MySQL prepare error (更新访问时间): " . $conn->error);
            } else {
                $stmt->bind_param("ii", $user_id, $course_id);
                $stmt->execute();
            }
        }
    }
}

// 获取课程章节
$sql = "SELECT * FROM course_chapters WHERE course_id = ? ORDER BY display_order";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("MySQL prepare error (获取课程章节): " . $conn->error);
    $chapters_result = false;
} else {
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $chapters_result = $stmt->get_result();
}

// 获取课程相关资源
$sql = "SELECT * FROM media WHERE course_id = ? ORDER BY id";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("MySQL prepare error (获取课程资源): " . $conn->error);
    $resources_result = false;
} else {
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $resources_result = $stmt->get_result();
}

// 获取相关课程
$sql = "SELECT * FROM courses 
        WHERE id != ? AND status = '已发布' AND 
        (MATCH(title, description) AGAINST(?) OR author_id = ?) 
        LIMIT 3";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("MySQL prepare error (相关课程全文搜索): " . $conn->error);
    // 可能是没有FULLTEXT索引，使用LIKE替代
    $sql = "SELECT * FROM courses 
            WHERE id != ? AND status = '已发布' AND 
            (title LIKE ? OR author_id = ?) 
            LIMIT 3";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        error_log("MySQL prepare error (相关课程LIKE搜索): " . $conn->error);
        $related_courses = false;
    } else {
        $search_param = "%" . $conn->real_escape_string($course['title']) . "%";
        $stmt->bind_param("isi", $course_id, $search_param, $course['author_id']);
        $stmt->execute();
        $related_courses = $stmt->get_result();
    }
} else {
    $content_param = $course['title'];
    $stmt->bind_param("isi", $course_id, $content_param, $course['author_id']);
    $stmt->execute();
    $related_courses = $stmt->get_result();
}

// 处理完成课程
if (isset($_POST['complete_course']) && is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    
    // 更新学习进度
    $sql = "UPDATE course_progress SET status = '已完成', completed_at = NOW() 
            WHERE user_id = ? AND course_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        die('准备语句失败: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $user_id, $course_id);
    
    if ($stmt->execute()) {
        // 给用户增加爱心值
        update_user_points($user_id, 15);
        
        set_message('恭喜您完成课程学习！获得15点爱心值。', 'success');
        header('Location: detail.php?id=' . $course_id);
        exit;
    }
}

$page_title = $course['title'] . " - 爱心联萌";
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
        .course-detail-container {
            max-width: 1200px;
            margin: 40px auto;
        }
        
        .course-detail-grid {
            display: grid;
            grid-template-columns: 7fr 3fr;
            gap: 30px;
        }
        
        .course-main {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .course-header {
            padding: 30px;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .course-title {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .course-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            color: var(--gray-color);
        }
        
        .course-meta-item {
            display: flex;
            align-items: center;
        }
        
        .course-meta-item i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        .course-author {
            display: flex;
            align-items: center;
            margin-top: 20px;
        }
        
        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .author-info h4 {
            margin-bottom: 5px;
        }
        
        .author-info p {
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .course-video {
            position: relative;
            padding-bottom: 56.25%; /* 16:9比例 */
            height: 0;
            overflow: hidden;
        }
        
        .course-video iframe, 
        .course-video video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .course-tabs {
            display: flex;
            padding: 0 30px;
            margin-top: 20px;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .course-tab {
            padding: 15px 20px;
            cursor: pointer;
            position: relative;
            margin-right: 10px;
        }
        
        .course-tab.active {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .course-tab.active::after {
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
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .course-description {
            line-height: 1.8;
            margin-bottom: 20px;
        }
        
        .chapter-list {
            margin-top: 20px;
        }
        
        .chapter-item {
            border: 1px solid var(--light-gray-color);
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }
        
        .chapter-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }
        
        .chapter-header i {
            transition: var(--transition);
        }
        
        .chapter-header.active i {
            transform: rotate(180deg);
        }
        
        .chapter-content {
            padding: 15px 20px;
            display: none;
        }
        
        .chapter-content.show {
            display: block;
        }
        
        .resources-list {
            list-style: none;
        }
        
        .resources-list li {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
        }
        
        .resources-list li a {
            display: flex;
            align-items: center;
        }
        
        .resources-list li i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .course-sidebar {
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
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: var(--light-gray-color);
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--primary-color);
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .learning-actions {
            margin-top: 20px;
        }
        
        .btn-complete {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            background-color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .learning-tips {
            background-color: #e9f7ef;
            border-radius: var(--border-radius);
            padding: 15px;
        }
        
        .learning-tips h4 {
            margin-bottom: 10px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .learning-tips h4 i {
            margin-right: 10px;
        }
        
        .learning-tips ul {
            margin-left: 20px;
        }
        
        .learning-tips li {
            margin-bottom: 5px;
            color: var(--gray-color);
        }
        
        .related-courses {
            margin-top: 40px;
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
        
        .related-course {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .related-course:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .related-image {
            height: 150px;
        }
        
        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-content {
            padding: 15px;
        }
        
        .related-title {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .related-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        @media (max-width: 991px) {
            .course-detail-grid {
                grid-template-columns: 1fr;
            }
            
            .course-sidebar {
                position: static;
                margin-top: 0;
            }
            
            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .related-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .course-sidebar-section {
            margin-top: 30px;
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .course-sidebar-section h3 {
            margin-bottom: 20px;
            font-size: 1.2rem;
        }
        
        .related-course-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .related-course-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .related-course-item img {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-right: 15px;
        }
        
        .related-course-info {
            flex: 1;
        }
        
        .related-course-info h4 {
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .related-course-info span {
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        /* 登录状态下的特殊样式修复 */
        <?php if (is_logged_in()): ?>
        .course-sidebar {
            display: block !important;
            visibility: visible !important;
        }
        
        /* 确保侧边栏内部元素正常显示 */
        .sidebar-card, 
        .progress-bar, 
        .progress-fill, 
        .progress-text, 
        .learning-actions,
        .learning-tips,
        .share-buttons {
            display: block !important;
        }
        
        /* 确保标签页切换正常 */
        .course-tab {
            pointer-events: auto !important;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container course-detail-container">
        <?php echo display_message(); ?>
        
        <div class="course-detail-grid">
            <div class="course-main">
                <!-- 课程头部信息 -->
                <div class="course-header">
                    <h1 class="course-title"><?php echo $course['title']; ?></h1>
                    <div class="course-meta">
                        <div class="course-meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $course['duration']; ?> 分钟</span>
                        </div>
                        <div class="course-meta-item">
                            <i class="fas fa-eye"></i>
                            <span><?php echo $course['views']; ?> 次学习</span>
                        </div>
                        <div class="course-meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>发布时间: <?php echo date('Y-m-d', strtotime($course['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="course-author">
                        <img src="<?php echo !empty($course['author_avatar']) ? $course['author_avatar'] : 'https://via.placeholder.com/50'; ?>" alt="作者头像" class="author-avatar">
                        <div class="author-info">
                            <h4><?php echo !empty($course['organization_name']) ? $course['organization_name'] : $course['author_name']; ?></h4>
                            <p>课程作者</p>
                        </div>
                    </div>
                </div>
                
                <!-- 课程视频 -->
                <div class="course-video">
                    <?php if (!empty($course['video_url'])): ?>
                        <iframe src="<?php echo $course['video_url']; ?>" frameborder="0" allowfullscreen></iframe>
                    <?php else: ?>
                        <img src="<?php echo !empty($course['cover_image']) ? $course['cover_image'] : 'https://source.unsplash.com/random/800x450/?education'; ?>" alt="<?php echo $course['title']; ?>">
                    <?php endif; ?>
                </div>
                
                <!-- 课程标签页 -->
                <div class="course-tabs">
                    <div class="course-tab active" data-tab="description">课程介绍</div>
                    <div class="course-tab" data-tab="chapters">课程大纲</div>
                    <div class="course-tab" data-tab="resources">课程资源</div>
                    <div class="course-tab" data-tab="notes">学习笔记</div>
                </div>
                
                <!-- 课程介绍 -->
                <div id="description" class="tab-content active">
                    <div class="course-description">
                        <?php echo nl2br($course['description']); ?>
                    </div>
                    
                    <div class="course-target">
                        <h3>学习目标</h3>
                        <ul>
                            <li>了解志愿服务的基本概念和意义</li>
                            <li>掌握参与志愿服务的方法和技巧</li>
                            <li>学习如何更好地开展公益项目</li>
                            <li>提升志愿服务专业能力和水平</li>
                        </ul>
                    </div>
                    
                    <div class="course-suitable">
                        <h3>适合人群</h3>
                        <ul>
                            <li>有志于参与公益活动的志愿者</li>
                            <li>希望提升自身专业能力的志愿者</li>
                            <li>公益组织工作人员</li>
                            <li>对志愿服务感兴趣的社会各界人士</li>
                        </ul>
                    </div>
                </div>
                
                <!-- 课程大纲 -->
                <div id="chapters" class="tab-content">
                    <?php if ($chapters_result && $chapters_result->num_rows > 0): ?>
                        <div class="chapter-list">
                            <?php while ($chapter = $chapters_result->fetch_assoc()): ?>
                                <div class="chapter-item">
                                    <div class="chapter-header">
                                        <span><?php echo $chapter['title']; ?></span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="chapter-content">
                                        <?php echo nl2br($chapter['content']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p>本课程暂无章节大纲信息。</p>
                    <?php endif; ?>
                </div>
                
                <!-- 课程资源 -->
                <div id="resources" class="tab-content">
                    <?php if ($resources_result && $resources_result->num_rows > 0): ?>
                        <ul class="resources-list">
                            <?php while ($resource = $resources_result->fetch_assoc()): ?>
                                <li>
                                    <a href="<?php echo $resource['file_path']; ?>" target="_blank">
                                        <?php if ($resource['type'] == 'document'): ?>
                                            <i class="fas fa-file-pdf"></i>
                                        <?php elseif ($resource['type'] == 'audio'): ?>
                                            <i class="fas fa-file-audio"></i>
                                        <?php elseif ($resource['type'] == 'video'): ?>
                                            <i class="fas fa-file-video"></i>
                                        <?php else: ?>
                                            <i class="fas fa-file"></i>
                                        <?php endif; ?>
                                        <?php echo $resource['title']; ?>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>本课程暂无额外资源。</p>
                    <?php endif; ?>
                </div>
                
                <!-- 学习笔记 -->
                <div id="notes" class="tab-content">
                    <?php if (is_logged_in()): ?>
                        <div class="notes-editor">
                            <h3>我的学习笔记</h3>
                            <form action="../ajax/save_notes.php" method="post">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <textarea name="notes" class="form-control" rows="10" placeholder="在这里记录您的学习心得和笔记..."><?php 
                                    $sql = "SELECT notes FROM course_progress WHERE user_id = ? AND course_id = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("ii", $_SESSION['user_id'], $course_id);
                                    $stmt->execute();
                                    $notes_result = $stmt->get_result();
                                    if ($notes_result->num_rows > 0) {
                                        echo $notes_result->fetch_assoc()['notes'];
                                    }
                                ?></textarea>
                                <button type="submit" class="btn btn-primary" style="margin-top: 15px;">保存笔记</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="login-prompt">
                            <p>请<a href="../user/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">登录</a>后记录学习笔记</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="course-sidebar">
                <!-- 学习进度 -->
                <div class="sidebar-card">
                    <h3 class="sidebar-card-title">学习进度</h3>
                    <?php if (is_logged_in()): ?>
                        <?php
                        $progress_percent = 0;
                        $status = '未开始';
                        $progress = null;
                        
                        try {
                            $sql = "SELECT * FROM course_progress WHERE user_id = ? AND course_id = ?";
                            $stmt = $conn->prepare($sql);
                            if ($stmt) {
                                $stmt->bind_param("ii", $_SESSION['user_id'], $course_id);
                                $stmt->execute();
                                $progress_result = $stmt->get_result();
                                if ($progress_result && $progress_result->num_rows > 0) {
                                    $progress = $progress_result->fetch_assoc();
                                }
                            }
                            
                            if ($progress) {
                                if ($progress['status'] == '已完成') {
                                    $progress_percent = 100;
                                    $status = '已完成';
                                } else {
                                    $progress_percent = 30; // 默认值，实际应该根据学习时间、章节完成情况等计算
                                    $status = '学习中';
                                }
                            }
                        } catch (Exception $e) {
                            error_log("课程进度查询失败: " . $e->getMessage());
                        }
                        ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                        </div>
                        <div class="progress-text">
                            <span>进度: <?php echo $progress_percent; ?>%</span>
                            <span>状态: <?php echo $status; ?></span>
                        </div>
                        
                        <div class="learning-actions">
                            <?php if (!$progress || $progress['status'] != '已完成'): ?>
                                <form method="post" action="">
                                    <button type="submit" name="complete_course" class="btn btn-complete">标记为已完成</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-complete" disabled>已完成课程</button>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-outline" style="width: 100%;">返回课程列表</a>
                        </div>
                    <?php else: ?>
                        <div class="login-prompt">
                            <p>请先登录以记录您的学习进度</p>
                            <a href="../user/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary" style="width: 100%;">立即登录</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 学习小贴士 -->
                <div class="sidebar-card">
                    <div class="learning-tips">
                        <h4><i class="fas fa-lightbulb"></i> 学习小贴士</h4>
                        <ul>
                            <li>专注学习，选择安静的环境</li>
                            <li>做好笔记，记录重点内容</li>
                            <li>实践应用，理论结合实际</li>
                            <li>分享知识，教学相长提高效果</li>
                        </ul>
                    </div>
                </div>
                
                <!-- 分享课程 -->
                <div class="sidebar-card">
                    <h3 class="sidebar-card-title">分享课程</h3>
                    <div class="share-buttons">
                        <a href="javascript:;" class="btn btn-sm btn-outline" onclick="window.open('https://service.weibo.com/share/share.php?url=' + encodeURIComponent(window.location.href) + '&title=' + encodeURIComponent('<?php echo $course['title']; ?> - 爱心联萌公益课堂'), '_blank')"><i class="fab fa-weibo"></i> 微博</a>
                        <a href="javascript:;" class="btn btn-sm btn-outline" onclick="window.open('https://connect.qq.com/widget/shareqq/index.html?url=' + encodeURIComponent(window.location.href) + '&title=' + encodeURIComponent('<?php echo $course['title']; ?> - 爱心联萌公益课堂'), '_blank')"><i class="fab fa-qq"></i> QQ</a>
                        <a href="javascript:;" class="btn btn-sm btn-outline" id="wechat-share"><i class="fab fa-weixin"></i> 微信</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 相关课程 -->
        <div class="course-sidebar-section">
            <h3>相关课程</h3>
            <?php if ($related_courses && $related_courses->num_rows > 0): ?>
                <div class="related-courses">
                    <?php while ($related = $related_courses->fetch_assoc()): ?>
                        <div class="related-course-item">
                            <img src="<?php echo !empty($related['cover_image']) ? $related['cover_image'] : 'https://source.unsplash.com/random/80x60/?education'; ?>" alt="<?php echo $related['title']; ?>">
                            <div class="related-course-info">
                                <h4><a href="detail.php?id=<?php echo $related['id']; ?>"><?php echo $related['title']; ?></a></h4>
                                <span><i class="fas fa-eye"></i> <?php echo $related['views']; ?> 次学习</span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="no-data">暂无相关课程推荐</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // 当DOM加载完成时执行
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM已加载完成');
            
            // 调试信息
            console.log('是否登录:', '<?php echo is_logged_in() ? "是" : "否" ?>');
            
            // 标签页切换
            const tabs = document.querySelectorAll('.course-tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            console.log('找到标签页数量:', tabs.length);
            console.log('找到内容区域数量:', tabContents.length);
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    const target = this.getAttribute('data-tab');
                    console.log('点击标签:', target);
                    
                    // 切换标签页激活状态
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // 切换内容区域
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === target) {
                            content.classList.add('active');
                            console.log('激活内容区域:', target);
                        }
                    });
                });
            });
            
            // 章节折叠展开
            const chapterHeaders = document.querySelectorAll('.chapter-header');
            console.log('找到章节标题数量:', chapterHeaders.length);
            
            chapterHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    console.log('点击章节标题');
                    this.classList.toggle('active');
                    const content = this.nextElementSibling;
                    if (content) {
                        content.classList.toggle('show');
                        console.log('切换章节内容显示');
                    }
                });
            });
            
            // 微信分享弹窗
            const wechatShare = document.getElementById('wechat-share');
            if (wechatShare) {
                wechatShare.addEventListener('click', function() {
                    alert('请打开微信，使用"扫一扫"功能，扫描网页中的二维码来分享本课程。');
                });
            }
            
            // 常见问题诊断
            console.log('body子元素数量:', document.body.children.length);
            console.log('主内容区可见性:', document.querySelector('.course-main') ? 'visible' : 'missing');
            console.log('侧边栏可见性:', document.querySelector('.course-sidebar') ? 'visible' : 'missing');
            
            if (document.querySelector('.course-sidebar')) {
                console.log('侧边栏样式:', 
                    window.getComputedStyle(document.querySelector('.course-sidebar')));
            }
        });
    </script>
    
    <?php if (is_logged_in()): ?>
    <!-- 登录状态专用修复脚本 -->
    <script>
        // 确保在页面完全加载后执行登录状态专用修复
        window.addEventListener('load', function() {
            // 每300ms检查一次侧边栏是否显示，持续10次
            let checks = 0;
            let checkInterval = setInterval(function() {
                checks++;
                
                // 修复侧边栏
                const sidebar = document.querySelector('.course-sidebar');
                if (sidebar) {
                    sidebar.style.display = 'block';
                    sidebar.style.visibility = 'visible';
                    
                    // 获取所有侧边栏卡片
                    const cards = sidebar.querySelectorAll('.sidebar-card');
                    cards.forEach(card => {
                        card.style.display = 'block';
                    });
                    
                    console.log('第' + checks + '次修复侧边栏');
                }
                
                // 确保标签页功能正常
                const tabs = document.querySelectorAll('.course-tab');
                if (tabs.length > 0) {
                    tabs.forEach(tab => {
                        // 直接使用更直接的方式绑定点击事件
                        tab.onclick = function() {
                            const target = this.getAttribute('data-tab');
                            
                            // 激活标签
                            tabs.forEach(t => t.classList.remove('active'));
                            this.classList.add('active');
                            
                            // 激活内容
                            const tabContents = document.querySelectorAll('.tab-content');
                            tabContents.forEach(content => {
                                content.style.display = 'none';
                                content.classList.remove('active');
                                
                                if (content.id === target) {
                                    content.style.display = 'block';
                                    content.classList.add('active');
                                }
                            });
                            
                            return false; // 阻止默认行为
                        };
                    });
                    
                    console.log('第' + checks + '次修复标签页点击');
                }
                
                // 停止检查
                if (checks >= 10) {
                    clearInterval(checkInterval);
                }
            }, 300);
        });
    </script>
    <?php endif; ?>
</body>
</html> 