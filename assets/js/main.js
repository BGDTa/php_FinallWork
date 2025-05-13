/**
 * 爱心联萌公益志愿信息平台主要JavaScript文件
 */

document.addEventListener('DOMContentLoaded', function() {
    // 导航菜单切换
    const mobileToggle = document.querySelector('.mobile-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
        });
    }
    
    // 自动隐藏消息提醒
    const alertMessages = document.querySelectorAll('.alert');
    alertMessages.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
    
    // 回到顶部按钮
    const backToTopBtn = document.querySelector('.back-to-top');
    
    if (backToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });
        
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // 项目报名表单验证
    const registrationForm = document.getElementById('registration-form');
    
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(e) {
            let isValid = true;
            const name = document.getElementById('name');
            const phone = document.getElementById('phone');
            const email = document.getElementById('email');
            
            // 验证姓名
            if (name.value.trim() === '') {
                showError(name, '请输入姓名');
                isValid = false;
            } else {
                removeError(name);
            }
            
            // 验证手机号
            const phonePattern = /^1[3-9]\d{9}$/;
            if (!phonePattern.test(phone.value.trim())) {
                showError(phone, '请输入有效的手机号码');
                isValid = false;
            } else {
                removeError(phone);
            }
            
            // 验证邮箱
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email.value.trim())) {
                showError(email, '请输入有效的邮箱地址');
                isValid = false;
            } else {
                removeError(email);
            }
            
            // 如果表单无效，阻止提交
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // 显示表单错误信息
    function showError(input, message) {
        const formGroup = input.parentElement;
        formGroup.classList.add('error');
        
        let error = formGroup.querySelector('.error-message');
        if (!error) {
            error = document.createElement('div');
            error.className = 'error-message';
            formGroup.appendChild(error);
        }
        
        error.innerText = message;
    }
    
    // 移除表单错误信息
    function removeError(input) {
        const formGroup = input.parentElement;
        formGroup.classList.remove('error');
        
        const error = formGroup.querySelector('.error-message');
        if (error) {
            error.remove();
        }
    }

    // 爱心值统计数字动画
    const statNumbers = document.querySelectorAll('.stat-number');
    
    if (statNumbers.length > 0) {
        const options = {
            threshold: 0.5
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    const targetNumber = parseInt(target.innerText.replace(/,/g, ''), 10);
                    let count = 0;
                    const duration = 2000; // 动画持续时间（毫秒）
                    const interval = 30; // 更新间隔（毫秒）
                    const steps = duration / interval;
                    const increment = targetNumber / steps;
                    
                    const timer = setInterval(() => {
                        count += increment;
                        if (count >= targetNumber) {
                            target.innerText = targetNumber.toLocaleString();
                            clearInterval(timer);
                        } else {
                            target.innerText = Math.floor(count).toLocaleString();
                        }
                    }, interval);
                    
                    observer.unobserve(target);
                }
            });
        }, options);
        
        statNumbers.forEach(number => {
            observer.observe(number);
        });
    }
    
    // 项目卡片鼠标悬停效果
    const projectCards = document.querySelectorAll('.project-card');
    
    projectCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // 志愿者地图可视化
    const volunteerMap = document.getElementById('volunteer-map');
    
    if (volunteerMap) {
        // 获取地图数据（示例数据）
        const mapData = [
            { region: '北京', count: 120 },
            { region: '上海', count: 150 },
            { region: '广州', count: 90 },
            { region: '深圳', count: 80 },
            { region: '成都', count: 70 },
            { region: '武汉', count: 60 },
            { region: '杭州', count: 55 },
            { region: '南京', count: 50 }
        ];
        
        // 根据志愿者人数渲染各个地区的方块
        mapData.forEach(data => {
            const box = document.createElement('div');
            box.className = 'region-box';
            box.style.width = Math.min(100, 30 + data.count / 2) + 'px';
            box.style.height = Math.min(100, 30 + data.count / 2) + 'px';
            
            const label = document.createElement('span');
            label.className = 'region-label';
            label.innerText = data.region;
            
            const count = document.createElement('span');
            count.className = 'region-count';
            count.innerText = data.count + '人';
            
            box.appendChild(label);
            box.appendChild(count);
            volunteerMap.appendChild(box);
        });
    }
    
    // 公益打卡日历
    const checkinCalendar = document.getElementById('checkin-calendar');
    
    if (checkinCalendar) {
        // 获取当前月份的日期
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDay = new Date(year, month, 1).getDay();
        
        // 日历头部
        const calendarHeader = document.createElement('div');
        calendarHeader.className = 'calendar-header';
        
        const prevBtn = document.createElement('button');
        prevBtn.className = 'calendar-nav';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        
        const monthTitle = document.createElement('div');
        monthTitle.className = 'calendar-title';
        monthTitle.innerText = `${year}年${month + 1}月`;
        
        const nextBtn = document.createElement('button');
        nextBtn.className = 'calendar-nav';
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        
        calendarHeader.appendChild(prevBtn);
        calendarHeader.appendChild(monthTitle);
        calendarHeader.appendChild(nextBtn);
        
        // 周几标题
        const weekdays = ['日', '一', '二', '三', '四', '五', '六'];
        const weekdaysRow = document.createElement('div');
        weekdaysRow.className = 'calendar-weekdays';
        
        weekdays.forEach(day => {
            const dayElem = document.createElement('div');
            dayElem.className = 'calendar-weekday';
            dayElem.innerText = day;
            weekdaysRow.appendChild(dayElem);
        });
        
        // 日期格子
        const daysGrid = document.createElement('div');
        daysGrid.className = 'calendar-days';
        
        // 添加空白格子（月初前面的空白）
        for (let i = 0; i < firstDay; i++) {
            const blankDay = document.createElement('div');
            blankDay.className = 'calendar-day empty';
            daysGrid.appendChild(blankDay);
        }
        
        // 添加日期格子
        for (let i = 1; i <= daysInMonth; i++) {
            const dayElem = document.createElement('div');
            dayElem.className = 'calendar-day';
            dayElem.innerText = i;
            
            // 随机设置一些打卡日期（示例数据）
            if ([5, 8, 12, 15, 20, 25].includes(i)) {
                dayElem.classList.add('checked-in');
                dayElem.setAttribute('title', '已打卡');
            } else if (i < now.getDate()) {
                dayElem.classList.add('missed');
                dayElem.setAttribute('title', '未打卡');
            } else if (i === now.getDate()) {
                dayElem.classList.add('today');
                dayElem.setAttribute('title', '今日');
            }
            
            daysGrid.appendChild(dayElem);
        }
        
        // 将所有元素添加到日历容器
        checkinCalendar.appendChild(calendarHeader);
        checkinCalendar.appendChild(weekdaysRow);
        checkinCalendar.appendChild(daysGrid);
    }
}); 