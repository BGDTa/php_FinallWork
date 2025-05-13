<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 获取故事ID
$story_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 检查故事ID是否有效
if ($story_id <= 0) {
    set_message('无效的故事ID', 'error');
    header('Location: index.php');
    exit;
}

// 获取故事信息
$sql = "SELECT s.*, u.username, u.avatar, u.role, p.title as project_title, p.id as project_id 
        FROM stories s 
        LEFT JOIN users u ON s.user_id = u.id 
        LEFT JOIN projects p ON s.project_id = p.id 
        WHERE s.id = ? AND s.status = '已审核'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $story_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    set_message('故事不存在或已被删除', 'error');
    header('Location: index.php');
    exit;
}

$story = $result->fetch_assoc();

// 更新浏览次数
$sql = "UPDATE stories SET views = views + 1 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $story_id);
$stmt->execute();

// 获取评论列表
$sql = "SELECT c.*, u.username, u.avatar FROM comments c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.story_id = ? AND c.status = '已审核' 
        ORDER BY c.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $story_id);
$stmt->execute();
$comments = $stmt->get_result();

// 获取相关故事
$sql = "SELECT s.*, u.username, u.avatar FROM stories s 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.id != ? AND s.status = '已审核'";
$params = [$story_id];
$types = "i";

// 优先获取相同项目的故事
if ($story['project_id']) {
    $sql .= " AND (s.project_id = ? OR MATCH(s.title, s.content) AGAINST(?))";
    $params[] = $story['project_id'];
    $params[] = $story['title'];
    $types .= "is";
} else {
    $sql .= " AND MATCH(s.title, s.content) AGAINST(?)";
    $params[] = $story['title'];
    $types .= "s";
}

$sql .= " ORDER BY CASE WHEN s.project_id = ? THEN 0 ELSE 1 END, s.created_at DESC LIMIT 3";
$params[] = $story['project_id'] ?: 0;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$related_stories = $stmt->get_result();

// 处理点赞操作
if (isset($_POST['like']) && is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    
    // 检查是否已经点赞
    $sql = "SELECT id FROM story_likes WHERE story_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $story_id, $user_id);
    $stmt->execute();
    $already_liked = ($stmt->get_result()->num_rows > 0);
    
    if ($already_liked) {
        // 取消点赞
        $sql = "DELETE FROM story_likes WHERE story_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $story_id, $user_id);
        
        if ($stmt->execute()) {
            // 更新故事点赞数
            $sql = "UPDATE stories SET likes = likes - 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $story_id);
            $stmt->execute();
        }
    } else {
        // 添加点赞
        $sql = "INSERT INTO story_likes (story_id, user_id, created_at) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $story_id, $user_id);
        
        if ($stmt->execute()) {
            // 更新故事点赞数
            $sql = "UPDATE stories SET likes = likes + 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $story_id);
            $stmt->execute();
            
            // 给作者增加爱心值
            if ($story['user_id'] != $user_id) {
                update_user_points($story['user_id'], 2);
            }
        }
    }
    
    // 重定向避免刷新重复提交
    header('Location: detail.php?id=' . $story_id);
    exit;
}

// 处理评论提交
if (isset($_POST['submit_comment']) && is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $content = sanitize($_POST['content']);
    
    // 验证评论内容
    if (empty($content)) {
        $comment_error = '评论内容不能为空';
    } else {
        // 过滤敏感词
        $content = filter_sensitive_words($content);
        
        // 插入评论
        $sql = "INSERT INTO comments (user_id, story_id, content, status, created_at) 
                VALUES (?, ?, ?, '已审核', NOW())";
        $stmt = $conn->prepare($sql);
        $status = '已审核'; // 自动审核通过
        $stmt->bind_param("iis", $user_id, $story_id, $content);
        
        if ($stmt->execute()) {
            // 给评论者增加爱心值
            update_user_points($user_id, 1);
            
            // 给故事作者增加爱心值（自己评论自己的故事不加分）
            if ($story['user_id'] != $user_id) {
                update_user_points($story['user_id'], 1);
            }
            
            set_message('评论发表成功', 'success');
            header('Location: detail.php?id=' . $story_id . '#comments');
            exit;
        } else {
            $comment_error = '评论发表失败，请稍后再试';
        }
    }
}

