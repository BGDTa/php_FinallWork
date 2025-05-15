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

$sql = "INSERT INTO projects (title, description,cover_image, organization_id, location, start_date, end_date, quota, status, contact_name, contact_phone, contact_email) 
        VALUES ('关爱山区儿童公益行', '为山区儿童送去学习用品和关爱，组织志愿者前往山区学校开展支教活动。','https://tse3-mm.cn.bing.net/th/id/OIP-C.n9_W8vF53gdnJVxAuX1ZNwHaDe?w=305&h=164&c=7&r=0&o=5&dpr=1.3&pid=1.7', $org_id, '四川省阿坝藏族羌族自治州', '2023-06-01', '2023-06-10', 20, '招募中', '李主任', '13800138000', 'project@aixinlianmeng.org')
        ";

if ($conn->query($sql) === TRUE) {
    echo "演示项目1创建成功！<br>";
} else {
    echo "创建演示项目1时出错: " . $conn->error . "<br>";
}

$sql = "INSERT INTO projects (title, description,cover_image, organization_id, location, start_date, end_date, quota, status, contact_name, contact_phone, contact_email) 
        VALUES ('城市环保清洁日', '组织志愿者在城市公园和河畔进行垃圾清理活动，提高市民环保意识。','https://tse3-mm.cn.bing.net/th/id/OIP-C.8dwc8Ub0mkZ-dAqA4_4aVwHaHa?w=170&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7', $org_id, '北京市海淀区', '2023-05-20', '2023-05-20', 50, '已结束', '王组长', '13900139000', 'project@aixinlianmeng.org')
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
        VALUES ($volunteer_id, $project_id, $cover_image['https://tse3-mm.cn.bing.net/th/id/OIP-C.ktiGtZ0QHam2i7SGOOXYDwHaEh?w=278&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7'],'我的第一次志愿服务经历', '那天，我和其他几十位志愿者一起来到了北京市海淀区的公园，开始了清理垃圾的活动。虽然天气很热，但看到自己的努力让公园变得更加干净，心里特别有成就感。希望有更多人加入到环保志愿服务中来！', '已审核')
        ";

if ($conn->query($sql) === TRUE) {
    echo "演示故事创建成功！<br>";
} else {
    echo "创建演示故事时出错: " . $conn->error . "<br>";
}

// 创建演示数据 - 公益课堂
$sql = "INSERT INTO courses (title, description, cover_image, duration, author_id, status) 
        VALUES ('志愿服务基础知识', '本课程介绍志愿服务的基本概念、类型以及参与方式，适合新手志愿者学习。', 'https://tse4-mm.cn.bing.net/th/id/OIP-C.RH-2I5zFkdGp9gxZTOUO5QHaEK?w=302&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7', 45, $org_id, '已发布')
        ";

if ($conn->query($sql) === TRUE) {
    echo "演示课程创建成功！<br>";
} else {
    echo "创建演示课程时出错: " . $conn->error . "<br>";
}

echo "<br>数据库初始化完成！";

// 关闭连接
$conn->close();

// 重新连接数据库以添加更多示例数据
$conn = new mysqli($servername, $username, $password, "volunteer_platform");
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

echo "<h2>开始添加更多示例数据</h2>";

// 添加更多志愿者用户
$volunteer_data = [
    ['volunteer002', '李志愿', '女', '13811223344', 'v2@example.com', '2000-05-15'],
    ['volunteer003', '王小明', '男', '13922334455', 'v3@example.com', '1995-11-22'],
    ['volunteer004', '赵丽华', '女', '13633445566', 'v4@example.com', '1998-03-08'],
    ['volunteer005', '刘大伟', '男', '13744556677', 'v5@example.com', '1992-07-19']
];

foreach ($volunteer_data as $v) {
    $v_username = $v[0];
    $v_password = password_hash($v[0]."123", PASSWORD_DEFAULT);
    $v_real_name = $v[1];
    $v_gender = $v[2];
    $v_phone = $v[3];
    $v_email = $v[4];
    $v_birthday = $v[5];
    $v_points = rand(20, 200);
    
    $sql = "INSERT INTO users (username, password, email, role, status, real_name, phone, gender, birthday, points) 
            VALUES ('$v_username', '$v_password', '$v_email', 'volunteer', '已审核', '$v_real_name', '$v_phone', '$v_gender', '$v_birthday', $v_points)
            ON DUPLICATE KEY UPDATE username=username";
    
    if ($conn->query($sql) === TRUE) {
        echo "志愿者账户 $v_username 创建成功！<br>";
    } else {
        echo "创建志愿者账户 $v_username 时出错: " . $conn->error . "<br>";
    }
}

