<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查项目状态并更新
check_project_status();

// 分页
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // 每页显示的项目数
$offset = ($page - 1) * $limit;

// 搜索和筛选
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$location = isset($_GET['location']) ? sanitize($_GET['location']) : '';

// 构建查询条件
$conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "(p.title LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($status)) {
    $conditions[] = "p.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($location)) {
    $conditions[] = "p.location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

// 只有当没有明确筛选状态且不是从"所有状态"下拉菜单选择时，才默认只显示招募中和进行中的项目
if (empty($status) && !isset($_GET['status'])) {
    $conditions[] = "p.status IN ('招募中', '进行中')";
}

// 如果明确选择了"所有状态"（可能是空字符串值），则不添加状态条件

// 组合查询条件
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// 计算总记录数
$count_sql = "SELECT COUNT(*) as total FROM projects p $where_clause";
$stmt = $conn->prepare($count_sql);
if (!$stmt) {
    die("预处理语句准备失败: " . $conn->error . " SQL: " . $count_sql);
}
if (!empty($params)) {
    try {
        $stmt->bind_param($types, ...$params);
    } catch (Exception $e) {
        die("绑定参数失败: " . $e->getMessage());
    }
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// 获取项目列表
$sql = "SELECT p.*, u.organization_name FROM projects p 
        LEFT JOIN users u ON p.organization_id = u.id 
        $where_clause
        ORDER BY 
            CASE 
                WHEN p.status = '招募中' THEN 1 
                WHEN p.status = '进行中' THEN 2 
                ELSE 3 
            END, 
            p.created_at DESC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("预处理语句准备失败: " . $conn->error . " SQL: " . $sql);
}

// 修复绑定参数的方式
$types .= "ii";
$params[] = $offset;
$params[] = $limit;

// 直接使用spread操作符绑定参数
try {
    $stmt->bind_param($types, ...$params);
} catch (Exception $e) {
    die("绑定参数失败: " . $e->getMessage());
}
$stmt->execute();
$projects = $stmt->get_result();

// 获取项目地点列表（用于筛选）
$sql = "SELECT DISTINCT location FROM projects ORDER BY location";
$locations = $conn->query($sql);

// 获取热门项目（报名人数最多的3个项目）
$sql = "SELECT p.*, u.organization_name FROM projects p 
        LEFT JOIN users u ON p.organization_id = u.id 
        WHERE p.status = '招募中' 
        ORDER BY p.registered DESC 
        LIMIT 3";
$hot_projects = $conn->query($sql);

