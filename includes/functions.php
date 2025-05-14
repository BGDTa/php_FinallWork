<?php
/**
 * 爱心联萌公益志愿平台通用函数文件
 */

/**
 * 检查用户是否已登录
 * @return bool 已登录返回true，否则返回false
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * 检查用户是否是管理员
 * @return bool 是管理员返回true，否则返回false
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * 检查用户是否是机构
 * @return bool 是机构返回true，否则返回false
 */
function is_organization() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'organization';
}

/**
 * 生成随机字符串
 * @param int $length 字符串长度
 * @return string 随机字符串
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * 格式化日期时间
 * @param string $datetime 日期时间字符串
 * @param string $format 格式化模式
 * @return string 格式化后的日期时间字符串
 */
function format_datetime($datetime, $format = 'Y-m-d H:i') {
    $date = new DateTime($datetime);
    return $date->format($format);
}

/**
 * 计算时间差（多久前）
 * @param string $datetime 日期时间字符串
 * @return string 格式化后的时间差
 */
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => '年',
        'm' => '个月',
        'w' => '周',
        'd' => '天',
        'h' => '小时',
        'i' => '分钟',
        's' => '秒',
    );

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$string) {
        return '刚刚';
    }

    return current($string) . '前';
}

/**
 * 敏感词过滤
 * @param string $content 需要过滤的内容
 * @return string 过滤后的内容
 */
function filter_sensitive_words($content) {
    // 简单的敏感词列表示例，实际项目中可以从数据库加载
    $sensitive_words = array('暴力', '色情', '赌博', '诈骗');
    
    // 替换为星号
    foreach ($sensitive_words as $word) {
        $stars = str_repeat('*', mb_strlen($word, 'UTF-8'));
        $content = str_replace($word, $stars, $content);
    }
    
    return $content;
}

/**
 * 获取用户爱心值
 * @param int $user_id 用户ID
 * @return int 爱心值
 */
function get_user_points($user_id) {
    global $conn;
    
    $sql = "SELECT points FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['points'];
    }
    
    return 0;
}

/**
 * 更新用户爱心值
 * @param int $user_id 用户ID
 * @param int $points 增加的爱心值（可为负数）
 * @return bool 操作结果
 */
function update_user_points($user_id, $points) {
    global $conn;
    
    $sql = "UPDATE users SET points = points + ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $points, $user_id);
    
    if ($stmt->execute()) {
        // 记录积分变动日志
        $sql = "INSERT INTO point_logs (user_id, points, action, created_at) 
                VALUES (?, ?, ?, NOW())";
        $action = $points > 0 ? '增加' : '减少';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $user_id, abs($points), $action);
        $stmt->execute();
        
        return true;
    }
    
    return false;
}

/**
 * 获取用户信息
 * @param int $user_id 用户ID
 * @return array|null 用户信息数组或null
 */
function get_user_info($user_id) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row;
    }
    
    return null;
}

/**
 * 检查项目状态并更新
 * 如果项目开始日期已经过去，状态为"招募中"，则更新为"进行中"
 * 如果项目结束日期已经过去，状态为"招募中"或"进行中"，则更新为"已结束"
 * 保留其他状态（待审核、草稿、已取消）不变
 */
function check_project_status() {
    global $conn;
    
    // 更新已开始的项目
    $sql = "UPDATE projects SET status = '进行中' 
            WHERE status = '招募中' AND start_date <= CURDATE()";
    $conn->query($sql);
    
    // 更新已结束的项目 - 只更新招募中和进行中的项目
    $sql = "UPDATE projects SET status = '已结束' 
            WHERE status IN ('招募中', '进行中') AND end_date < CURDATE()";
    $conn->query($sql);
}

/**
 * 设置消息提醒
 * @param string $message 消息内容
 * @param string $type 消息类型（success, error, warning, info）
 */
function set_message($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

/**
 * 显示消息提醒
 * @return string HTML消息提醒
 */
function display_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info';
        
        // 清除会话中的消息
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        
        return '<div class="alert alert-' . $type . '">' . $message . '</div>';
    }
    
    return '';
}

/**
 * 分页函数
 * @param int $total 总记录数
 * @param int $limit 每页显示数量
 * @param int $page 当前页码
 * @param string $url 链接URL
 * @return string HTML分页导航
 */
function pagination($total, $limit, $page, $url = '?') {
    $total_pages = ceil($total / $limit);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<ul class="pagination">';
    
    // 上一页
    if ($page > 1) {
        $html .= '<li><a href="' . $url . 'page=' . ($page - 1) . '">&laquo; 上一页</a></li>';
    } else {
        $html .= '<li class="disabled"><span>&laquo; 上一页</span></li>';
    }
    
    // 页码链接
    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);
    
    if ($start > 1) {
        $html .= '<li><a href="' . $url . 'page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="disabled"><span>...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page) {
            $html .= '<li class="active"><span>' . $i . '</span></li>';
        } else {
            $html .= '<li><a href="' . $url . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<li class="disabled"><span>...</span></li>';
        }
        $html .= '<li><a href="' . $url . 'page=' . $total_pages . '">' . $total_pages . '</a></li>';
    }
    
    // 下一页
    if ($page < $total_pages) {
        $html .= '<li><a href="' . $url . 'page=' . ($page + 1) . '">下一页 &raquo;</a></li>';
    } else {
        $html .= '<li class="disabled"><span>下一页 &raquo;</span></li>';
    }
    
    $html .= '</ul>';
    
    return $html;
}

/**
 * 图片上传函数
 * @param array $file $_FILES['file']
 * @param string $target_dir 目标目录
 * @param array $allowed_types 允许的文件类型
 * @param int $max_size 最大文件大小（字节）
 * @return string|false 成功返回文件路径，失败返回false
 */
function upload_image($file, $target_dir = 'assets/img/uploads/', $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 2097152) {
    // 检查文件是否上传成功
    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }
    
    // 检查文件上传错误
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return false; // 文件太大
        default:
            return false; // 其他错误
    }
    
    // 检查文件大小
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // 检查MIME类型
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $file_type = $finfo->file($file['tmp_name']);
    $ext = array_search(
        $file_type,
        array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ),
        true
    );
    
    if ($ext === false) {
        return false;
    }
    
    // 创建唯一文件名
    $file_name = md5(uniqid(rand(), true)) . '.' . $ext;
    $target_file = $target_dir . $file_name;
    
    // 确保目标目录存在
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // 移动上传的文件
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $target_file;
    }
    
    return false;
}
?> 