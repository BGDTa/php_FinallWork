<?php
/**
 * 爱心联萌公益志愿信息平台数据库初始化脚本
 */

// 数据库连接配置
$servername = "localhost";
$username = "root";
$password = "123456";

// 创建连接
$conn = new mysqli($servername, $username, $password);

// 检查连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 创建数据库
$sql = "CREATE DATABASE IF NOT EXISTS volunteer_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "数据库创建成功！<br>";
} else {
    echo "创建数据库时出错: " . $conn->error . "<br>";
}

// 选择数据库
$conn->select_db("volunteer_platform");

// 创建用户表
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('volunteer', 'organization', 'admin') NOT NULL DEFAULT 'volunteer',
    phone VARCHAR(20),
    avatar VARCHAR(255),
    points INT(11) NOT NULL DEFAULT 0,
    real_name VARCHAR(50),
    id_card VARCHAR(20),
    gender ENUM('男', '女', '其他') NOT NULL DEFAULT '其他',
    birthday DATE,
    address TEXT,
    organization_name VARCHAR(100),
    organization_intro TEXT,
    organization_license VARCHAR(255),
    status ENUM('待审核', '已审核', '已拒绝') NOT NULL DEFAULT '待审核',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (username),
    UNIQUE KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "用户表创建成功！<br>";
} else {
    echo "创建用户表时出错: " . $conn->error . "<br>";
}

// 创建项目表
$sql = "CREATE TABLE IF NOT EXISTS projects (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    cover_image VARCHAR(255),
    organization_id INT(11) UNSIGNED NOT NULL,
    location VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    quota INT(11) NOT NULL DEFAULT 0,
    registered INT(11) NOT NULL DEFAULT 0,
    requirements TEXT,
    benefits TEXT,
    contact_name VARCHAR(50) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    contact_email VARCHAR(100) NOT NULL,
    status ENUM('草稿', '待审核', '招募中', '进行中', '已结束', '已取消') NOT NULL DEFAULT '待审核',
    video_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "项目表创建成功！<br>";
} else {
    echo "创建项目表时出错: " . $conn->error . "<br>";
}

// 创建报名表
$sql = "CREATE TABLE IF NOT EXISTS registrations (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT(11) UNSIGNED NOT NULL,
    user_id INT(11) UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    id_card VARCHAR(20),
    hours INT(11) NOT NULL DEFAULT 0,
    status ENUM('待审核', '已通过', '已拒绝', '已参与', '已完成', '已取消') NOT NULL DEFAULT '待审核',
    feedback TEXT,
    rating INT(1) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "报名表创建成功！<br>";
} else {
    echo "创建报名表时出错: " . $conn->error . "<br>";
}

// 创建多媒体表
$sql = "CREATE TABLE IF NOT EXISTS media (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT(11) UNSIGNED,
    story_id INT(11) UNSIGNED,
    course_id INT(11) UNSIGNED,
    type ENUM('image', 'video', 'audio', 'document') NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (project_id),
    INDEX (story_id),
    INDEX (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "多媒体表创建成功！<br>";
} else {
    echo "创建多媒体表时出错: " . $conn->error . "<br>";
}

// 创建故事表
$sql = "CREATE TABLE IF NOT EXISTS stories (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    project_id INT(11) UNSIGNED,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    cover_image VARCHAR(255),
    category_id VARCHAR(50) NOT NULL DEFAULT '其他',
    status ENUM('待审核', '已审核', '已拒绝') NOT NULL DEFAULT '待审核',
    views INT(11) NOT NULL DEFAULT 0,
    likes INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "故事表创建成功！<br>";
} else {
    echo "创建故事表时出错: " . $conn->error . "<br>";
}

// 创建评论表
$sql = "CREATE TABLE IF NOT EXISTS comments (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    story_id INT(11) UNSIGNED,
    project_id INT(11) UNSIGNED,
    content TEXT NOT NULL,
    status ENUM('待审核', '已审核', '已拒绝') NOT NULL DEFAULT '待审核',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "评论表创建成功！<br>";
} else {
    echo "创建评论表时出错: " . $conn->error . "<br>";
}

// 创建公益课堂表
$sql = "CREATE TABLE IF NOT EXISTS courses (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    cover_image VARCHAR(255) NOT NULL,
    video_url VARCHAR(255),
    duration INT(11) NOT NULL DEFAULT 0,
    author_id INT(11) UNSIGNED NOT NULL,
    category_id VARCHAR(50) NOT NULL DEFAULT 'basic',
    views INT(11) NOT NULL DEFAULT 0,
    status ENUM('待发布', '已发布', '已下线') NOT NULL DEFAULT '待发布',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "公益课堂表创建成功！<br>";
} else {
    echo "创建公益课堂表时出错: " . $conn->error . "<br>";
}

// 创建积分记录表
$sql = "CREATE TABLE IF NOT EXISTS point_logs (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    points INT(11) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "积分记录表创建成功！<br>";
} else {
    echo "创建积分记录表时出错: " . $conn->error . "<br>";
}

// 创建打卡记录表
$sql = "CREATE TABLE IF NOT EXISTS checkins (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, checkin_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "打卡记录表创建成功！<br>";
} else {
    echo "创建打卡记录表时出错: " . $conn->error . "<br>";
}

