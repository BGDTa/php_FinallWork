/**
 * 爱心联萌公益志愿信息平台主要JavaScript文件
 */

document.addEventListener('DOMContentLoaded', function() {
    // 轮播图功能实现
    const slider = document.querySelector('.banner-slider');
    const slides = document.querySelectorAll('.banner-slider .slide');
    const dots = document.querySelectorAll('.slider-dot');
    const prevArrow = document.querySelector('.slider-arrow-left');
    const nextArrow = document.querySelector('.slider-arrow-right');
    
    if (slider && slides.length > 0) {
        let currentSlide = 0;
        const slideInterval = 5000; // 5秒切换一次
        let isAnimating = false; // 添加动画状态标记
        
        // 自动轮播
        let slideTimer = setInterval(nextSlide, slideInterval);
        
        // 点击指示点切换
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                if (isAnimating || currentSlide === index) return; // 防止动画过程中重复点击
                clearInterval(slideTimer);
                currentSlide = index;
                updateSlider();
                slideTimer = setInterval(nextSlide, slideInterval);
            });
        });
        
        // 箭头控制
        if (nextArrow) {
            nextArrow.addEventListener('click', () => {
                if (isAnimating) return;
                clearInterval(slideTimer);
                nextSlide();
                slideTimer = setInterval(nextSlide, slideInterval);
            });
        }
        
        if (prevArrow) {
            prevArrow.addEventListener('click', () => {
                if (isAnimating) return;
                clearInterval(slideTimer);
                currentSlide = (currentSlide - 1 + slides.length) % slides.length;
                updateSlider();
                slideTimer = setInterval(nextSlide, slideInterval);
            });
        }
        
        // 下一张幻灯片
        function nextSlide() {
            if (isAnimating) return; // 如果正在动画中，则跳过
            currentSlide = (currentSlide + 1) % slides.length;
            updateSlider();
        }
        
        // 更新轮播状态
        function updateSlider() {
            isAnimating = true; // 开始动画
            
            // 先移除所有的active类
            slides.forEach((slide) => {
                if (slide.classList.contains('active')) {
                    slide.style.zIndex = 1; // 当前活跃的放在底层
                }
            });
            
            // 设置当前显示的幻灯片
            slides[currentSlide].style.zIndex = 3; // 新幻灯片放在顶层
            slides[currentSlide].classList.add('active');
            
            setTimeout(() => {
                // 动画结束后移除非活跃幻灯片的active类
                slides.forEach((slide, i) => {
                    if (i !== currentSlide) {
                        slide.classList.remove('active');
                    }
                });
                
                isAnimating = false; // 结束动画
            }, 800); // 与CSS过渡时间一致
            
            // 更新指示点
            dots.forEach((dot, i) => {
                if (i === currentSlide) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }
        
        // 预加载图片
        slides.forEach(slide => {
            const img = slide.querySelector('img');
            if (img) {
                const preloadImg = new Image();
                preloadImg.src = img.src;
            }
        });
    }

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