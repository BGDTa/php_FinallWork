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

// 获取用户参与的项目
$sql = "SELECT p.*, r.status as registration_status, r.created_at as registration_date 
        FROM projects p 
        JOIN registrations r ON p.id = r.project_id 
        WHERE r.user_id = ? 
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$projects_result = $stmt->get_result();

// 获取用户的积分记录
$sql = "SELECT * FROM point_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$points_result = $stmt->get_result();

// 获取用户发布的故事
$sql = "SELECT * FROM stories WHERE user_id = ? ORDER BY created_at DESC LIMIT 3";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stories_result = $stmt->get_result();

// 获取用户打卡记录
$sql = "SELECT * FROM checkins WHERE user_id = ? ORDER BY checkin_date DESC LIMIT 7";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$checkins_result = $stmt->get_result();

// 处理每日打卡
if (isset($_POST['checkin'])) {
    $today = date('Y-m-d');
    
    // 检查今日是否已打卡
    $sql = "SELECT id FROM checkins WHERE user_id = ? AND checkin_date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // 未打卡，创建新记录
        $sql = "INSERT INTO checkins (user_id, checkin_date) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $today);
        
        if ($stmt->execute()) {
            // 打卡成功，增加积分
            update_user_points($user_id, 5);
            set_message('打卡成功！获得5点爱心值。', 'success');
        } else {
            set_message('打卡失败，请稍后再试。', 'error');
        }
    } else {
        set_message('您今天已经打卡了。', 'info');
    }
    
    // 重定向避免表单重复提交
    header('Location: dashboard.php');
    exit;
}

$page_title = "个人中心 - 爱心联萌";

