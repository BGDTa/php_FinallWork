<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查是否已登录，未登录则重定向到登录页
if (!is_logged_in()) {
    header('Location: ../user/login.php');
    exit;
}

// 检查是否有权限访问后台（只有管理员和机构可以访问）
if (!is_admin() && !is_organization()) {
    header('Location: ../user/dashboard.php');
    exit;
}

// 获取用户信息
$user_id = $_SESSION['user_id'];
$user = get_user_info($user_id);

if (!$user) {
    // 用户不存在，可能是会话已过期但Cookie仍存在
    header('Location: ../user/logout.php');
    exit;
}

// 根据角色获取不同的统计数据
$stats = [];

if (is_admin()) {
    // 管理员可以看到所有统计数据
    $sql = "SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'volunteer' AND status = '已审核') AS volunteer_count,
            (SELECT COUNT(*) FROM users WHERE role = 'organization' AND status = '已审核') AS org_count,
            (SELECT COUNT(*) FROM users WHERE status = '待审核') AS pending_users,
            (SELECT COUNT(*) FROM projects) AS total_projects,
            (SELECT COUNT(*) FROM projects WHERE status = '待审核') AS pending_projects,
            (SELECT COUNT(*) FROM stories WHERE status = '待审核') AS pending_stories";
} else {
    // 机构用户只能看到与自己相关的统计数据
    $sql = "SELECT 
            (SELECT COUNT(*) FROM projects WHERE organization_id = ?) AS total_projects,
            (SELECT COUNT(*) FROM projects WHERE organization_id = ? AND status = '待审核') AS pending_projects";
}

$stmt = $conn->prepare($sql);
if (is_organization()) {
    $stmt->bind_param("ii", $user_id, $user_id);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// 获取最近的项目（管理员可见所有项目，机构只能看到自己的项目）
$recent_projects = [];
if (is_admin()) {
    $sql = "SELECT p.*, u.organization_name FROM projects p 
            JOIN users u ON p.organization_id = u.id 
            ORDER BY p.created_at DESC LIMIT 5";
    $result = $conn->query($sql);
} else {
    $sql = "SELECT p.*, u.organization_name FROM projects p 
            JOIN users u ON p.organization_id = u.id 
            WHERE p.organization_id = ? 
            ORDER BY p.created_at DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

while ($row = $result->fetch_assoc()) {
    $recent_projects[] = $row;
}

// 获取待审核的内容
$pending_items = [];
if (is_admin()) {
    // 管理员看到所有待审核内容
    $sql = "SELECT 'user' as type, id, username as title, created_at FROM users WHERE status = '待审核' 
            UNION 
            SELECT 'project' as type, id, title, created_at FROM projects WHERE status = '待审核'
            ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $pending_items[] = $row;
    }
} else {
    // 机构只看到与自己相关的待审核内容
    $sql = "SELECT 'project' as type, id, title, created_at FROM projects 
            WHERE organization_id = ? AND status = '待审核'
            ORDER BY created_at DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_items[] = $row;
    }
}

$page_title = "后台管理 - 爱心联萌";
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
        .admin-container {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        
        .admin-sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            padding: 20px 0;
        }
        
        .admin-content {
            flex: 1;
            padding: 20px;
            background-color: #f8f9fa;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .admin-logo {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 0 20px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        .admin-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .admin-menu li a {
            display: block;
            padding: 10px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .admin-menu li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .admin-menu li a.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .admin-menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .admin-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .admin-card-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-card-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .admin-card-body {
            padding: 20px;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .admin-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .admin-table tr:last-child td {
            border-bottom: none;
        }
        
        .admin-table tr:hover td {
            background-color: #f8f9fa;
        }
        
        .admin-actions {
            display: flex;
            gap: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .badge-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <!-- 后台管理界面结构 -->
    <div class="admin-container">
        <!-- 侧边栏菜单 -->
        <div class="admin-sidebar">
            <div class="admin-logo">
                爱心联萌管理
            </div>
            <ul class="admin-menu">
                <li><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> 控制面板</a></li>
                <?php if (is_admin()): ?>
                    <li><a href="users.php"><i class="fas fa-users"></i> 用户管理</a></li>
                <?php endif; ?>
                <li><a href="projects.php"><i class="fas fa-project-diagram"></i> 项目管理</a></li>
                <?php if (is_admin()): ?>
                    <li><a href="stories.php"><i class="fas fa-book-open"></i> 故事审核</a></li>
                <?php endif; ?>
                <li><a href="../user/dashboard.php"><i class="fas fa-user"></i> 用户中心</a></li>
                <li><a href="../user/logout.php"><i class="fas fa-sign-out-alt"></i> 退出登录</a></li>
            </ul>
        </div>
        
        <!-- 主要内容 -->
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <h2>控制面板</h2>
                    <p>欢迎，<?php echo htmlspecialchars($user['username']); ?> (<?php echo is_admin() ? '管理员' : '机构用户'; ?>)</p>
                </div>
                <div>
                    当前时间：<?php echo date('Y-m-d H:i'); ?>
                </div>
            </div>
            
            <!-- 统计数据 -->
            <div class="stats-grid">
                <?php if (is_admin()): ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['volunteer_count']; ?></div>
                        <div class="stat-label">注册志愿者</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['org_count']; ?></div>
                        <div class="stat-label">注册机构</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['pending_users']; ?></div>
                        <div class="stat-label">待审核用户</div>
                    </div>
                <?php endif; ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_projects']; ?></div>
                    <div class="stat-label">总项目数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pending_projects']; ?></div>
                    <div class="stat-label">待审核项目</div>
                </div>
                <?php if (is_admin()): ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['pending_stories']; ?></div>
                        <div class="stat-label">待审核故事</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 待审核内容 -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">待审核内容</h3>
                    <?php if (is_admin()): ?>
                        <div class="admin-actions">
                            <a href="users.php?status=待审核" class="btn btn-sm">待审核用户</a>
                            <a href="projects.php?status=待审核" class="btn btn-sm">待审核项目</a>
                            <a href="stories.php?status=待审核" class="btn btn-sm">待审核故事</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="admin-card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>类型</th>
                                <th>标题</th>
                                <th>提交时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_items)): ?>
                                <tr>
                                    <td colspan="4">暂无待审核内容</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php
                                                switch ($item['type']) {
                                                    case 'user': echo '用户'; break;
                                                    case 'project': echo '项目'; break;
                                                    default: echo '其他';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <div class="admin-actions">
                                                <?php if ($item['type'] == 'user'): ?>
                                                    <a href="users.php?status=待审核" class="btn btn-sm">查看</a>
                                                <?php elseif ($item['type'] == 'project'): ?>
                                                    <a href="projects.php?status=待审核" class="btn btn-sm">查看</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 最近项目 -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">最近项目</h3>
                    <a href="projects.php" class="btn btn-sm">查看全部</a>
                </div>
                <div class="admin-card-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>项目名称</th>
                                <th>机构</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_projects)): ?>
                                <tr>
                                    <td colspan="4">暂无项目</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['organization_name']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                switch ($project['status']) {
                                                    case '待审核': echo 'badge-warning'; break;
                                                    case '招募中': echo 'badge-primary'; break;
                                                    case '进行中': echo 'badge-success'; break;
                                                    case '已结束': echo 'badge-danger'; break;
                                                    default: echo '';
                                                }
                                            ?>">
                                                <?php echo $project['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="projects.php" class="btn btn-sm">查看</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html> 