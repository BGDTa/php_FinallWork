<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 分页
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9; // 每页显示的故事数
$offset = ($page - 1) * $limit;

// 搜索和筛选
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';

// 构建查询条件
$where_clause = "WHERE s.status = '已审核'";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause .= " AND (s.title LIKE ? OR s.content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($category)) {
    $where_clause .= " AND s.category_id = ?";
    $params[] = $category;
    $types .= "s";
}

// 计算总记录数
$count_sql = "SELECT COUNT(*) as total FROM stories s $where_clause";
$stmt = $conn->prepare($count_sql);
if (!$stmt) {
    die("预处理语句准备失败: " . $conn->error . " SQL: " . $count_sql);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// 获取故事列表
$sql = "SELECT s.*, u.username, u.avatar, p.title as project_title 
        FROM stories s 
        LEFT JOIN users u ON s.user_id = u.id 
        LEFT JOIN projects p ON s.project_id = p.id 
        $where_clause 
        ORDER BY s.created_at DESC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("预处理语句准备失败: " . $conn->error . " SQL: " . $sql);
}
$types .= "ii";
$params[] = $offset;
$params[] = $limit;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stories = $stmt->get_result();

// 获取推荐故事（点赞最多的3个）
$sql = "SELECT s.*, u.username, u.avatar, p.title as project_title 
        FROM stories s 
        LEFT JOIN users u ON s.user_id = u.id 
        LEFT JOIN projects p ON s.project_id = p.id 
        WHERE s.status = '已审核' 
        ORDER BY s.likes DESC 
        LIMIT 3";
$featured_stories = $conn->query($sql);

// 获取故事分类列表（示例分类）
$categories = [
    '教育支教', '环境保护', '关爱儿童', '敬老助老', '扶贫济困', 
    '医疗救助', '应急救援', '文化传承', '社区服务', '其他'
];