// 添加更多组织机构
$org_data = [
    ['green_earth', '绿色地球', '专注于环保工作的公益组织', '13888889999', 'green@example.com'],
    ['child_care', '儿童关爱中心', '致力于儿童教育和福利的公益组织', '13777778888', 'child@example.com'],
    ['elder_help', '银发之家', '关爱老年人生活与健康的社会组织', '13666667777', 'elder@example.com']
];

foreach ($org_data as $o) {
    $o_username = $o[0];
    $o_password = password_hash($o[0]."123", PASSWORD_DEFAULT);
    $o_name = $o[1];
    $o_intro = $o[2];
    $o_phone = $o[3];
    $o_email = $o[4];
    
    $sql = "INSERT INTO users (username, password, email, role, status, organization_name, organization_intro, phone) 
            VALUES ('$o_username', '$o_password', '$o_email', 'organization', '已审核', '$o_name', '$o_intro', '$o_phone')
            ON DUPLICATE KEY UPDATE username=username";
    
    if ($conn->query($sql) === TRUE) {
        echo "组织机构账户 $o_username 创建成功！<br>";
    } else {
        echo "创建组织机构账户 $o_username 时出错: " . $conn->error . "<br>";
    }
}

// 获取所有组织ID
$org_ids = [];
$result = $conn->query("SELECT id, username FROM users WHERE role = 'organization'");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $org_ids[$row['username']] = $row['id'];
    }
}

// 添加更多项目
$project_data = [
    ['社区老人关爱行动', '组织志愿者定期走访社区独居老人，提供生活帮助和情感陪伴',$cover_image['https://tse2-mm.cn.bing.net/th/id/OIP-C.arn74QS8NvsGRMF9BLvetAHaDt?w=327&h=175&c=7&r=0&o=5&dpr=1.3&pid=1.7'], $org_ids['elder_help'], '上海市徐汇区', '2023-07-15', '2023-12-20', 15, '招募中'],
    ['城市河道清洁计划', '定期清理城市河道垃圾，美化水环境', $cover_image['https://tse1-mm.cn.bing.net/th/id/OIP-C.R7bAX2jcM5JmXkjDmFlJmgHaE8?w=235&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7'], $org_ids['green_earth'], '广州市天河区', '2023-06-18', '2023-06-19', 30, '已结束'],
    ['乡村儿童阅读推广', '为乡村学校捐赠图书，并组织志愿者进行阅读指导',$cover_image['https://tse1-mm.cn.bing.net/th/id/OIP-C._KnVfN3yau1-YLa1w556lwHaFj?w=244&h=183&c=7&r=0&o=5&dpr=1.3&pid=1.7'], $org_ids['child_care'], '云南省大理市', '2023-08-10', '2023-08-20', 25, '招募中'],
    ['城市公园植树活动', '在城市公园举行植树活动，改善城市生态环境', $cover_image['https://tse4-mm.cn.bing.net/th/id/OIP-C.jXf2V97d3aVy3gLg-6vgYgHaE7?w=243&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7'], $org_ids['green_earth'], '深圳市南山区', '2023-09-01', '2023-09-01', 40, '待审核'],
    ['特殊儿童关爱日', '为特殊儿童提供一对一陪伴和互动游戏', $cover_image['https://tse4-mm.cn.bing.net/th/id/OIP-C.cLlsfI-jyc2zmYETqiamdAHaE7?w=299&h=199&c=7&r=0&o=5&dpr=1.3&pid=1.7'], $org_ids['child_care'], '北京市朝阳区', '2023-10-12', '2023-10-12', 20, '待审核']
];

foreach ($project_data as $p) {
    $title = $p[0];
    $description = $p[1];
    $org_id = $p[2];
    $location = $p[3];
    $start_date = $p[4];
    $end_date = $p[5];
    $quota = $p[6];
    $status = $p[7];
    $contact_name = "联系人".rand(1,100);
    $contact_phone = "139".rand(10000000, 99999999);
    $contact_email = "contact".rand(1,100)."@example.com";
    
    $sql = "INSERT INTO projects (title, description, organization_id, location, start_date, end_date, quota, status, contact_name, contact_phone, contact_email) 
            VALUES ('$title', '$description', $org_id, '$location', '$start_date', '$end_date', $quota, '$status', '$contact_name', '$contact_phone', '$contact_email')";
    
    if ($conn->query($sql) === TRUE) {
        echo "项目 '$title' 创建成功！<br>";
    } else {
        echo "创建项目 '$title' 时出错: " . $conn->error . "<br>";
    }
}

