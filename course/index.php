<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 分页
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9; // 每页显示的课程数
$offset = ($page - 1) * $limit;

// 搜索
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// 构建查询条件
$where_clause = "WHERE c.status = '已发布'";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// 添加分类筛选
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
if (!empty($category) && $category != 'all') {
    $where_clause .= " AND c.category_id = ?";
    $params[] = $category;
    $types .= "s";
}

// 计算总记录数
$count_sql = "SELECT COUNT(*) as total FROM courses c $where_clause";
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

// 获取课程列表
$sql = "SELECT c.*, u.username as author_name FROM courses c 
        LEFT JOIN users u ON c.author_id = u.id 
        $where_clause 
        ORDER BY c.created_at DESC 
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
$result = $stmt->get_result();

// 获取推荐课程（浏览量最高的3个课程）
$sql = "SELECT c.*, u.username as author_name FROM courses c 
        LEFT JOIN users u ON c.author_id = u.id 
        WHERE c.status = '已发布' 
        ORDER BY c.views DESC 
        LIMIT 3";
$featured_courses = $conn->query($sql);

$page_title = "公益课堂 - 爱心联萌";
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
        .courses-banner {
            background-color: var(--secondary-color);
            padding: 60px 0;
            color: white;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .courses-banner h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .courses-banner p {
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
        
        .featured-course {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .featured-course:hover {
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
        
        .featured-course:hover .featured-image img {
            transform: scale(1.05);
        }
        
        .featured-label {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .featured-content {
            padding: 20px;
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
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .course-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .course-image {
            height: 180px;
            position: relative;
            overflow: hidden;
        }
        
        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .course-card:hover .course-image img {
            transform: scale(1.05);
        }
        
        .course-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .course-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            flex-grow: 0;
        }
        
        .course-description {
            color: var(--gray-color);
            margin-bottom: 15px;
            flex-grow: 1;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            color: var(--gray-color);
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .course-meta i {
            margin-right: 5px;
        }
        
        .course-author {
            display: flex;
            align-items: center;
            margin-top: auto;
        }
        
        .author-name {
            font-weight: 500;
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
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .no-courses {
            text-align: center;
            padding: 40px 0;
            color: var(--gray-color);
        }
        
        @media (max-width: 991px) {
            .featured-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .featured-grid {
                grid-template-columns: 1fr;
            }
            
            .courses-banner h1 {
                font-size: 2rem;
            }
            
            .courses-banner p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <!-- 页面横幅 -->
    <div class="courses-banner">
        <div class="container">
            <h1>公益课堂</h1>
            <p>提升公益知识和志愿服务技能，成为更专业的志愿者</p>
            <form action="" method="get" class="search-bar">
                <input type="text" name="search" class="search-input" placeholder="搜索课程..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>
    </div>
    
    <!-- 推荐课程 -->
    <?php if ($featured_courses->num_rows > 0): ?>
        <section class="featured-section">
            <div class="container">
                <div class="section-title">
                    <h2>推荐课程</h2>
                    <p>精选优质课程，提升您的公益服务能力</p>
                </div>
                
                <div class="featured-grid">
                    <?php while ($course = $featured_courses->fetch_assoc()): ?>
                        <div class="featured-course">
                            <div class="featured-image">
                                <img src="<?php echo !empty($course['cover_image']) ? $course['cover_image'] : 'https://source.unsplash.com/random/400x200/?education'; ?>" alt="<?php echo $course['title']; ?>">
                                <span class="featured-label">推荐</span>
                            </div>
                            <div class="featured-content">
                                <h3 class="featured-title">
                                    <a href="detail.php?id=<?php echo $course['id']; ?>"><?php echo $course['title']; ?></a>
                                </h3>
                                <div class="featured-meta">
                                    <span><i class="fas fa-user"></i> <?php echo $course['author_name']; ?></span>
                                    <span><i class="fas fa-eye"></i> <?php echo $course['views']; ?>次学习</span>
                                </div>
                                <p><?php echo mb_substr(strip_tags($course['description']), 0, 80, 'UTF-8') . '...'; ?></p>
                                <a href="detail.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">立即学习</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
    
    <div class="container">
        <!-- 课程分类 -->
        <div class="categories">
            <a href="?category=all" class="category-btn<?php echo !isset($_GET['category']) || $_GET['category'] == 'all' ? ' active' : ''; ?>">全部课程</a>
            <a href="?category=basic" class="category-btn<?php echo isset($_GET['category']) && $_GET['category'] == 'basic' ? ' active' : ''; ?>">基础知识</a>
            <a href="?category=skill" class="category-btn<?php echo isset($_GET['category']) && $_GET['category'] == 'skill' ? ' active' : ''; ?>">技能培训</a>
            <a href="?category=special" class="category-btn<?php echo isset($_GET['category']) && $_GET['category'] == 'special' ? ' active' : ''; ?>">专题课程</a>
            <a href="?category=case" class="category-btn<?php echo isset($_GET['category']) && $_GET['category'] == 'case' ? ' active' : ''; ?>">案例分享</a>
        </div>
        
        <!-- 课程列表 -->
        <?php if ($result->num_rows > 0): ?>
            <div class="courses-grid">
                <?php while ($course = $result->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-image">
                            <img src="<?php echo !empty($course['cover_image']) ? $course['cover_image'] : 'https://source.unsplash.com/random/300x180/?education'; ?>" alt="<?php echo $course['title']; ?>">
                        </div>
                        <div class="course-content">
                            <h3 class="course-title">
                                <a href="detail.php?id=<?php echo $course['id']; ?>"><?php echo $course['title']; ?></a>
                            </h3>
                            <p class="course-description"><?php echo mb_substr(strip_tags($course['description']), 0, 100, 'UTF-8') . '...'; ?></p>
                            <div class="course-meta">
                                <span><i class="fas fa-clock"></i> <?php echo $course['duration']; ?> 分钟</span>
                                <span><i class="fas fa-eye"></i> <?php echo $course['views']; ?> 次学习</span>
                            </div>
                            <div class="course-author">
                                <span class="author-name"><i class="fas fa-user"></i> <?php echo $course['author_name']; ?></span>
                            </div>
                            <a href="detail.php?id=<?php echo $course['id']; ?>" class="btn btn-sm">开始学习</a>
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
                        'category' => isset($_GET['category']) ? $_GET['category'] : null
                    ]));
                    $query_string = $query_string ? '&' . $query_string : '';
                    echo pagination($total, $limit, $page, "?$query_string");
                    ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-courses">
                <h3>暂无相关课程</h3>
                <p>我们正在努力添加更多课程，敬请期待！</p>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="btn btn-outline">查看全部课程</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 