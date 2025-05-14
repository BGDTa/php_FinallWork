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
                    const duration = 2000;
                    const interval = 30;
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
});