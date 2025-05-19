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

// 处理项目审核操作
if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    // 验证权限
    if (is_admin()) {
        // 管理员可以审核所有项目
        $can_update = true;
    } else {
        // 机构只能更新自己的项目状态，且不能自行审核
        $sql = "SELECT organization_id FROM projects WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $can_update = ($project && $project['organization_id'] == $user_id && $status != '已审核');
    }
    
    if ($can_update && $project_id > 0 && in_array($status, ['招募中', '待审核', '已审核', '已拒绝', '进行中', '已结束', '已取消', '草稿'])) {
        $sql = "UPDATE projects SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $project_id);
        
        if ($stmt->execute()) {
            set_message('项目状态已更新', 'success');
        } else {
            set_message('更新项目状态失败', 'error');
        }
    }
    
    header('Location: projects.php');
    exit;
}

// 处理项目删除
if (isset($_POST['action']) && $_POST['action'] == 'delete_project') {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    // 验证权限
    if (is_admin()) {
        // 管理员可以删除所有项目
        $can_delete = true;
    } else {
        // 机构只能删除自己的项目
        $sql = "SELECT organization_id FROM projects WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $can_delete = ($project && $project['organization_id'] == $user_id);
    }
    
    if ($can_delete && $project_id > 0) {
        $sql = "DELETE FROM projects WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $project_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            set_message('项目已删除', 'success');
        } else {
            set_message('删除项目失败', 'error');
        }
    }
    
    header('Location: projects.php');
    exit;
}

// 分页设置
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// 筛选条件
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

// 如果是机构用户，只能看到自己的项目
if (!is_admin()) {
    $where_conditions[] = "p.organization_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

if (!empty($filter_status)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.location LIKE ? OR u.organization_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
}

// 计算总记录数
$count_sql = "SELECT COUNT(*) as total FROM projects p 
              JOIN users u ON p.organization_id = u.id" . $where_clause;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// 获取项目列表
$sql = "SELECT p.*, u.organization_name, u.avatar as org_avatar 
        FROM projects p 
        JOIN users u ON p.organization_id = u.id" . 
        $where_clause . 
        " ORDER BY p.created_at DESC LIMIT ?, ?";
$all_params = $params;
$all_params[] = $offset;
$all_params[] = $items_per_page;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($all_params)) {
    $stmt->bind_param($types, ...$all_params);
}
$stmt->execute();
$projects = $stmt->get_result();

