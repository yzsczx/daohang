// dh/js/main.js
document。addEventListener('DOMContentLoaded', function() {
    // console.log('Personal Dashboard Main JS Loaded');

    // Example: Smooth scroll for anchor links (if you add them)
    // document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    //     anchor.addEventListener('click', function (e) {
    //         e.preventDefault();
    //         const targetId = this.getAttribute('href');
    //         const targetElement = document.querySelector(targetId);
    //         if (targetElement) {
    //             targetElement.scrollIntoView({
    //                 behavior: 'smooth'
    //             });
    //         }
    //     });
    // });

    // Future: Initialize SortableJS for drag & drop
    // Future: AJAX calls for adding/editing/deleting items without page reload
    // Future: Dynamic widget loading/updating (e.g., weather)

    // 处理"显示更多"按钮的点击事件
    document.querySelectorAll('.show-more-links').forEach(button => {
        button.addEventListener('click', function() {
            const blockId = this.getAttribute('data-block-id');
            const pageId = this.getAttribute('data-page-id');
            const totalLinks = parseInt(this.getAttribute('data-total-links'));
            const loadedLinks = parseInt(this.getAttribute('data-loaded-links'));

            // 计算还需要加载多少链接
            const remainingLinks = totalLinks - loadedLinks;
            // 使用与初始加载相同的链接数量
            const initialLimit = loadedLinks; // 初始加载的链接数量，应该等于用户设置的值
            const limit = Math.min(remainingLinks, initialLimit); // 每次加载与初始加载相同数量的链接

            if (limit <= 0) {
                return; // 没有更多链接可加载
            }

            // 显示加载中状态
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 加载中...';
            this.disabled = true;

            // 发送AJAX请求加载更多链接
            fetch(`ajax_load_more_links.php?block_id=${blockId}&page_id=${pageId}&offset=${loadedLinks}&limit=${limit}`)
                。then(response => response.json())
                。then(data => {
                    if (data.error) {
                        alert('加载更多链接失败: ' + data.error);
                        return;
                    }

                    // 将新链接添加到列表中
                    const linksList = document.getElementById(`links-list-${blockId}`);
                    if (linksList && data.html) {
                        linksList.insertAdjacentHTML('beforeend', data.html);
                    }

                    // 更新按钮状态
                    const newLoadedLinks = loadedLinks + data.count;
                    this.setAttribute('data-loaded-links', newLoadedLinks);

                    if (newLoadedLinks >= totalLinks) {
                        // 已加载所有链接，隐藏按钮
                        this.parentNode.style.display = 'none';
                    } else {
                        // 更新按钮文本
                        this.innerHTML = `<i class="fas fa-chevron-down"></i> 显示更多 (${totalLinks - newLoadedLinks})`;
                        this.disabled = false;
                    }
                })
                。catch(error => {
                    console.error('加载更多链接出错:', error);
                    this.innerHTML = '<i class="fas fa-exclamation-circle"></i> 加载失败，请重试';
                    this.disabled = false;
                });
        });
    });

    // Handle messages that might be added via JS (if any)
    const pageMessages = document.querySelectorAll('.page-message');
    pageMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500); // Remove after fade out
        }, 5000); // Hide after 5 seconds
    });

});
