<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
$page_title = "爱心联萌 - 公益志愿信息平台";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <!-- 头部导航 -->
    <?php include 'includes/header.php'; ?>

    <!-- 轮播Banner -->
    <section class="banner">
        <div class="banner-content">
            <h1>爱心联萌</h1>
            <p>连接爱心，共创美好</p>
            <div class="banner-buttons">
                <a href="project/list.php" class="btn btn-primary">查看项目</a>
                <a href="user/register.php" class="btn btn-secondary">成为志愿者</a>
            </div>
        </div>
        <div class="banner-image">
            <!-- 使用在线图片占位 -->
            <img src="https://source.unsplash.com/random/1200x400/?volunteer" alt="公益志愿服务">
        </div>
    </section>

    <!-- 核心功能区 -->
    <section class="features">
        <div class="container">
            <h2 class="section-title">我们的服务</h2>
            <div class="feature-grid">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-hands-helping"></i></div>
                    <h3>公益项目</h3>
                    <p>浏览最新的公益项目，找到适合您参与的志愿服务机会</p>
                    <a href="project/list.php" class="feature-link">查看项目 <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-book-reader"></i></div>
                    <h3>志愿者故事</h3>
                    <p>阅读感人的志愿者故事，被爱与奉献的精神所感染</p>
                    <a href="story/index.php" class="feature-link">阅读故事 <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h3>公益课堂</h3>
                    <p>学习公益知识，提升志愿服务技能，成为更好的志愿者</p>
                    <a href="course/index.php" class="feature-link">进入课堂 <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                    <h3>在线报名</h3>
                    <p>便捷快速地报名参与公益项目，追踪您的志愿服务记录</p>
                    <a href="user/dashboard.php" class="feature-link">立即报名 <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- 最新项目展示 -->
    <section class="latest-projects">
        <div class="container">
            <h2 class="section-title">热门项目</h2>
            <div class="project-grid">
                <?php
                // 获取最新的4个项目
                $sql = "SELECT * FROM projects WHERE status = '招募中' ORDER BY created_at DESC LIMIT 4";
                $result = mysqli_query($conn, $sql);
                
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                        <div class="project-card">
                            <div class="project-image">
                                <img src="<?php echo !empty($row['cover_image']) ? $row['cover_image'] : 'https://source.unsplash.com/random/300x200/?charity'; ?>" alt="<?php echo $row['title']; ?>">
                                <span class="project-status"><?php echo $row['status']; ?></span>
                            </div>
                            <div class="project-content">
                                <h3><?php echo $row['title']; ?></h3>
                                <p><?php echo mb_substr($row['description'], 0, 50, 'UTF-8') . '...'; ?></p>
                                <div class="project-meta">
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo $row['location']; ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($row['start_date'])); ?></span>
                                </div>
                                <a href="project/detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm">查看详情</a>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<p class='no-data'>暂无公益项目，敬请期待！</p>";
                }
                ?>
            </div>
            <div class="view-all">
                <a href="project/list.php" class="btn btn-outline">查看全部项目</a>
            </div>
        </div>
    </section>

    <!-- 志愿者故事 -->
    <section class="volunteer-stories">
        <div class="container">
            <h2 class="section-title">志愿者故事</h2>
            <div class="stories-grid">
                <?php
                // 获取最新的3个故事
                $sql = "SELECT s.*, u.username, u.avatar FROM stories s 
                        LEFT JOIN users u ON s.user_id = u.id 
                        WHERE s.status = '已审核' ORDER BY s.created_at DESC LIMIT 3";
                $result = mysqli_query($conn, $sql);
                
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                        <div class="story-card">
                            <div class="story-author">
                                <img src="<?php echo !empty($row['avatar']) ? $row['avatar'] : 'https://via.placeholder.com/50'; ?>" alt="<?php echo $row['username']; ?>">
                                <span><?php echo $row['username']; ?></span>
                            </div>
                            <h3><?php echo $row['title']; ?></h3>
                            <p><?php echo mb_substr($row['content'], 0, 100, 'UTF-8') . '...'; ?></p>
                            <a href="story/detail.php?id=<?php echo $row['id']; ?>" class="story-link">阅读更多</a>
                        </div>
                        <?php
                    }
                } else {
                    echo "<p class='no-data'>暂无志愿者故事，敬请期待！</p>";
                }
                ?>
            </div>
            <div class="view-all">
                <a href="story/index.php" class="btn btn-outline">查看全部故事</a>
            </div>
        </div>
    </section>

    <!-- 公益课堂预览 -->
    <section class="charity-courses">
        <div class="container">
            <h2 class="section-title">公益课堂</h2>
            <div class="courses-grid">
                <?php
                // 获取最新的3个课程
                $sql = "SELECT * FROM courses WHERE status = '已发布' ORDER BY created_at DESC LIMIT 3";
                $result = mysqli_query($conn, $sql);
                
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $course_image = !empty($row['cover_image']) ? $row['cover_image'] : 'https://source.unsplash.com/random/300x200/?education';
                        ?>
                        <div class="course-card">
                            <div class="course-image">
                                <img src="<?php echo $course_image; ?>" alt="<?php echo $row['title']; ?>">
                            </div>
                            <div class="course-content">
                                <h3><?php echo $row['title']; ?></h3>
                                <p><?php echo mb_substr($row['description'], 0, 50, 'UTF-8') . '...'; ?></p>
                                <a href="course/detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm">立即学习</a>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    // 如果没有课程，显示示例课程
                    ?>
                    <div class="course-card">
                        <div class="course-image">
                            <img src="https://source.unsplash.com/random/300x200/?teaching" alt="志愿服务基础">
                        </div>
                        <div class="course-content">
                            <h3>志愿服务基础</h3>
                            <p>了解志愿服务的基本概念、类型以及参与方式</p>
                            <a href="not_found.php" class="btn btn-sm">立即学习</a>
                        </div>
                    </div>
                    <div class="course-card">
                        <div class="course-image">
                            <img src="https://source.unsplash.com/random/300x200/?education" alt="急救知识培训">
                        </div>
                        <div class="course-content">
                            <h3>急救知识培训</h3>
                            <p>学习基本急救技能，提升应急反应能力</p>
                            <a href="not_found.php" class="btn btn-sm">立即学习</a>
                        </div>
                    </div>
                    <div class="course-card">
                        <div class="course-image">
                            <img src="https://source.unsplash.com/random/300x200/?learning" alt="环保志愿指南">
                        </div>
                        <div class="course-content">
                            <h3>环保志愿指南</h3>
                            <p>掌握环保志愿服务的知识和技能，为环境保护贡献力量</p>
                            <a href="not_found.php" class="btn btn-sm">立即学习</a>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            <div class="view-all">
                <a href="course/index.php" class="btn btn-outline">浏览全部课程</a>
            </div>
        </div>
    </section>

    <!-- 爱心统计 -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" id="volunteers-count">
                        <?php
                        $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'volunteer'";
                        $result = mysqli_query($conn, $sql);
                        $row = mysqli_fetch_assoc($result);
                        echo number_format($row['total']);
                        ?>
                    </div>
                    <div class="stat-label">注册志愿者</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="projects-count">
                        <?php
                        $sql = "SELECT COUNT(*) as total FROM projects";
                        $result = mysqli_query($conn, $sql);
                        $row = mysqli_fetch_assoc($result);
                        echo number_format($row['total']);
                        ?>
                    </div>
                    <div class="stat-label">公益项目</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="hours-count">
                        <?php
                        $sql = "SELECT SUM(hours) as total FROM registrations WHERE status = '已完成'";
                        $result = mysqli_query($conn, $sql);
                        $row = mysqli_fetch_assoc($result);
                        echo number_format($row['total'] ?? 0);
                        ?>
                    </div>
                    <div class="stat-label">志愿服务小时</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="regions-count">32</div>
                    <div class="stat-label">覆盖省市</div>
                </div>
            </div>
        </div>
    </section>

    <!-- 底部信息 -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html> 