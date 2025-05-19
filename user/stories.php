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

// 处理删除请求
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $story_id = (int)$_GET['id'];
    
    // 检查故事是否属于当前用户
    $sql = "SELECT id FROM stories WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $story_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // 删除故事
        $sql = "DELETE FROM stories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $story_id);
        
        if ($stmt->execute()) {
            set_message('故事已成功删除', 'success');
        } else {
            set_message('删除故事时出错，请重试', 'error');
        }
    } else {
        set_message('没有权限删除此故事', 'error');
    }
    
    // 重定向避免刷新后重复操作
    header('Location: stories.php');
    exit;
}

// 分页设置
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// 获取用户的故事总数
$sql = "SELECT COUNT(*) as total FROM stories WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_items = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// 获取用户发布的故事（分页）
$sql = "SELECT s.*, p.title as project_title 
        FROM stories s 
        LEFT JOIN projects p ON s.project_id = p.id 
        WHERE s.user_id = ? 
        ORDER BY s.created_at DESC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $offset, $items_per_page);
$stmt->execute();
$stories_result = $stmt->get_result();

$page_title = "我的志愿故事 - 爱心联萌";
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
        .stories-container {
            max-width: 1000px;
            margin: 40px auto;
        }
        
        .stories-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stories-title {
            font-size: 1.8rem;
            margin-bottom: 0;
        }
        
        .stories-table {
            width: 100%;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .stories-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .stories-table th, .stories-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray-color);
            vertical-align: middle;
        }
        
        .stories-table th {
            background-color: var(--light-gray-color);
            font-weight: bold;
        }
        
        .stories-table tr:hover {
            background-color: var(--lightest-gray-color);
        }
        
        .story-status {
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
        
        .story-cover {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .pagination a {
            display: inline-block;
            padding: 8px 16px;
            border: 1px solid var(--light-gray-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--dark-color);
        }
        
        .pagination a:hover {
            background-color: var(--light-gray-color);
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .no-stories {
            background-color: white;
            padding: 30px;
            text-align: center;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container stories-container">
        <?php echo display_message(); ?>
        
        <div class="stories-header">
            <h1 class="stories-title">我的志愿故事</h1>
            <a href="add_story.php" class="btn btn-primary">发布新故事</a>
        </div>
        
        <?php if ($stories_result->num_rows > 0): ?>
            <div class="stories-table">
                <table>
                    <thead>
                        <tr>
                            <th>封面</th>
                            <th>标题</th>
                            <th>分类</th>
                            <th>关联项目</th>
                            <th>发布时间</th>
                            <th>浏览/点赞</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($story = $stories_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($story['cover_image'])): ?>
                                        <img src="<?php echo $story['cover_image']; ?>" alt="封面图片" class="story-cover">
                                    <?php else: ?>
                                        <div class="story-cover" style="background-color: #eee; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: #999;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($story['title']); ?></td>
                                <td><?php echo htmlspecialchars($story['category_id']); ?></td>
                                <td><?php echo !empty($story['project_title']) ? htmlspecialchars($story['project_title']) : '无'; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($story['created_at'])); ?></td>
                                <td><?php echo $story['views']; ?> / <?php echo $story['likes']; ?></td>
                                <td>
                                    <span class="story-status <?php 
                                        switch ($story['status']) {
                                            case '待审核': echo 'status-waiting'; break;
                                            case '已审核': echo 'status-approved'; break;
                                            case '已拒绝': echo 'status-rejected'; break;
                                            default: echo '';
                                        }
                                    ?>">
                                        <?php echo $story['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../story/detail.php?id=<?php echo $story['id']; ?>" class="btn btn-sm" title="查看"><i class="fas fa-eye"></i></a>
                                    <a href="edit_story.php?id=<?php echo $story['id']; ?>" class="btn btn-sm" title="编辑"><i class="fas fa-edit"></i></a>
                                    <a href="stories.php?action=delete&id=<?php echo $story['id']; ?>" class="btn btn-sm btn-danger" title="删除" onclick="return confirm('确定要删除此故事吗？')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="stories.php?page=<?php echo $current_page - 1; ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="stories.php?page=<?php echo $i; ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="stories.php?page=<?php echo $current_page + 1; ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-stories">
                <p>您还没有发布任何志愿故事</p>
                <a href="add_story.php" class="btn btn-primary">发布第一个故事</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 底部信息 -->
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html> 