// 检查当前用户是否已点赞
$user_liked = false;
if (is_logged_in()) {
    $sql = "SELECT id FROM story_likes WHERE story_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $story_id, $_SESSION['user_id']);
    $stmt->execute();
    $user_liked = ($stmt->get_result()->num_rows > 0);
}

$page_title = $story['title'] . " - 爱心联萌";
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
        .story-detail-container {
            max-width: 1200px;
            margin: 40px auto;
        }
        
        .story-detail-grid {
            display: grid;
            grid-template-columns: 7fr 3fr;
            gap: 30px;
        }
        
        .story-main {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .story-header {
            position: relative;
        }
        
        .story-cover {
            height: 400px;
            position: relative;
        }
        
        .story-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .story-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
            padding: 30px;
            color: white;
        }
        
        .story-title {
            font-size: 2.5rem;
            margin-bottom: 15px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }
        
        .story-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 0.9rem;
        }
        
        .story-author {
            display: flex;
            align-items: center;
        }
        
        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid white;
        }
        
        .story-content {
            padding: 30px;
            line-height: 1.8;
        }
        
        .story-content p {
            margin-bottom: 20px;
        }
        
        .story-content img {
            max-width: 100%;
            height: auto;
            margin: 20px 0;
            border-radius: var(--border-radius);
        }
        
        .story-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            border-top: 1px solid var(--light-gray-color);
        }
        
        .story-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            color: var(--gray-color);
            transition: var(--transition);
        }
        
        .stat-item i {
            margin-right: 8px;
        }
        
        .stat-item.active,
        .stat-item:hover {
            color: var(--accent-color);
        }
        
        .share-buttons {
            display: flex;
            gap: 10px;
        }
        
        .share-buttons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: white;
            transition: var(--transition);
        }
        
        .share-buttons a:hover {
            transform: translateY(-3px);
        }
        
        .share-weibo {
            background-color: #e6162d;
        }
        
        .share-wechat {
            background-color: #07c160;
        }
        
        .share-qq {
            background-color: #12b7f5;
        }
        
        .comments-section {
            padding: 30px;
            border-top: 1px solid var(--light-gray-color);
        }
        
        .comments-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .comments-count {
            font-size: 1rem;
            background-color: var(--light-gray-color);
            color: var(--gray-color);
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .comments-list {
            margin-bottom: 30px;
        }
        
        .comment-item {
            padding: 20px 0;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .comment-item:last-child {
            border-bottom: none;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .comment-user {
            display: flex;
            align-items: center;
        }
        
        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .comment-username {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .comment-date {
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        .comment-text {
            line-height: 1.6;
        }
        
        .comment-form {
            margin-top: 30px;
        }
        
        .comment-form h4 {
            margin-bottom: 15px;
        }
        
        .comment-textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 1px solid var(--light-gray-color);
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
        }
        
        .comment-textarea:focus {
            border-color: var(--accent-color);
            outline: none;
        }
        
        .story-sidebar {
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
        
        .author-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .author-large-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--primary-color);
        }
        
        .author-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .author-role {
            color: var(--gray-color);
            margin-bottom: 15px;
        }
        
        .author-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
            width: 100%;
        }
        
        .author-stat {
            text-align: center;
        }
        
        .author-stat-value {
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        
        .author-stat-label {
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        .project-card-simple {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
        }
        
        .project-card-simple h4 {
            margin-bottom: 10px;
        }
        
        .project-card-simple p {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .related-stories-list {
            margin-top: 15px;
        }
        
        .related-story-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .related-story-item:last-child {
            border-bottom: none;
        }
        
        .related-story-image {
            width: 80px;
            height: 80px;
            border-radius: var(--border-radius);
            object-fit: cover;
        }
        
        .related-story-content {
            flex: 1;
        }
        
        .related-story-title {
            font-size: 1rem;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .related-story-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        @media (max-width: 991px) {
            .story-detail-grid {
                grid-template-columns: 1fr;
            }
            
            .story-sidebar {
                position: static;
                margin-top: 0;
            }
        }
        
        @media (max-width: 768px) {
            .story-cover {
                height: 300px;
            }
            
            .story-overlay {
                padding: 20px;
            }
            
            .story-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container story-detail-container">
        <?php echo display_message(); ?>
        
        <div class="story-detail-grid">
            <div class="story-main">
                <!-- 故事头部 -->
                <div class="story-header">
                    <div class="story-cover">
                        <img src="<?php echo !empty($story['cover_image']) ? $story['cover_image'] : 'https://source.unsplash.com/random/1200x400/?volunteer'; ?>" alt="<?php echo $story['title']; ?>">
                        <div class="story-overlay">
                            <h1 class="story-title"><?php echo $story['title']; ?></h1>
                            <div class="story-meta">
                                <div class="story-author">
                                    <img src="<?php echo !empty($story['avatar']) ? $story['avatar'] : 'https://via.placeholder.com/40'; ?>" alt="<?php echo $story['username']; ?>" class="author-avatar">
                                    <span><?php echo $story['username']; ?></span>
                                </div>
                                <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($story['created_at'])); ?></span>
                                <span><i class="fas fa-eye"></i> <?php echo $story['views']; ?> 浏览</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 故事内容 -->
                <div class="story-content">
                    <?php 
                    if (!empty($story['project_title'])) {
                        echo '<div class="story-project-info">';
                        echo '<p><strong>相关项目：</strong><a href="../project/detail.php?id=' . $story['project_id'] . '">' . $story['project_title'] . '</a></p>';
                        echo '</div>';
                    }
                    ?>
                    
                    <div class="story-text">
                        <?php echo nl2br($story['content']); ?>
                    </div>
                </div>
                
                <!-- 故事互动 -->
                <div class="story-actions">
                    <div class="story-stats">
                        <form method="post" action="" style="display: inline">
                            <button type="submit" name="like" class="stat-item<?php echo $user_liked ? ' active' : ''; ?>" style="background: none; border: none; cursor: pointer; padding: 0;">
                                <i class="<?php echo $user_liked ? 'fas' : 'far'; ?> fa-heart"></i>
                                <span><?php echo $story['likes']; ?></span>
                            </button>
                        </form>
                        <a href="#comments" class="stat-item">
                            <i class="far fa-comment"></i>
                            <span><?php echo $comments->num_rows; ?></span>
                        </a>
                    </div>
                    <div class="share-buttons">
                        <a href="javascript:;" class="share-weibo" onclick="window.open('https://service.weibo.com/share/share.php?url=' + encodeURIComponent(window.location.href) + '&title=' + encodeURIComponent('<?php echo $story['title']; ?> - 爱心联萌'), '_blank')"><i class="fab fa-weibo"></i></a>
                        <a href="javascript:;" class="share-wechat" id="wechat-share"><i class="fab fa-weixin"></i></a>
                        <a href="javascript:;" class="share-qq" onclick="window.open('https://connect.qq.com/widget/shareqq/index.html?url=' + encodeURIComponent(window.location.href) + '&title=' + encodeURIComponent('<?php echo $story['title']; ?> - 爱心联萌'), '_blank')"><i class="fab fa-qq"></i></a>
                    </div>
                </div>
                
                <!-- 评论区 -->
                <div id="comments" class="comments-section">
                    <h3 class="comments-title">
                        评论
                        <span class="comments-count"><?php echo $comments->num_rows; ?></span>
                    </h3>
                    
                    <div class="comments-list">
                        <?php if ($comments->num_rows > 0): ?>
                            <?php while ($comment = $comments->fetch_assoc()): ?>
                                <div class="comment-item">
                                    <div class="comment-header">
                                        <div class="comment-user">
                                            <img src="<?php echo !empty($comment['avatar']) ? $comment['avatar'] : 'https://via.placeholder.com/40'; ?>" alt="<?php echo $comment['username']; ?>" class="comment-avatar">
                                            <div>
                                                <div class="comment-username"><?php echo $comment['username']; ?></div>
                                                <div class="comment-date"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br($comment['content']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-comments">
                                <p>暂无评论，快来发表第一条评论吧！</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 评论表单 -->
                    <?php if (is_logged_in()): ?>
                        <div class="comment-form">
                            <h4>发表评论</h4>
                            <form method="post" action="">
                                <?php if (isset($comment_error)): ?>
                                    <div class="alert alert-error"><?php echo $comment_error; ?></div>
                                <?php endif; ?>
                                <textarea name="content" class="comment-textarea" placeholder="分享您的看法..."></textarea>
                                <button type="submit" name="submit_comment" class="btn btn-primary">提交评论</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="comment-login">
                            <p>请<a href="../user/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] . '#comments'); ?>">登录</a>后发表评论</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="story-sidebar">
                <!-- 作者信息 -->
                <div class="sidebar-card">
                    <div class="author-card">
                        <img src="<?php echo !empty($story['avatar']) ? $story['avatar'] : 'https://via.placeholder.com/100'; ?>" alt="<?php echo $story['username']; ?>" class="author-large-avatar">
                        <h3 class="author-name"><?php echo $story['username']; ?></h3>
                        <div class="author-role">
                            <?php 
                            switch ($story['role']) {
                                case 'volunteer':
                                    echo '志愿者';
                                    break;
                                case 'organization':
                                    echo '公益机构';
                                    break;
                                case 'admin':
                                    echo '管理员';
                                    break;
                                default:
                                    echo '志愿者';
                            }
                            ?>
                        </div>
                        <div class="author-stats">
                            <div class="author-stat">
                                <?php
                                $sql = "SELECT COUNT(*) as count FROM stories WHERE user_id = ? AND status = '已审核'";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $story['user_id']);
                                $stmt->execute();
                                $stories_count = $stmt->get_result()->fetch_assoc()['count'];
                                ?>
                                <div class="author-stat-value"><?php echo $stories_count; ?></div>
                                <div class="author-stat-label">故事</div>
                            </div>
                            <div class="author-stat">
                                <?php
                                $sql = "SELECT SUM(likes) as total FROM stories WHERE user_id = ? AND status = '已审核'";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $story['user_id']);
                                $stmt->execute();
                                $likes_count = $stmt->get_result()->fetch_assoc()['total'] ?: 0;
                                ?>
                                <div class="author-stat-value"><?php echo $likes_count; ?></div>
                                <div class="author-stat-label">获赞</div>
                            </div>
                        </div>
                        <a href="../user/view_profile.php?id=<?php echo $story['user_id']; ?>" class="btn btn-outline">查看主页</a>
                    </div>
                </div>
                
                <!-- 相关项目 -->
                <?php if (!empty($story['project_id'])): ?>
                    <div class="sidebar-card">
                        <h3 class="sidebar-card-title">相关项目</h3>
                        <?php
                        $sql = "SELECT * FROM projects WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $story['project_id']);
                        $stmt->execute();
                        $project_result = $stmt->get_result();
                        
                        if ($project_result->num_rows > 0):
                            $project = $project_result->fetch_assoc();
                        ?>
                            <div class="project-card-simple">
                                <h4><?php echo $project['title']; ?></h4>
                                <p><?php echo mb_substr(strip_tags($project['description']), 0, 100, 'UTF-8') . '...'; ?></p>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo $project['location']; ?></p>
                                <p><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($project['start_date'])); ?></p>
                                <a href="../project/detail.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">查看项目</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- 相关故事 -->
                <div class="sidebar-card">
                    <h3 class="sidebar-card-title">相关故事</h3>
                    <?php if ($related_stories->num_rows > 0): ?>
                        <div class="related-stories-list">
                            <?php while ($related = $related_stories->fetch_assoc()): ?>
                                <div class="related-story-item">
                                    <img src="<?php echo !empty($related['cover_image']) ? $related['cover_image'] : 'https://source.unsplash.com/random/80x80/?volunteer'; ?>" alt="<?php echo $related['title']; ?>" class="related-story-image">
                                    <div class="related-story-content">
                                        <h4 class="related-story-title">
                                            <a href="detail.php?id=<?php echo $related['id']; ?>"><?php echo $related['title']; ?></a>
                                        </h4>
                                        <div class="related-story-meta">
                                            <span><?php echo $related['username']; ?></span>
                                            <span><i class="fas fa-heart"></i> <?php echo $related['likes']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">暂无相关故事</p>
                    <?php endif; ?>
                    <div class="text-center" style="margin-top: 15px;">
                        <a href="index.php" class="btn btn-outline btn-sm">查看更多故事</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 微信分享弹窗
            const wechatShare = document.getElementById('wechat-share');
            if (wechatShare) {
                wechatShare.addEventListener('click', function() {
                    alert('请打开微信，使用"扫一扫"功能，扫描网页中的二维码来分享本故事。');
                });
            }
        });
    </script>
</body>
</html> 