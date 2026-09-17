/**
 * site_config.js - 全局站点配置与动态 UI 渲染脚本
 */
(function() {
    window.siteConfigData = null;

    function applySiteConfig(config) {
        if (!config) return;
        window.siteConfigData = config;

        // 1. 应用品牌与主题颜色
        if (config.brand) {
            var brand = config.brand;
            
            // 更新 CSS 全局主配色变量
            if (brand.primary_color) {
                document.documentElement.style.setProperty('--accent-orange', brand.primary_color);
            }

            // 更新导航栏 Brand 标识
            var navBrands = document.querySelectorAll('.nav-brand');
            navBrands.forEach(function(el) {
                var badgeEl = el.querySelector('.badge');
                var badgeText = badgeEl ? badgeEl.outerHTML : '';
                
                // 仅替换文本节点，保留 badge
                el.childNodes.forEach(function(child) {
                    if (child.nodeType === Node.TEXT_NODE) {
                        child.nodeValue = brand.site_name + ' ';
                    }
                });

                if (badgeEl && brand.badge) {
                    badgeEl.innerText = brand.badge;
                }
            });

            // 更新浏览器页面 Title
            if (brand.site_name && !document.title.includes('管理控制台')) {
                var currentTitle = document.title;
                if (currentTitle.indexOf(' - ') !== -1) {
                    var parts = currentTitle.split(' - ');
                    document.title = brand.site_name + ' - ' + parts[1];
                }
            }
        }

        // 2. 动态渲染 Header 顶部导航菜单 (对于含有后台特殊内部导航的控制台页面做排他保护)
        if (config.nav_menu && Array.isArray(config.nav_menu) && !window.location.pathname.endsWith('admin.html')) {
            var navContainers = document.querySelectorAll('.nav-links');
            var currentPath = window.location.pathname.split('/').pop() || 'index.html';

            navContainers.forEach(function(container) {
                container.innerHTML = '';
                config.nav_menu.forEach(function(item) {
                    if (item.visible !== false) {
                        var a = document.createElement('a');
                        a.href = item.url;
                        a.innerText = item.name;
                        
                        // 匹配当前页高亮
                        if (currentPath === item.url || (currentPath === '' && item.url === 'index.html')) {
                            a.className = 'active';
                        }
                        container.appendChild(a);
                    }
                });
            });
        }

        // 3. 动态渲染带有 data-config 属性的页面文本节点
        if (config.page_content) {
            var contentMap = config.page_content;
            Object.keys(contentMap).forEach(function(key) {
                var els = document.querySelectorAll('[data-config="' + key + '"]');
                els.forEach(function(el) {
                    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                        el.value = contentMap[key];
                    } else {
                        el.innerText = contentMap[key];
                    }
                });
            });
        }
    }

    function loadSiteConfig() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'api_site_config.php?action=get', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.status && res.config) {
                        applySiteConfig(res.config);
                    }
                } catch(e) {
                    console.error('[SiteConfig] 解析全站配置失败:', e);
                }
            }
        };
        xhr.send();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSiteConfig);
    } else {
        loadSiteConfig();
    }
})();
