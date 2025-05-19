<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查是否已登录，未登录则重定向到登录页
if (!is_logged_in()) {
    header('Location: ../user/login.php');
    exit;
}

// 检查是否有管理员权限
if (!is_admin()) {
    header('Location: ../user/dashboard.php');
    exit;
}

// 处理用户审核
if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    if ($user_id > 0 && in_array($status, ['已审核', '已拒绝'])) {
        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            set_message('用户状态已更新', 'success');
        } else {
            set_message('更新用户状态失败', 'error');
        }
    }
    
    header('Location: users.php');
    exit;
}

// 处理删除用户
if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    // 不能删除自己
    if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
        $sql = "DELETE FROM users WHERE id = ? AND role != 'admin'";  // 防止删除管理员
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            set_message('用户已删除', 'success');
        } else {
            set_message('删除用户失败', 'error');
        }
    } elseif ($user_id == $_SESSION['user_id']) {
        set_message('不能删除自己的账户', 'error');
    }
    
    header('Location: users.php');
    exit;
}

// 分页设置
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// 筛选条件
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_role)) {
    $where_conditions[] = "role = ?";
    $params[] = $filter_role;
    $types .= 's';
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR real_name LIKE ? OR organization_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
}

// 计算总记录数
$count_sql = "SELECT COUNT(*) as total FROM users" . $where_clause;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// 获取用户列表
$sql = "SELECT * FROM users" . $where_clause . " ORDER BY created_at DESC LIMIT ?, ?";
$all_params = $params;
$all_params[] = $offset;
$all_params[] = $items_per_page;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($all_params)) {
    $stmt->bind_param($types, ...$all_params);
}
$stmt->execute();
$users = $stmt->get_result();

$page_title = "用户管理 - 爱心联萌";
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
                <li><a href="users.php" class="active"><i class="fas fa-users"></i> 用户管理</a></li>
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
                <h2>用户管理</h2>
            </div>
            
            <?php echo display_message(); ?>
            
            <!-- 筛选表单 -->
            <div class="filter-container">
                <form action="users.php" method="get" class="row" style="width: 100%; display: flex;">
                    <div class="filter-group">
                        <label for="role">用户角色</label>
                        <select id="role" name="role">
                            <option value="">全部角色</option>
                            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>管理员</option>
                            <option value="organization" <?php echo $filter_role === 'organization' ? 'selected' : ''; ?>>机构</option>
                            <option value="volunteer" <?php echo $filter_role === 'volunteer' ? 'selected' : ''; ?>>志愿者</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">用户状态</label>
                        <select id="status" name="status">
                            <option value="">全部状态</option>
                            <option value="待审核" <?php echo $filter_status === '待审核' ? 'selected' : ''; ?>>待审核</option>
                            <option value="已审核" <?php echo $filter_status === '已审核' ? 'selected' : ''; ?>>已审核</option>
                            <option value="已拒绝" <?php echo $filter_status === '已拒绝' ? 'selected' : ''; ?>>已拒绝</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">搜索</label>
                        <input type="text" id="search" name="search" placeholder="用户名/邮箱/姓名/机构名" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">筛选</button>
                        <a href="users.php" class="btn btn-secondary">重置</a>
                    </div>
                </form>
            </div>
            
            <!-- 用户表格 -->
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>邮箱</th>
                            <th>角色</th>
                            <th>姓名/机构名</th>
                            <th>状态</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            switch ($user['role']) {
                                                case 'admin': echo 'badge-primary'; break;
                                                case 'organization': echo 'badge-success'; break;
                                                case 'volunteer': echo 'badge-warning'; break;
                                                default: echo '';
                                            }
                                        ?>">
                                            <?php 
                                                switch ($user['role']) {
                                                    case 'admin': echo '管理员'; break;
                                                    case 'organization': echo '机构'; break;
                                                    case 'volunteer': echo '志愿者'; break;
                                                    default: echo '未知';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($user['role'] == 'organization' && !empty($user['organization_name'])) {
                                                echo htmlspecialchars($user['organization_name']);
                                            } elseif (!empty($user['real_name'])) {
                                                echo htmlspecialchars($user['real_name']);
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            switch ($user['status']) {
                                                case '待审核': echo 'badge-warning'; break;
                                                case '已审核': echo 'badge-success'; break;
                                                case '已拒绝': echo 'badge-danger'; break;
                                                default: echo '';
                                            }
                                        ?>">
                                            <?php echo $user['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($user['status'] == '待审核'): ?>
                                                <button type="button" class="btn btn-sm btn-success approve-btn" 
                                                        data-user-id="<?php echo $user['id']; ?>" 
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        title="通过"><i class="fas fa-check"></i></button>
                                                <button type="button" class="btn btn-sm btn-danger reject-btn" 
                                                        data-user-id="<?php echo $user['id']; ?>" 
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        title="拒绝"><i class="fas fa-times"></i></button>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] != 'admin'): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                        data-user-id="<?php echo $user['id']; ?>" 
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        title="删除"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">暂无用户数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="users.php?page=<?php echo $current_page - 1; ?>&role=<?php echo urlencode($filter_role); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="users.php?page=<?php echo $i; ?>&role=<?php echo urlencode($filter_role); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="users.php?page=<?php echo $current_page + 1; ?>&role=<?php echo urlencode($filter_role); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 批准用户模态框 -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">批准用户</h4>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>确定要批准用户 <strong id="approveUsername"></strong> 的注册申请吗？</p>
                <form id="approveForm" action="users.php" method="post">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="user_id" id="approveUserId">
                    <input type="hidden" name="status" value="已审核">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">取消</button>
                <button type="button" class="btn btn-success" id="approveConfirm">确认批准</button>
            </div>
        </div>
    </div>

    <!-- 拒绝用户模态框 -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">拒绝用户</h4>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>确定要拒绝用户 <strong id="rejectUsername"></strong> 的注册申请吗？</p>
                <form id="rejectForm" action="users.php" method="post">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="user_id" id="rejectUserId">
                    <input type="hidden" name="status" value="已拒绝">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">取消</button>
                <button type="button" class="btn btn-danger" id="rejectConfirm">确认拒绝</button>
            </div>
        </div>
    </div>

    <!-- 删除用户模态框 -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">删除用户</h4>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>确定要删除用户 <strong id="deleteUsername"></strong> 吗？此操作不可撤销。</p>
                <form id="deleteForm" action="users.php" method="post">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId">
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
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    document.getElementById('approveUserId').value = userId;
                    document.getElementById('approveUsername').textContent = username;
                    document.getElementById('approveModal').classList.add('show');
                });
            });
            
            // 拒绝按钮点击事件
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    document.getElementById('rejectUserId').value = userId;
                    document.getElementById('rejectUsername').textContent = username;
                    document.getElementById('rejectModal').classList.add('show');
                });
            });
            
            // 删除按钮点击事件
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    document.getElementById('deleteUserId').value = userId;
                    document.getElementById('deleteUsername').textContent = username;
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