// 获取所有志愿者ID
$volunteer_ids = [];
$result = $conn->query("SELECT id, username FROM users WHERE role = 'volunteer'");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $volunteer_ids[$row['username']] = $row['id'];
    }
}

// 获取所有项目ID
$project_ids = [];
$result = $conn->query("SELECT id, title FROM projects");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $project_ids[$row['title']] = $row['id'];
    }
}

// 添加项目报名记录
$registration_data = [
    [$project_ids['关爱山区儿童公益行'], $volunteer_ids['volunteer001'], '已通过', 0],
    [$project_ids['关爱山区儿童公益行'], $volunteer_ids['volunteer002'], '已参与', 8],
    [$project_ids['城市环保清洁日'], $volunteer_ids['volunteer001'], '已完成', 6],
    [$project_ids['城市环保清洁日'], $volunteer_ids['volunteer003'], '已完成', 6],
    [$project_ids['社区老人关爱行动'], $volunteer_ids['volunteer002'], '待审核', 0],
    [$project_ids['社区老人关爱行动'], $volunteer_ids['volunteer004'], '已通过', 0],
    [$project_ids['乡村儿童阅读推广'], $volunteer_ids['volunteer003'], '待审核', 0],
    [$project_ids['乡村儿童阅读推广'], $volunteer_ids['volunteer005'], '待审核', 0],
    [$project_ids['城市河道清洁计划'], $volunteer_ids['volunteer001'], '已完成', 4],
    [$project_ids['城市河道清洁计划'], $volunteer_ids['volunteer004'], '已完成', 4]
];

foreach ($registration_data as $r) {
    $project_id = $r[0];
    $user_id = $r[1];
    $status = $r[2];
    $hours = $r[3];
    
    // 获取用户信息
    $user_info = $conn->query("SELECT real_name, phone, email FROM users WHERE id = $user_id")->fetch_assoc();
    $name = $user_info['real_name'];
    $phone = $user_info['phone'];
    $email = $user_info['email'];
    
    $sql = "INSERT INTO registrations (project_id, user_id, name, phone, email, status, hours) 
            VALUES ($project_id, $user_id, '$name', '$phone', '$email', '$status', $hours)";
    
    if ($conn->query($sql) === TRUE) {
        echo "项目报名记录创建成功！<br>";
    } else {
        echo "创建项目报名记录时出错: " . $conn->error . "<br>";
    }
}

// 添加故事
$story_data = [
    [$volunteer_ids['volunteer002'], $project_ids['城市环保清洁日'],$cover_image['https://tse4-mm.cn.bing.net/th/id/OIP-C.kOXexSP6fYEcs6l7WIfHJgHaDV?w=303&h=157&c=7&r=0&o=5&dpr=1.3&pid=1.7'], '环保志愿者的一天', '参加城市环保清洁日的经历让我深受感动。看到那么多志愿者一起努力，让我们的城市变得更加干净美丽。', '已审核'],
    [$volunteer_ids['volunteer003'], $project_ids['城市河道清洁计划'],$cover_image['https://tse3-mm.cn.bing.net/th/id/OIP-C.-6aIaBcFvQXejgpyEbdSWgHaFj?w=248&h=186&c=7&r=0&o=5&dpr=1.3&pid=1.7'], '守护城市的蓝色血脉', '河道清洁工作虽然辛苦，但看到清澈的河水和市民赞许的目光，一切都值得了。', '已审核'],
    [$volunteer_ids['volunteer004'], $project_ids['社区老人关爱行动'],$cover_image['https://tse2-mm.cn.bing.net/th/id/OIP-C.f1AEtl-34ofeS7IaXtsi2AHaFj?w=225&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7'], '与老人们的温暖时光', '陪伴社区老人的过程中，我收获了许多人生智慧，也感受到了人与人之间的真情实意。', '待审核'],
    [$volunteer_ids['volunteer001'], null, $cover_image['https://tse2-mm.cn.bing.net/th/id/OIP-C.hq1aknX5eNI5lXwtJaBd0AHaE8?w=276&h=184&c=7&r=0&o=5&dpr=1.3&pid=1.7'],'我的志愿服务之路', '从大学开始参与志愿服务至今，已经有五年时间，每一次服务都是一次成长。', '已审核']
];