// 创建系统管理员账户
$admin_username = "admin";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);
$admin_email = "admin@aixinlianmeng.org";

$sql = "INSERT INTO users (username, password, email, role, status) 
        VALUES ('$admin_username', '$admin_password', '$admin_email', 'admin', '已审核')
        ON DUPLICATE KEY UPDATE username=username";

if ($conn->query($sql) === TRUE) {
    echo "管理员账户创建成功！<br>";
} else {
    echo "创建管理员账户时出错: " . $conn->error . "<br>";
}

// 创建演示数据 - 机构账号
$org_username = "love_org";
$org_password = password_hash("org123", PASSWORD_DEFAULT);
$org_email = "org@aixinlianmeng.org";

$sql = "INSERT INTO users (username, password, email, role, status, organization_name, organization_intro) 
        VALUES ('$org_username', '$org_password', '$org_email', 'organization', '已审核', '爱心公益组织', '致力于关爱儿童、老人和弱势群体的非盈利组织')
        ON DUPLICATE KEY UPDATE username=username";

if ($conn->query($sql) === TRUE) {
    echo "演示机构账户创建成功！<br>";
} else {
    echo "创建演示机构账户时出错: " . $conn->error . "<br>";
}

// 创建演示数据 - 志愿者账号
$volunteer_username = "volunteer001";
$volunteer_password = password_hash("vol123", PASSWORD_DEFAULT);
$volunteer_email = "volunteer@aixinlianmeng.org";

$sql = "INSERT INTO users (username, password, email, role, status, real_name, points) 
        VALUES ('$volunteer_username', '$volunteer_password', '$volunteer_email', 'volunteer', '已审核', '张志愿', 100)
        ON DUPLICATE KEY UPDATE username=username";

if ($conn->query($sql) === TRUE) {
    echo "演示志愿者账户创建成功！<br>";
} else {
    echo "创建演示志愿者账户时出错: " . $conn->error . "<br>";
}

// 创建演示数据 - 项目
$org_id_query = "SELECT id FROM users WHERE username = 'love_org'";
$result = $conn->query($org_id_query);
$org_id = ($result->num_rows > 0) ? $result->fetch_assoc()['id'] : 1;

$sql = "INSERT INTO projects (title, description, organization_id, location, start_date, end_date, quota, status, contact_name, contact_phone, contact_email) 
        VALUES ('关爱山区儿童公益行', '为山区儿童送去学习用品和关爱，组织志愿者前往山区学校开展支教活动。', $org_id, '四川省阿坝藏族羌族自治州', '2023-06-01', '2023-06-10', 20, '招募中', '李主任', '13800138000', 'project@aixinlianmeng.org')
        ";

if ($conn->query($sql) === TRUE) {
    echo "演示项目1创建成功！<br>";
} else {
    echo "创建演示项目1时出错: " . $conn->error . "<br>";
}

$sql = "INSERT INTO projects (title, description, organization_id, location, start_date, end_date, quota, status, contact_name, contact_phone, contact_email) 
        VALUES ('城市环保清洁日', '组织志愿者在城市公园和河畔进行垃圾清理活动，提高市民环保意识。', $org_id, '北京市海淀区', '2023-05-20', '2023-05-20', 50, '已结束', '王组长', '13900139000', 'project@aixinlianmeng.org')
        ";

if ($conn->query($sql) === TRUE) {
    echo "演示项目2创建成功！<br>";
} else {
    echo "创建演示项目2时出错: " . $conn->error . "<br>";
}

// 创建演示数据 - 故事
$volunteer_id_query = "SELECT id FROM users WHERE username = 'volunteer001'";
$result = $conn->query($volunteer_id_query);
$volunteer_id = ($result->num_rows > 0) ? $result->fetch_assoc()['id'] : 2;

$project_id_query = "SELECT id FROM projects WHERE title = '城市环保清洁日'";
$result = $conn->query($project_id_query);
$project_id = ($result->num_rows > 0) ? $result->fetch_assoc()['id'] : 2;

$sql = "INSERT INTO stories (user_id, project_id, title, content, status) 
        VALUES ($volunteer_id, $project_id, '我的第一次志愿服务经历', '那天，我和其他几十位志愿者一起来到了北京市海淀区的公园，开始了清理垃圾的活动。虽然天气很热，但看到自己的努力让公园变得更加干净，心里特别有成就感。希望有更多人加入到环保志愿服务中来！', '已审核')
        ";

if ($conn->query($sql) === TRUE) {
    echo "演示故事创建成功！<br>";
} else {
    echo "创建演示故事时出错: " . $conn->error . "<br>";
}

// 创建演示数据 - 公益课堂
$sql = "INSERT INTO courses (title, description, cover_image, duration, author_id, status) 
        VALUES ('志愿服务基础知识', '本课程介绍志愿服务的基本概念、类型以及参与方式，适合新手志愿者学习。', 'https://source.unsplash.com/random/800x450/?teaching', 45, $org_id, '已发布')
        ";

if ($conn->query($sql) === TRUE) {
    echo "演示课程创建成功！<br>";
} else {
    echo "创建演示课程时出错: " . $conn->error . "<br>";
}

echo "<br>数据库初始化完成！";

// 关闭连接
$conn->close();
?> 