$page_title = "项目管理 - 爱心联萌";
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
        
        .filter-container {
            background-color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .filter-buttons {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }
        
        .table-container {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
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
        
        .admin-table tr:hover td {
            background-color: #f8f9fa;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            text-decoration: none;
            color: #212529;
        }
        
        .pagination a:hover {
            background-color: #e9ecef;
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .modal-close {
            cursor: pointer;
            font-size: 1.5rem;
            color: #aaa;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> 控制面板</a></li>
                <?php if (is_admin()): ?>
                    <li><a href="users.php"><i class="fas fa-users"></i> 用户管理</a></li>
                <?php endif; ?>
                <li><a href="projects.php" class="active"><i class="fas fa-project-diagram"></i> 项目管理</a></li>
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
                <h2>项目管理</h2>
            </div>
            
            <?php echo display_message(); ?>
            
            <!-- 筛选表单 -->
            <div class="filter-container">
                <form action="projects.php" method="get" class="row" style="width: 100%; display: flex;">
                    <div class="filter-group">
                        <label for="status">项目状态</label>
                        <select id="status" name="status">
                            <option value="">全部状态</option>
                            <option value="待审核" <?php echo $filter_status === '待审核' ? 'selected' : ''; ?>>待审核</option>
                            <option value="招募中" <?php echo $filter_status === '招募中' ? 'selected' : ''; ?>>招募中</option>
                            <option value="进行中" <?php echo $filter_status === '进行中' ? 'selected' : ''; ?>>进行中</option>
                            <option value="已结束" <?php echo $filter_status === '已结束' ? 'selected' : ''; ?>>已结束</option>
                            <option value="已取消" <?php echo $filter_status === '已取消' ? 'selected' : ''; ?>>已取消</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">搜索</label>
                        <input type="text" id="search" name="search" placeholder="项目名称/地点/机构名称" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">筛选</button>
                        <a href="projects.php" class="btn btn-secondary">重置</a>
                    </div>
                </form>
            </div>
            
            <!-- 项目表格 -->
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>项目名称</th>
                            <th>机构名称</th>
                            <th>地点</th>
                            <th>时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($projects->num_rows > 0): ?>
                            <?php while ($project = $projects->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $project['id']; ?></td>
                                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                                    <td><?php echo htmlspecialchars($project['organization_name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['location']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($project['start_date'])); ?> ~ <?php echo date('Y-m-d', strtotime($project['end_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php
                                            switch ($project['status']) {
                                                case '待审核': echo 'badge-warning'; break;
                                                case '招募中': echo 'badge-primary'; break;
                                                case '进行中': echo 'badge-success'; break;
                                                case '已结束': echo 'badge-secondary'; break;
                                                case '已取消': echo 'badge-danger'; break;
                                                default: echo '';
                                            }
                                        ?>">
                                            <?php echo $project['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="../project/detail.php?id=<?php echo $project['id']; ?>" class="btn btn-sm" title="查看"><i class="fas fa-eye"></i></a>
                                            
                                            <?php if (is_admin() && $project['status'] == '待审核'): ?>
                                                <button type="button" class="btn btn-sm btn-success approve-btn" 
                                                        data-project-id="<?php echo $project['id']; ?>" 
                                                        data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                        title="通过"><i class="fas fa-check"></i></button>
                                                <button type="button" class="btn btn-sm btn-danger reject-btn" 
                                                        data-project-id="<?php echo $project['id']; ?>" 
                                                        data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                        title="拒绝"><i class="fas fa-times"></i></button>
                                            <?php endif; ?>
                                            
                                            <?php if (is_admin() || $project['organization_id'] == $user_id): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                        data-project-id="<?php echo $project['id']; ?>" 
                                                        data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                        title="删除"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">暂无项目数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="projects.php?page=<?php echo $current_page - 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="projects.php?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="projects.php?page=<?php echo $current_page + 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 批准项目模态框 -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">批准项目</h4>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>确定要批准项目 <strong id="approveTitle"></strong> 吗？</p>
                <form id="approveForm" action="projects.php" method="post">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="project_id" id="approveProjectId">
                    <input type="hidden" name="status" value="招募中">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">取消</button>
                <button type="button" class="btn btn-success" id="approveConfirm">确认批准</button>
            </div>
        </div>
    </div>

    <!-- 拒绝项目模态框 -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">拒绝项目</h4>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>确定要拒绝项目 <strong id="rejectTitle"></strong> 吗？</p>
                <form id="rejectForm" action="projects.php" method="post">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="project_id" id="rejectProjectId">
                    <input type="hidden" name="status" value="已拒绝">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">取消</button>
                <button type="button" class="btn btn-danger" id="rejectConfirm">确认拒绝</button>
            </div>
        </div>
    </div>

    <!-- 删除项目模态框 -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">删除项目</h4>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>确定要删除项目 <strong id="deleteTitle"></strong> 吗？此操作不可撤销。</p>
                <form id="deleteForm" action="projects.php" method="post">
                    <input type="hidden" name="action" value="delete_project">
                    <input type="hidden" name="project_id" id="deleteProjectId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">取消</button>
                <button type="button" class="btn btn-danger" id="deleteConfirm">确认删除</button>
            </div>
        </div>
    </div>

    <script>
        // 模态框功能
        document.addEventListener('DOMContentLoaded', function() {
            // 获取所有模态框元素
            const modals = document.querySelectorAll('.modal');
            const approveButtons = document.querySelectorAll('.approve-btn');
            const rejectButtons = document.querySelectorAll('.reject-btn');
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const closeButtons = document.querySelectorAll('.modal-close, .modal-close-btn');
            
            // 批准按钮点击事件
            approveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const projectId = this.getAttribute('data-project-id');
                    const title = this.getAttribute('data-title');
                    document.getElementById('approveProjectId').value = projectId;
                    document.getElementById('approveTitle').textContent = title;
                    document.getElementById('approveModal').classList.add('show');
                });
            });
            
            // 拒绝按钮点击事件
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const projectId = this.getAttribute('data-project-id');
                    const title = this.getAttribute('data-title');
                    document.getElementById('rejectProjectId').value = projectId;
                    document.getElementById('rejectTitle').textContent = title;
                    document.getElementById('rejectModal').classList.add('show');
                });
            });
            
            // 删除按钮点击事件
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const projectId = this.getAttribute('data-project-id');
                    const title = this.getAttribute('data-title');
                    document.getElementById('deleteProjectId').value = projectId;
                    document.getElementById('deleteTitle').textContent = title;
                    document.getElementById('deleteModal').classList.add('show');
                });
            });
            
            // 关闭按钮点击事件
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modals.forEach(modal => {
                        modal.classList.remove('show');
                    });
                });
            });
            
            // 点击模态框外部关闭
            window.addEventListener('click', function(event) {
                modals.forEach(modal => {
                    if (event.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });
            
            // 确认按钮点击事件
            document.getElementById('approveConfirm').addEventListener('click', function() {
                document.getElementById('approveForm').submit();
            });
            
            document.getElementById('rejectConfirm').addEventListener('click', function() {
                document.getElementById('rejectForm').submit();
            });
            
            document.getElementById('deleteConfirm').addEventListener('click', function() {
                document.getElementById('deleteForm').submit();
            });
        });
    </script>
</body>
</html> 