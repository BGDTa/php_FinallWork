<header class="main-header">
    <div class="container">
        <div class="logo">
            <a href="/">爱心联萌</a>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="/" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">首页</a></li>
                <li><a href="/project/list.php" class="<?php echo (strpos($_SERVER['PHP_SELF'], 'project/') !== false) ? 'active' : ''; ?>">公益项目</a></li>
                <li><a href="/story/index.php" class="<?php echo (strpos($_SERVER['PHP_SELF'], 'story/') !== false) ? 'active' : ''; ?>">志愿者故事</a></li>
                <li><a href="/course/index.php" class="<?php echo (strpos($_SERVER['PHP_SELF'], 'course/') !== false) ? 'active' : ''; ?>">公益课堂</a></li>
            </ul>
        </nav>
        <div class="user-actions">
            <?php if (is_logged_in()): ?>
                <div class="user-dropdown">
                    <button class="user-dropdown-btn">
                        <?php
                        $user = get_user_info($_SESSION['user_id']);
                        $avatar = !empty($user['avatar']) ? $user['avatar'] : 'https://via.placeholder.com/40';
                        ?>
                        <img src="<?php echo $avatar; ?>" alt="用户头像" class="user-avatar">
                        <span><?php echo $_SESSION['username']; ?></span>
                        <i class="fas fa-caret-down"></i>
                    </button>
                    <div class="user-dropdown-content">
                        <a href="/user/dashboard.php"><i class="fas fa-tachometer-alt"></i> 个人中心</a>
                        <?php if (is_admin() || is_organization()): ?>
                            <a href="/admin/index.php"><i class="fas fa-cog"></i> 后台管理</a>
                        <?php endif; ?>
                        <a href="/user/profile.php"><i class="fas fa-user-edit"></i> 编辑资料</a>
                        <a href="/user/logout.php"><i class="fas fa-sign-out-alt"></i> 退出登录</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/user/login.php" class="btn btn-outline-light">登录</a>
                <a href="/user/register.php" class="btn btn-primary">注册</a>
            <?php endif; ?>
        </div>
        <div class="mobile-toggle">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</header>

<!-- 显示消息提醒 -->
<div class="message-container">
    <?php echo display_message(); ?>
</div> 