$page_title = "志愿者故事 - 爱心联萌";
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
        .stories-banner {
            background-color: var(--accent-color);
            padding: 60px 0;
            color: white;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .stories-banner h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stories-banner p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 30px;
        }
        
        .search-bar {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 15px 20px;
            border-radius: 50px;
            border: none;
            font-size: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 5px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .search-btn:hover {
            background-color: #27ae60;
        }
        
        .featured-section {
            padding: 60px 0;
            background-color: #f8f9fa;
            margin-bottom: 40px;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .section-title h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .section-title p {
            color: var(--gray-color);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .featured-story {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .featured-story:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .featured-image {
            height: 200px;
            position: relative;
            overflow: hidden;
        }
        
        .featured-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .featured-story:hover .featured-image img {
            transform: scale(1.05);
        }
        
        .featured-label {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--accent-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .featured-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .featured-title {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .featured-meta {
            display: flex;
            justify-content: space-between;
            color: var(--gray-color);
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .featured-meta .author {
            display: flex;
            align-items: center;
        }
        
        .featured-meta .author img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .featured-text {
            margin-bottom: 20px;
            flex-grow: 1;
            line-height: 1.6;
        }
        
        .featured-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .story-stats {
            display: flex;
            gap: 15px;
            color: var(--gray-color);
        }
        
        .story-stats span {
            display: flex;
            align-items: center;
        }
        
        .story-stats i {
            margin-right: 5px;
        }
        
        .categories {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .category-btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background-color: white;
            border: 1px solid var(--light-gray-color);
            border-radius: 20px;
            color: var(--text-color);
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .category-btn:hover, .category-btn.active {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        
        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .story-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .story-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .story-image {
            height: 180px;
            position: relative;
            overflow: hidden;
        }
        
        .story-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .story-card:hover .story-image img {
            transform: scale(1.05);
        }
        
        .story-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .story-author {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .story-author img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .story-author-info {
            flex: 1;
        }
        
        .story-author-name {
            font-weight: 500;
        }
        
        .story-date {
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        .story-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            flex-grow: 0;
        }
        
        .story-text {
            color: var(--gray-color);
            margin-bottom: 15px;
            line-height: 1.5;
            flex-grow: 1;
        }
        
        .story-project {
            font-size: 0.9rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .story-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .story-actions {
            display: flex;
            gap: 15px;
            color: var(--gray-color);
        }
        
        .story-actions a {
            display: flex;
            align-items: center;
            color: var(--gray-color);
            transition: var(--transition);
        }
        
        .story-actions a:hover {
            color: var(--accent-color);
        }
        
        .story-actions i {
            margin-right: 5px;
        }
        
        .add-story {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .add-story p {
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .no-stories {
            text-align: center;
            padding: 40px 0;
            color: var(--gray-color);
        }
        
        @media (max-width: 991px) {
            .featured-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .featured-grid {
                grid-template-columns: 1fr;
            }
            
            .stories-grid {
                grid-template-columns: 1fr;
            }
            
            .stories-banner h1 {
                font-size: 2rem;
            }
            
            .stories-banner p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <!-- 页面横幅 -->
    <div class="stories-banner">
        <div class="container">
            <h1>志愿者故事</h1>
            <p>分享感人的志愿服务经历，传递爱与温暖</p>
            <form action="" method="get" class="search-bar">
                <input type="text" name="search" class="search-input" placeholder="搜索故事..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>
    </div>
    
    <!-- 推荐故事 -->
    <?php if ($featured_stories->num_rows > 0): ?>
        <section class="featured-section">
            <div class="container">
                <div class="section-title">
                    <h2>精选故事</h2>
                    <p>这些温暖人心的故事获得了志愿者们的广泛共鸣与认可</p>
                </div>
                
                <div class="featured-grid">
                    <?php while ($story = $featured_stories->fetch_assoc()): ?>
                        <div class="featured-story">
                            <div class="featured-image">
                                <img src="<?php echo !empty($story['cover_image']) ? $story['cover_image'] : 'https://source.unsplash.com/random/400x200/?volunteer'; ?>" alt="<?php echo $story['title']; ?>">
                                <span class="featured-label">精选</span>
                            </div>
                            <div class="featured-content">
                                <h3 class="featured-title">
                                    <a href="detail.php?id=<?php echo $story['id']; ?>"><?php echo $story['title']; ?></a>
                                </h3>
                                <div class="featured-meta">
                                    <div class="author">
                                        <img src="<?php echo !empty($story['avatar']) ? $story['avatar'] : 'https://via.placeholder.com/24'; ?>" alt="<?php echo $story['username']; ?>">
                                        <span><?php echo $story['username']; ?></span>
                                    </div>
                                    <span><?php echo date('Y-m-d', strtotime($story['created_at'])); ?></span>
                                </div>
                                <div class="featured-text">
                                    <?php echo mb_substr(strip_tags($story['content']), 0, 150, 'UTF-8') . '...'; ?>
                                </div>
                                <div class="featured-footer">
                                    <div class="story-stats">
                                        <span><i class="fas fa-eye"></i> <?php echo $story['views']; ?></span>
                                        <span><i class="fas fa-heart"></i> <?php echo $story['likes']; ?></span>
                                        <span><i class="fas fa-comment"></i> 
                                            <?php
                                            $sql = "SELECT COUNT(*) as count FROM comments WHERE story_id = ?";
                                            $stmt = $conn->prepare($sql);
                                            if (!$stmt) {
                                                echo "0";
                                            } else {
                                                $stmt->bind_param("i", $story['id']);
                                                $stmt->execute();
                                                echo $stmt->get_result()->fetch_assoc()['count'];
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <a href="detail.php?id=<?php echo $story['id']; ?>" class="btn btn-sm">阅读全文</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
    
    <div class="container">
        <!-- 故事分类 -->
        <div class="categories">
            <a href="?category=" class="category-btn<?php echo empty($category) ? ' active' : ''; ?>">全部</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?php echo urlencode($cat); ?>" class="category-btn<?php echo $category == $cat ? ' active' : ''; ?>"><?php echo $cat; ?></a>
            <?php endforeach; ?>
        </div>
        
        <!-- 添加故事按钮 -->
        <div class="add-story">
            <?php if (is_logged_in()): ?>
                <p>有感人的志愿服务经历想要分享？</p>
                <a href="../user/add_story.php" class="btn btn-primary">发布我的故事</a>
            <?php else: ?>
                <p>登录后即可分享您的志愿服务经历</p>
                <a href="../user/login.php?redirect=<?php echo urlencode('../user/add_story.php'); ?>" class="btn btn-primary">立即登录</a>
            <?php endif; ?>
        </div>
        
        <!-- 故事列表 -->
        <?php if ($stories->num_rows > 0): ?>
            <div class="stories-grid">
                <?php while ($story = $stories->fetch_assoc()): ?>
                    <div class="story-card">
                        <div class="story-image">
                            <img src="<?php echo !empty($story['cover_image']) ? $story['cover_image'] : 'https://source.unsplash.com/random/400x200/?volunteer'; ?>" alt="<?php echo $story['title']; ?>">
                        </div>
                        <div class="story-content">
                            <div class="story-author">
                                <img src="<?php echo !empty($story['avatar']) ? $story['avatar'] : 'https://via.placeholder.com/40'; ?>" alt="<?php echo $story['username']; ?>">
                                <div class="story-author-info">
                                    <div class="story-author-name"><?php echo $story['username']; ?></div>
                                    <div class="story-date"><?php echo date('Y-m-d', strtotime($story['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <h3 class="story-title">
                                <a href="detail.php?id=<?php echo $story['id']; ?>"><?php echo $story['title']; ?></a>
                            </h3>
                            
                            <div class="story-text">
                                <?php echo mb_substr(strip_tags($story['content']), 0, 100, 'UTF-8') . '...'; ?>
                            </div>
                            
                            <?php if (!empty($story['project_title'])): ?>
                                <div class="story-project">
                                    <i class="fas fa-project-diagram"></i> 相关项目：<?php echo $story['project_title']; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="story-footer">
                                <div class="story-actions">
                                    <a href="detail.php?id=<?php echo $story['id']; ?>#comments">
                                        <i class="fas fa-comment"></i> 
                                        <?php
                                        $sql = "SELECT COUNT(*) as count FROM comments WHERE story_id = ?";
                                        $stmt = $conn->prepare($sql);
                                        if (!$stmt) {
                                            echo "0";
                                        } else {
                                            $stmt->bind_param("i", $story['id']);
                                            $stmt->execute();
                                            echo $stmt->get_result()->fetch_assoc()['count'];
                                        }
                                        ?>
                                    </a>
                                    <span><i class="fas fa-heart"></i> <?php echo $story['likes']; ?></span>
                                </div>
                                <a href="detail.php?id=<?php echo $story['id']; ?>" class="btn btn-sm">阅读全文</a>
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
                        'category' => $category
                    ]));
                    $query_string = $query_string ? '&' . $query_string : '';
                    echo pagination($total, $limit, $page, "?$query_string");
                    ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-stories">
                <h3>暂无相关故事</h3>
                <p>快来分享您的志愿服务经历，成为第一个发布故事的志愿者吧！</p>
                <?php if (!empty($search) || !empty($category)): ?>
                    <a href="index.php" class="btn btn-outline">查看全部故事</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 