// 今天是否已打卡
$today = date('Y-m-d');
$sql = "SELECT id FROM checkins WHERE user_id = ? AND checkin_date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$already_checked_in = ($stmt->get_result()->num_rows > 0);

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
        .dashboard-container {
            max-width: 1200px;
            margin: 40px auto;
        }
        
        .dashboard-header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 30px;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-info h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .user-info p {
            color: var(--gray-color);
            margin-bottom: 5px;
        }
        
        .user-stats {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }
        
        .user-stats div {
            text-align: center;
        }
        
        .user-stats .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .user-stats .stat-label {
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .dashboard-card {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .dashboard-card-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .dashboard-card-actions a {
            font-size: 0.9rem;
            color: var(--primary-color);
        }
        
        .project-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .project-item:last-child {
            border-bottom: none;
        }
        
        .project-image {
            width: 80px;
            height: 80px;
            border-radius: 5px;
            object-fit: cover;
        }
        
        .project-content {
            flex: 1;
        }
        
        .project-content h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .project-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .project-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-waiting {
            background-color: #ffeaa7;
            color: #fdcb6e;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #28a745;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #dc3545;
        }
        
        .status-completed {
            background-color: #e2e3e5;
            color: #495057;
        }
        
        .badge-points {
            display: inline-block;
            padding: 2px 8px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .point-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .point-item:last-child {
            border-bottom: none;
        }
        
        .point-action {
            font-weight: bold;
        }
        
        .point-date {
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        .checkin-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }
        
        .calendar-day {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 50px;
            border-radius: 5px;
            background-color: #f8f9fa;
            font-size: 0.9rem;
        }
        
        .calendar-day.checked {
            background-color: #d4edda;
            color: #28a745;
        }
        
        .day-number {
            font-weight: bold;
        }
        
        .no-data {
            padding: 20px;
            text-align: center;
            color: var(--gray-color);
        }
        
        .story-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray-color);
        }
        
        .story-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .story-item h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .story-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: var(--gray-color);
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container dashboard-container">
        <?php echo display_message(); ?>
        
        <!-- 用户个人信息 -->
        <div class="dashboard-header">
            <img src="<?php echo !empty($user['avatar']) ? $user['avatar'] : 'https://via.placeholder.com/100x100'; ?>" alt="用户头像" class="user-avatar">
            <div class="user-info">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                <p><?php echo !empty($user['real_name']) ? htmlspecialchars($user['real_name']) : '未设置真实姓名'; ?></p>
                <div class="user-stats">
                    <div>
                        <div class="stat-value"><?php echo number_format($user['points']); ?></div>
                        <div class="stat-label">爱心值</div>
                    </div>
                    <div>
                        <div class="stat-value">
                            <?php 
                            $sql = "SELECT COUNT(*) as count FROM registrations WHERE user_id = ? AND status = '已完成'";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            echo $result->fetch_assoc()['count'];
                            ?>
                        </div>
                        <div class="stat-label">参与项目</div>
                    </div>
                    <div>
                        <div class="stat-value">
                            <?php 
                            $sql = "SELECT COUNT(*) as count FROM stories WHERE user_id = ? AND status = '已审核'";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            echo $result->fetch_assoc()['count'];
                            ?>
                        </div>
                        <div class="stat-label">发布故事</div>
                    </div>
                </div>
            </div>
            <div class="actions">
                <a href="profile.php" class="btn btn-primary">编辑资料</a>
                <?php if (!$already_checked_in): ?>
                    <form method="post" action="">
                        <button type="submit" name="checkin" class="btn btn-secondary">每日打卡</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>今日已打卡</button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-main">
                <!-- 我的项目 -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div class="dashboard-card-title">我参与的项目</div>
                        <div class="dashboard-card-actions">
                            <a href="../project/list.php">查看更多 <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if ($projects_result->num_rows > 0): ?>
                            <?php while ($project = $projects_result->fetch_assoc()): ?>
                                <div class="project-item">
                                    <img src="<?php echo !empty($project['cover_image']) ? $project['cover_image'] : 'https://source.unsplash.com/random/80x80/?charity'; ?>" alt="<?php echo $project['title']; ?>" class="project-image">
                                    <div class="project-content">
                                        <h3><a href="../project/detail.php?id=<?php echo $project['id']; ?>"><?php echo $project['title']; ?></a></h3>
                                        <div class="project-meta">
                                            <span><i class="fas fa-map-marker-alt"></i> <?php echo $project['location']; ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($project['start_date'])); ?></span>
                                        </div>
                                        <div>
                                            <span class="project-status <?php 
                                                switch ($project['registration_status']) {
                                                    case '待审核': echo 'status-waiting'; break;
                                                    case '已通过': echo 'status-approved'; break;
                                                    case '已拒绝': echo 'status-rejected'; break;
                                                    case '已完成': echo 'status-completed'; break;
                                                    default: echo '';
                                                }
                                            ?>">
                                                <?php echo $project['registration_status']; ?>
                                            </span>
                                            <span>报名时间: <?php echo date('Y-m-d', strtotime($project['registration_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <p>您还没有参与任何项目</p>
                                <a href="../project/list.php" class="btn btn-outline">浏览项目</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 我的故事 -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div class="dashboard-card-title">我的志愿故事</div>
                        <div class="dashboard-card-actions">
                            <a href="stories.php">管理故事</a> | 
                            <a href="add_story.php">发布故事</a>
                        </div>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if ($stories_result->num_rows > 0): ?>
                            <?php while ($story = $stories_result->fetch_assoc()): ?>
                                <div class="story-item">
                                    <h3><a href="../story/detail.php?id=<?php echo $story['id']; ?>"><?php echo $story['title']; ?></a></h3>
                                    <div class="story-meta">
                                        <span><i class="fas fa-clock"></i> <?php echo date('Y-m-d', strtotime($story['created_at'])); ?></span>
                                        <span><i class="fas fa-eye"></i> <?php echo $story['views']; ?> 浏览</span>
                                        <span><i class="fas fa-heart"></i> <?php echo $story['likes']; ?> 点赞</span>
                                        <span class="project-status <?php 
                                            switch ($story['status']) {
                                                case '待审核': echo 'status-waiting'; break;
                                                case '已审核': echo 'status-approved'; break;
                                                case '已拒绝': echo 'status-rejected'; break;
                                                default: echo '';
                                            }
                                        ?>">
                                            <?php echo $story['status']; ?>
                                        </span>
                                    </div>
                                    <p><?php echo mb_substr(strip_tags($story['content']), 0, 100, 'UTF-8') . '...'; ?></p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <p>您还没有发布任何志愿故事</p>
                                <a href="add_story.php" class="btn btn-outline">发布故事</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-sidebar">
                <!-- 爱心值记录 -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div class="dashboard-card-title">爱心值记录</div>
                        <div class="dashboard-card-actions">
                            <a href="points.php">查看全部</a>
                        </div>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if ($points_result->num_rows > 0): ?>
                            <?php while ($point = $points_result->fetch_assoc()): ?>
                                <div class="point-item">
                                    <div>
                                        <div class="point-action"><?php echo $point['action']; ?></div>
                                        <div class="point-date"><?php echo date('Y-m-d', strtotime($point['created_at'])); ?></div>
                                    </div>
                                    <div class="badge-points <?php echo $point['points'] > 0 ? 'positive' : 'negative'; ?>">
                                        <?php echo $point['points'] > 0 ? '+'.$point['points'] : $point['points']; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data">暂无爱心值记录</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 打卡日历 -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div class="dashboard-card-title">公益打卡</div>
                        <div class="dashboard-card-actions">
                            <?php if (!$already_checked_in): ?>
                                <form method="post" action="">
                                    <button type="submit" name="checkin" class="btn btn-sm">今日打卡</button>
                                </form>
                            <?php else: ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i> 已打卡</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dashboard-card-body">
                        <div id="checkin-calendar"></div>
                        
                        <div class="checkin-stats" style="margin-top: 15px; text-align: center;">
                            <p>本月累计打卡: 
                                <?php 
                                $month_start = date('Y-m-01');
                                $sql = "SELECT COUNT(*) as count FROM checkins WHERE user_id = ? AND checkin_date >= ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("is", $user_id, $month_start);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                echo '<strong>' . $result->fetch_assoc()['count'] . '</strong>';
                                ?> 天
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- 推荐项目 -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div class="dashboard-card-title">推荐项目</div>
                        <div class="dashboard-card-actions">
                            <a href="../project/list.php">更多项目</a>
                        </div>
                    </div>
                    <div class="dashboard-card-body">
                        <?php
                        $sql = "SELECT * FROM projects WHERE status = '招募中' ORDER BY RAND() LIMIT 2";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0):
                            while ($project = $result->fetch_assoc()):
                        ?>
                            <div class="project-item">
                                <img src="<?php echo !empty($project['cover_image']) ? $project['cover_image'] : 'https://source.unsplash.com/random/80x80/?charity'; ?>" alt="<?php echo $project['title']; ?>" class="project-image">
                                <div class="project-content">
                                    <h3><a href="../project/detail.php?id=<?php echo $project['id']; ?>"><?php echo $project['title']; ?></a></h3>
                                    <div class="project-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo $project['location']; ?></span>
                                    </div>
                                    <div>
                                        <span class="project-status status-approved"><?php echo $project['status']; ?></span>
                                        <a href="../project/detail.php?id=<?php echo $project['id']; ?>" class="btn btn-sm">查看详情</a>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <div class="no-data">暂无推荐项目</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 