foreach ($story_data as $s) {
    $user_id = $s[0];
    $project_id = $s[1] ? $s[1] : "NULL";
    $title = $s[2];
    $content = $s[3];
    $status = $s[4];
    
    $sql = "INSERT INTO stories (user_id, " . ($s[1] ? "project_id, " : "") . "title, content, status) 
            VALUES ($user_id, " . ($s[1] ? "$project_id, " : "") . "'$title', '$content', '$status')";
    
    if ($conn->query($sql) === TRUE) {
        echo "故事 '$title' 创建成功！<br>";
    } else {
        echo "创建故事 '$title' 时出错: " . $conn->error . "<br>";
    }
}

// 获取所有故事ID
$story_ids = [];
$result = $conn->query("SELECT id, title FROM stories");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $story_ids[$row['title']] = $row['id'];
    }
}

// 添加评论
$comment_data = [
    [$volunteer_ids['volunteer002'], $story_ids['我的第一次志愿服务经历'], null, '很棒的分享，我也有类似的经历！', '已审核'],
    [$volunteer_ids['volunteer003'], $story_ids['我的第一次志愿服务经历'], null, '希望有机会一起参加下次活动！', '已审核'],
    [$volunteer_ids['volunteer001'], $story_ids['环保志愿者的一天'], null, '保护环境，从我做起！', '已审核'],
    [$volunteer_ids['volunteer004'], null, $project_ids['关爱山区儿童公益行'], '这个项目非常有意义，期待参与！', '待审核'],
    [$volunteer_ids['volunteer005'], null, $project_ids['乡村儿童阅读推广'], '我有很多适合儿童的图书可以捐赠', '已审核']
];

foreach ($comment_data as $c) {
    $user_id = $c[0];
    $story_id = $c[1] ? $c[1] : "NULL";
    $project_id = $c[2] ? $c[2] : "NULL";
    $content = $c[3];
    $status = $c[4];
    
    $sql = "INSERT INTO comments (user_id, " . ($c[1] ? "story_id, " : "") . ($c[2] ? "project_id, " : "") . "content, status) 
            VALUES ($user_id, " . ($c[1] ? "$story_id, " : "") . ($c[2] ? "$project_id, " : "") . "'$content', '$status')";
    
    if ($conn->query($sql) === TRUE) {
        echo "评论创建成功！<br>";
    } else {
        echo "创建评论时出错: " . $conn->error . "<br>";
    }
}

// 添加公益课堂
$course_data = [
    ['环保知识入门', '本课程介绍基本环保知识，垃圾分类方法和环保行动指南', 'https://tse3-mm.cn.bing.net/th/id/OIP-C.cea0GHSispRcciWd0P83fAHaEK?w=333&h=187&c=7&r=0&o=5&dpr=1.3&pid=1.7', 30, $org_ids['green_earth'], '已发布'],
    ['志愿服务心理辅导', '如何在志愿服务过程中进行心理调适和情绪管理', 'https://tse3-mm.cn.bing.net/th/id/OIP-C.ZubiIzjynnHMePQ3_fRFZAHaHO?w=179&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7', 60, $org_ids['love_org'], '已发布'],
    ['儿童教育互动技巧', '与儿童互动的有效方法和技巧分享', 'https://tse1-mm.cn.bing.net/th/id/OIP-C.x0UxK-lM20WjApfMEypRlAHaE8?w=286&h=191&c=7&r=0&o=5&dpr=1.3&pid=1.7', 45, $org_ids['child_care'], '待发布'],
    ['老年人护理基础', '基础的老年人护理知识和注意事项', 'https://tse3-mm.cn.bing.net/th/id/OIP-C.yFyyAFcn7x7jWo6QIM3IFAHaFj?w=237&h=180&c=7&r=0&o=5&dpr=1.3&pid=1.7', 50, $org_ids['elder_help'], '已发布']
];

foreach ($course_data as $c) {
    $title = $c[0];
    $description = $c[1];
    $cover_image = $c[2];
    $duration = $c[3];
    $author_id = $c[4];
    $status = $c[5];
    
    $sql = "INSERT INTO courses (title, description, cover_image, duration, author_id, status) 
            VALUES ('$title', '$description', '$cover_image', $duration, $author_id, '$status')";
    
    if ($conn->query($sql) === TRUE) {
        echo "课程 '$title' 创建成功！<br>";
    } else {
        echo "创建课程 '$title' 时出错: " . $conn->error . "<br>";
    }
}

// 获取所有课程ID
$course_ids = [];
$result = $conn->query("SELECT id, title FROM courses");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $course_ids[$row['title']] = $row['id'];
    }
}