$page_title = "公益项目列表 - 爱心联萌";
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
        .projects-banner {
            background-color: var(--primary-color);
            padding: 60px 0;
            color: white;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .projects-banner h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .projects-banner p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 30px;
        }
        
        .filter-bar {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .filter-form .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 0;
        }
        
        .filter-form .form-control {
            padding: 10px 15px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .project-card {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .project-image {
            height: 200px;
            position: relative;
        }
        
        .project-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .project-status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
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
        
        .project-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .project-title {
            margin-bottom: 10px;
            font-size: 1.2rem;
            color: var(--dark-color);
            line-height: 1.4;
        }
        
        .project-org {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .project-org i {
            margin-right: 5px;
        }
        
        .project-description {
            color: var(--text-color);
            margin-bottom: 15px;
            line-height: 1.5;
            flex-grow: 1;
        }
        
        .project-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .project-meta span {
            display: flex;
            align-items: center;
        }
        
        .project-meta i {
            margin-right: 5px;
        }
        
        .project-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .project-quota {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .project-quota-progress {
            width: 100px;
            height: 6px;
            background-color: var(--light-gray-color);
            border-radius: 3px;
            margin: 0 10px;
            overflow: hidden;
        }
        
        .project-quota-bar {
            height: 100%;
            background-color: var(--primary-color);
        }
        
        .hot-projects {
            background-color: #f8f9fa;
            padding: 50px 0;
            margin-bottom: 40px;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .section-title h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .section-title p {
            color: var(--gray-color);
        }
        
        .hot-projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form .form-group {
                width: 100%;
            }
            
            .filter-buttons {
                width: 100%;
                justify-content: space-between;
            }
            
            .hot-projects-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <!-- 页面横幅 -->
    <div class="projects-banner">
        <div class="container">
            <h1>发现公益项目</h1>
            <p>浏览最新公益项目，找到适合您的志愿服务机会，让我们共同为社会贡献爱心</p>
            <?php if (is_logged_in() && is_organization()): ?>
                <a href="../user/add_project.php" class="btn btn-light">发布项目</a>
            <?php elseif (!is_logged_in()): ?>
                <a href="../user/login.php" class="btn btn-light">登录发布项目</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container">
        <!-- 筛选栏 -->
        <div class="filter-bar">
            <form class="filter-form" action="" method="get">
                <div class="form-group">
                    <input type="text" name="search" class="form-control" placeholder="搜索项目..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="">所有状态</option>
                        <option value="招募中" <?php echo $status == '招募中' ? 'selected' : ''; ?>>招募中</option>
                        <option value="进行中" <?php echo $status == '进行中' ? 'selected' : ''; ?>>进行中</option>
                        <option value="已结束" <?php echo $status == '已结束' ? 'selected' : ''; ?>>已结束</option>
                    </select>
                </div>
                <div class="form-group">
                    <select name="location" class="form-control">
                        <option value="">所有地区</option>
                        <?php while ($location_row = $locations->fetch_assoc()): ?>
                            <option value="<?php echo $location_row['location']; ?>" <?php echo $location == $location_row['location'] ? 'selected' : ''; ?>>
                                <?php echo $location_row['location']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">筛选</button>
                    <a href="list.php" class="btn btn-outline">重置</a>
                </div>
            </form>
        </div>
        
        <!-- 项目列表 -->
        <?php if ($projects->num_rows > 0): ?>
            <div class="projects-grid">
                <?php while ($project = $projects->fetch_assoc()): ?>
                    <div class="project-card">
                        <div class="project-image">
                            <img src="<?php echo !empty($project['cover_image']) ? $project['cover_image'] : 'https://source.unsplash.com/random/400x200/?charity'; ?>" alt="<?php echo $project['title']; ?>">
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
                        <div class="project-info">
                            <h3 class="project-title">
                                <a href="detail.php?id=<?php echo $project['id']; ?>"><?php echo $project['title']; ?></a>
                            </h3>
                            <div class="project-org">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($project['organization_name']); ?>
                            </div>
                            <p class="project-description">
                                <?php echo mb_substr(strip_tags($project['description']), 0, 80, 'UTF-8') . '...'; ?>
                            </p>
                            <div class="project-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo $project['location']; ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($project['start_date'])); ?></span>
                            </div>
                            <div class="project-footer">
                                <div class="project-quota">
                                    <span><?php echo $project['registered']; ?>/<?php echo $project['quota']; ?></span>
                                    <div class="project-quota-progress">
                                        <div class="project-quota-bar" style="width: <?php echo ($project['quota'] > 0) ? min(($project['registered'] / $project['quota'] * 100), 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <a href="detail.php?id=<?php echo $project['id']; ?>" class="btn btn-sm">查看详情</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <?php 
                    $query_string = http_build_query(array_filter([
                        'search' => $search,
                        'status' => $status,
                        'location' => $location
                    ]));
                    $query_string = $query_string ? '&' . $query_string : '';
                    echo pagination($total, $limit, $page, "?$query_string");
                    ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <div class="alert alert-info">
                    <p>没有找到匹配的项目。请尝试调整筛选条件或查看所有项目。</p>
                </div>
                <a href="list.php" class="btn btn-outline">查看所有项目</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 热门项目 -->
    <?php if ($hot_projects->num_rows > 0): ?>
        <section class="hot-projects">
            <div class="container">
                <div class="section-title">
                    <h2>热门项目</h2>
                    <p>这些项目受到了志愿者们的广泛关注和参与</p>
                </div>
                <div class="hot-projects-grid">
                    <?php while ($hot_project = $hot_projects->fetch_assoc()): ?>
                        <div class="project-card">
                            <div class="project-image">
                                <img src="<?php echo !empty($hot_project['cover_image']) ? $hot_project['cover_image'] : 'https://source.unsplash.com/random/400x200/?charity'; ?>" alt="<?php echo $hot_project['title']; ?>">
                                <span class="project-status-badge status-recruiting">热门项目</span>
                            </div>
                            <div class="project-info">
                                <h3 class="project-title">
                                    <a href="detail.php?id=<?php echo $hot_project['id']; ?>"><?php echo $hot_project['title']; ?></a>
                                </h3>
                                <div class="project-org">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($hot_project['organization_name']); ?>
                                </div>
                                <p class="project-description">
                                    <?php echo mb_substr(strip_tags($hot_project['description']), 0, 80, 'UTF-8') . '...'; ?>
                                </p>
                                <div class="project-meta">
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo $hot_project['location']; ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($hot_project['start_date'])); ?></span>
                                </div>
                                <div class="project-footer">
                                    <div class="project-quota">
                                        <span><?php echo $hot_project['registered']; ?>/<?php echo $hot_project['quota']; ?></span>
                                        <div class="project-quota-progress">
                                            <div class="project-quota-bar" style="width: <?php echo ($hot_project['quota'] > 0) ? min(($hot_project['registered'] / $hot_project['quota'] * 100), 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <a href="detail.php?id=<?php echo $hot_project['id']; ?>" class="btn btn-sm">查看详情</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 