// 添加多媒体
$media_data = [
    [$project_ids['关爱山区儿童公益行'], null, null, 'image', '山区儿童活动照片1', '活动现场照片', 'uploads/images/mountain_children1.jpg'],
    [$project_ids['关爱山区儿童公益行'], null, null, 'image', '山区儿童活动照片2', '与孩子们的合影', 'uploads/images/mountain_children2.jpg'],
    [$project_ids['城市环保清洁日'], null, null, 'image', '环保活动照片', '志愿者清理垃圾', 'uploads/images/cleaning1.jpg'],
    [$project_ids['城市环保清洁日'], null, null, 'video', '环保活动视频', '活动纪实', 'uploads/videos/cleaning_event.mp4'],
    [null, $story_ids['环保志愿者的一天'], null, 'image', '志愿者合影', '活动结束后的团队合影', 'uploads/images/volunteers_group.jpg'],
    [null, null, $course_ids['环保知识入门'], 'document', '环保手册', '环保知识要点总结', 'uploads/documents/environment_guide.pdf'],
    [null, null, $course_ids['志愿服务心理辅导'], 'audio', '冥想指导音频', '志愿者减压音频', 'uploads/audio/meditation.mp3']
];

foreach ($media_data as $m) {
    $project_id = $m[0] ? $m[0] : "NULL";
    $story_id = $m[1] ? $m[1] : "NULL";
    $course_id = $m[2] ? $m[2] : "NULL";
    $type = $m[3];
    $title = $m[4];
    $description = $m[5];
    $file_path = $m[6];
    
    $sql = "INSERT INTO media (project_id, story_id, course_id, type, title, description, file_path) 
            VALUES (" . ($m[0] ? "$project_id" : "NULL") . ", " . ($m[1] ? "$story_id" : "NULL") . ", " . ($m[2] ? "$course_id" : "NULL") . ", '$type', '$title', '$description', '$file_path')";
    
    if ($conn->query($sql) === TRUE) {
        echo "多媒体 '$title' 创建成功！<br>";
    } else {
        echo "创建多媒体 '$title' 时出错: " . $conn->error . "<br>";
    }
}

// 添加积分记录
$point_log_data = [
    [$volunteer_ids['volunteer001'], 10, '签到', '每日签到奖励'],
    [$volunteer_ids['volunteer001'], 50, '完成志愿服务', '参与城市环保清洁日活动'],
    [$volunteer_ids['volunteer002'], 10, '签到', '每日签到奖励'],
    [$volunteer_ids['volunteer002'], 40, '完成志愿服务', '参与城市河道清洁计划'],
    [$volunteer_ids['volunteer003'], 10, '签到', '每日签到奖励'],
    [$volunteer_ids['volunteer003'], 5, '发表评论', '在故事下发表评论'],
    [$volunteer_ids['volunteer004'], 10, '签到', '每日签到奖励'],
    [$volunteer_ids['volunteer004'], 30, '分享故事', '发布《与老人们的温暖时光》']
];

foreach ($point_log_data as $pl) {
    $user_id = $pl[0];
    $points = $pl[1];
    $action = $pl[2];
    $description = $pl[3];
    
    $sql = "INSERT INTO point_logs (user_id, points, action, description) 
            VALUES ($user_id, $points, '$action', '$description')";
    
    if ($conn->query($sql) === TRUE) {
        echo "积分记录创建成功！<br>";
    } else {
        echo "创建积分记录时出错: " . $conn->error . "<br>";
    }
}

// 添加打卡记录
$checkin_data = [
    [$volunteer_ids['volunteer001'], '2023-05-10'],
    [$volunteer_ids['volunteer001'], '2023-05-11'],
    [$volunteer_ids['volunteer001'], '2023-05-12'],
    [$volunteer_ids['volunteer002'], '2023-05-10'],
    [$volunteer_ids['volunteer002'], '2023-05-12'],
    [$volunteer_ids['volunteer003'], '2023-05-11'],
    [$volunteer_ids['volunteer004'], '2023-05-12']
];

foreach ($checkin_data as $c) {
    $user_id = $c[0];
    $checkin_date = $c[1];
    
    $sql = "INSERT INTO checkins (user_id, checkin_date) 
            VALUES ($user_id, '$checkin_date')
            ON DUPLICATE KEY UPDATE user_id=user_id";
    
    if ($conn->query($sql) === TRUE) {
        echo "打卡记录创建成功！<br>";
    } else {
        echo "创建打卡记录时出错: " . $conn->error . "<br>";
    }
}

echo "<h2>示例数据添加完成！</h2>";

// 关闭连接
$conn->close();
?> 