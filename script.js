// Post Modal Form Submission (isolated to run independently)
const postForm = document.getElementById('post-form');
if (postForm) {
    console.log('Post form found, attaching submit handler');
    postForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        console.log('Post form submitted');
        const formData = new FormData(postForm);
        const errorDiv = document.getElementById('post-error');
        try {
            const response = await fetch('dashboard.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            console.log('Post response:', result);
            if (result.success && result.post) {
                const feed = document.querySelector('.feed');
                if (feed) {
                    const newPost = document.createElement('div');
                    newPost.className = 'post card mb-3';
                    newPost.dataset.postId = result.post.id;
                    newPost.innerHTML = `
                        <div class="card-body">
                            <div class="post-header d-flex gap-2 align-items-center">
                                <img src="assets/${result.post.image || 'default-profile.png'}" alt="User" class="profile-pic rounded-circle">
                                <span class="username">${result.post.username}</span>
                                <span class="timestamp text-muted">${new Date().toLocaleString()}</span>
                                <span class="status badge bg-warning">open</span>
                                ${result.post.department ? `<span class="department badge bg-secondary">${result.post.department}</span>` : ''}
                            </div>
                            <h3 class="card-title mt-2">${result.post.title}</h3>
                            <p class="card-text">${result.post.content}</p>
                            ${result.post.media_path ? (result.post.media_path.includes('.mp4') ? 
                                `<video controls class="w-100 rounded" style="max-height: 200px;"><source src="${result.post.media_path}" type="video/mp4"></video>` : 
                                `<img src="${result.post.media_path}" alt="Media" class="w-100 rounded" style="max-height: 200px;">`) : ''}
                            <div class="post-actions d-flex gap-3 mt-2">
                                <button class="btn btn-sm btn-outline-primary like-btn" data-post-id="${result.post.id}">
                                    <i class="bi bi-heart"></i> Like (<span class="like-count">0</span>)
                                </button>
                                <button class="btn btn-sm btn-outline-primary share-btn" data-post-id="${result.post.id}">
                                    <i class="bi bi-share"></i> Share
                                </button>
                                <button class="btn btn-sm btn-outline-primary repost-btn" data-post-id="${result.post.id}">
                                    <i class="bi bi-repeat"></i> Repost
                                </button>
                                <button class="btn btn-sm btn-outline-primary comment-btn" data-bs-toggle="collapse" data-bs-target="#comments-${result.post.id}">
                                    <i class="bi bi-chat"></i> Comment (<span class="comment-count">0</span>)
                                </button>
                            </div>
                            <div class="collapse" id="comments-${result.post.id}">
                                <div class="comments-list mt-2"></div>
                                <form class="comment-form mt-2" data-post-id="${result.post.id}">
                                    <input type="hidden" name="action" value="comment">
                                    <input type="hidden" name="post_id" value="${result.post.id}">
                                    <textarea name="content" class="form-control mb-2" placeholder="Add a comment..." required></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
                                </form>
                            </div>
                            <div class="x-post mt-2">
                                <p>Embedded X post will appear here.</p>
                            </div>
                        </div>`;
                    feed.insertBefore(newPost, feed.children[2] || feed.firstChild);
                    postForm.reset();
                    bootstrap.Modal.getInstance(document.getElementById('postModal')).hide();
                    errorDiv.style.display = 'none';
                    console.log('Post appended to feed');
                } else {
                    console.error('Feed element not found');
                    errorDiv.textContent = 'Error: Feed not found. Try refreshing.';
                    errorDiv.style.display = 'block';
                }
            } else {
                errorDiv.textContent = result.message || 'Error posting complaint.';
                errorDiv.style.display = 'block';
            }
        } catch (error) {
            errorDiv.textContent = 'Error posting complaint. Check console for details.';
            errorDiv.style.display = 'block';
            console.error('Post error:', error);
        }
    });
} else {
    console.warn('Post form not found');
}

// Other Features
document.addEventListener('DOMContentLoaded', () => {
    // Theme Toggle
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        console.log('Theme toggle found');
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            themeToggle.textContent = 'Switch to Dark Mode';
        } else {
            document.body.classList.remove('light-mode');
            themeToggle.textContent = 'Switch to Light Mode';
        }
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');
            const isLightMode = document.body.classList.contains('light-mode');
            themeToggle.textContent = isLightMode ? 'Switch to Dark Mode' : 'Switch to Light Mode';
            localStorage.setItem('theme', isLightMode ? 'light' : 'dark');
            console.log('Theme toggled to:', isLightMode ? 'light' : 'dark');
        });
    } else {
        console.warn('Theme toggle button not found');
    }

    // Profile Menu
    const profileLink = document.querySelector('.profile-link');
    if (profileLink) {
        profileLink.addEventListener('click', (e) => {
            e.preventDefault();
            const existingMenu = document.querySelector('.profile-menu');
            if (existingMenu) existingMenu.remove();
            const menu = document.createElement('div');
            menu.className = 'profile-menu position-absolute bg-dark text-white p-3 rounded';
            menu.style.bottom = '60px';
            menu.style.left = '20px';
            menu.innerHTML = `
                <a href="add-account.php" class="d-block mb-2 text-white">Add Existing Account</a>
                <a href="profile.php" class="d-block mb-2 text-white">Edit Profile</a>
                <a href="logout.php" class="d-block text-white">Logout</a>
            `;
            profileLink.parentElement.appendChild(menu);
            document.addEventListener('click', (event) => {
                if (!menu.contains(event.target) && !profileLink.contains(event.target)) {
                    menu.remove();
                }
            }, { once: true });
        });
    }
    // Live avatar preview on login: fetch user by email and show image
    const loginEmail = document.getElementById('login-email');
    const loginAvatar = document.getElementById('login-avatar');
    if (loginEmail && loginAvatar) {
        let emailDebounceTimer;
        const showAvatar = (src) => {
            loginAvatar.querySelector('img').src = src;
            loginAvatar.style.display = 'block';
        };
        const hideAvatar = () => {
            loginAvatar.style.display = 'none';
        };
        const fetchAvatar = async (email) => {
            try {
                const resp = await fetch(`login.php?lookup=${encodeURIComponent(email)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!resp.ok) return hideAvatar();
                const data = await resp.json();
                if (data && data.found && data.image) {
                    showAvatar(`assets/${data.image}`);
                } else {
                    hideAvatar();
                }
            } catch (e) {
                hideAvatar();
            }
        };
        loginEmail.addEventListener('input', () => {
            const value = loginEmail.value.trim();
            clearTimeout(emailDebounceTimer);
            if (value.length < 2) {
                hideAvatar();
                return;
            }
            emailDebounceTimer = setTimeout(() => fetchAvatar(value), 300);
        });
    }

    // Like Button Handler
    document.addEventListener('click', async (e) => {
        if (e.target.closest('.like-btn')) {
            const btn = e.target.closest('.like-btn');
            const postId = btn.dataset.postId;
            try {
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=like&post_id=${postId}`
                });
                const result = await response.json();
                if (result.success) {
                    const likeCount = btn.querySelector('.like-count');
                    likeCount.textContent = parseInt(likeCount.textContent) + (result.liked ? 1 : -1);
                    btn.querySelector('i').classList.toggle('bi-heart', !result.liked);
                    btn.querySelector('i').classList.toggle('bi-heart-fill', result.liked);
                } else {
                    console.error('Like error:', result.message);
                }
            } catch (error) {
                console.error('Like error:', error);
            }
        }
    });

    // Share Button Handler
    document.addEventListener('click', (e) => {
        if (e.target.closest('.share-btn')) {
            const postId = e.target.closest('.share-btn').dataset.postId;
            const url = `${window.location.origin}/student-complaint-center/post.php?id=${postId}`;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copied to clipboard!');
            });
        }
    });

    // Repost Button Handler
    document.addEventListener('click', async (e) => {
        if (e.target.closest('.repost-btn')) {
            const postId = e.target.closest('.repost-btn').dataset.postId;
            try {
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=repost&post_id=${postId}`
                });
                const result = await response.json();
                if (result.success && result.post) {
                    const feed = document.querySelector('.feed');
                    const newPost = document.createElement('div');
                    newPost.className = 'post card mb-3';
                    newPost.dataset.postId = result.post.id;
                    newPost.innerHTML = `
                        <div class="card-body">
                            <div class="post-header d-flex gap-2 align-items-center">
                                <img src="assets/${result.post.image || 'default-profile.png'}" alt="User" class="profile-pic rounded-circle">
                                <span class="username">${result.post.username} (Repost)</span>
                                <span class="timestamp text-muted">${new Date().toLocaleString()}</span>
                                <span class="status badge bg-warning">open</span>
                                ${result.post.department ? `<span class="department badge bg-secondary">${result.post.department}</span>` : ''}
                            </div>
                            <h3 class="card-title mt-2">${result.post.title}</h3>
                            <p class="card-text">${result.post.content}</p>
                            ${result.post.media_path ? (result.post.media_path.includes('.mp4') ? 
                                `<video controls class="w-100 rounded" style="max-height: 200px;"><source src="${result.post.media_path}" type="video/mp4"></video>` : 
                                `<img src="${result.post.media_path}" alt="Media" class="w-100 rounded" style="max-height: 200px;">`) : ''}
                            <div class="post-actions d-flex gap-3 mt-2">
                                <button class="btn btn-sm btn-outline-primary like-btn" data-post-id="${result.post.id}">
                                    <i class="bi bi-heart"></i> Like (<span class="like-count">0</span>)
                                </button>
                                <button class="btn btn-sm btn-outline-primary share-btn" data-post-id="${result.post.id}">
                                    <i class="bi bi-share"></i> Share
                                </button>
                                <button class="btn btn-sm btn-outline-primary repost-btn" data-post-id="${result.post.id}">
                                    <i class="bi bi-repeat"></i> Repost
                                </button>
                                <button class="btn btn-sm btn-outline-primary comment-btn" data-bs-toggle="collapse" data-bs-target="#comments-${result.post.id}">
                                    <i class="bi bi-chat"></i> Comment (<span class="comment-count">0</span>)
                                </button>
                            </div>
                            <div class="collapse" id="comments-${result.post.id}">
                                <div class="comments-list mt-2"></div>
                                <form class="comment-form mt-2" data-post-id="${result.post.id}">
                                    <input type="hidden" name="action" value="comment">
                                    <input type="hidden" name="post_id" value="${result.post.id}">
                                    <textarea name="content" class="form-control mb-2" placeholder="Add a comment..." required></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
                                </form>
                            </div>
                            <div class="x-post mt-2">
                                <p>Embedded X post will appear here.</p>
                            </div>
                        </div>`;
                    feed.insertBefore(newPost, feed.children[2] || feed.firstChild);
                } else {
                    console.error('Repost error:', result.message);
                }
            } catch (error) {
                console.error('Repost error:', error);
            }
        }
    });

    // Status Update Handler (staff/admin)
    document.addEventListener('change', async (e) => {
        if (e.target.classList && e.target.classList.contains('status-select')) {
            const select = e.target;
            const postId = select.dataset.postId;
            const newStatus = select.value;
            try {
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=update_status&post_id=${encodeURIComponent(postId)}&status=${encodeURIComponent(newStatus)}`
                });
                const result = await response.json();
                if (result.success) {
                    const postCard = select.closest('.post');
                    const statusBadge = postCard.querySelector('.status');
                    statusBadge.textContent = newStatus;
                    statusBadge.dataset.statusValue = newStatus;
                    statusBadge.classList.remove('bg-warning', 'bg-info', 'bg-success');
                    if (newStatus === 'open') statusBadge.classList.add('bg-warning');
                    else if (newStatus === 'in_progress') statusBadge.classList.add('bg-info');
                    else statusBadge.classList.add('bg-success');
                } else {
                    alert(result.message || 'Failed to update status');
                    // revert select
                    const current = (select.closest('.post').querySelector('.status').dataset.statusValue) || 'open';
                    select.value = current;
                }
            } catch (error) {
                console.error('Status update error:', error);
                alert('Network error while updating status');
            }
        }
    });

    // Comment Form Handler
    document.addEventListener('submit', async (e) => {
        if (e.target.closest('.comment-form')) {
            e.preventDefault();
            const form = e.target.closest('.comment-form');
            const postId = form.dataset.postId;
            const formData = new FormData(form);
            try {
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                if (result.success && result.comment) {
                    const commentsList = form.previousElementSibling;
                    const comment = document.createElement('div');
                    comment.className = 'comment card mb-2';
                    comment.innerHTML = `
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-center">
                                <img src="assets/${result.comment.image || 'default-profile.png'}" alt="User" class="profile-pic rounded-circle" style="width: 32px; height: 32px;">
                                <span class="username">${result.comment.username}</span>
                                <span class="timestamp text-muted">${new Date().toLocaleString()}</span>
                            </div>
                            <p class="card-text mt-1">${result.comment.content}</p>
                        </div>`;
                    commentsList.appendChild(comment);
                    form.reset();
                    const commentCount = form.parentElement.querySelector('.comment-count');
                    commentCount.textContent = parseInt(commentCount.textContent) + 1;
                } else {
                    console.error('Comment error:', result.message);
                }
            } catch (error) {
                console.error('Comment error:', error);
            }
        }
    });

    // Emoji Picker
    const emojiBtn = document.getElementById('emoji-btn');
    const contentTextarea = document.getElementById('content-textarea');
    if (emojiBtn && contentTextarea) {
        console.log('Emoji button found');
        emojiBtn.addEventListener('click', () => {
            const picker = new EmojiMart.Picker({
                data: EmojiMart.data,
                onEmojiSelect: (emoji) => {
                    contentTextarea.value += emoji.native;
                    contentTextarea.focus();
                }
            });
            document.body.appendChild(picker);
        });
    } else {
        console.warn('Emoji button or textarea not found');
    }

    // GIF Picker
    const gifBtn = document.getElementById('gif-btn');
    if (gifBtn) {
        console.log('GIF button found');
        gifBtn.addEventListener('click', () => {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>Search GIFs</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="text" id="gif-search" class="form-control mb-3" placeholder="Search GIFs...">
                            <div id="gif-results" class="row"></div>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(modal);
            new bootstrap.Modal(modal).show();
            const searchInput = modal.querySelector('#gif-search');
            const resultsDiv = modal.querySelector('#gif-results');
            searchInput.addEventListener('input', async (e) => {
                const query = e.target.value;
                if (query.length < 3) return;
                try {
                    const response = await fetch(`https://api.giphy.com/v1/gifs/search?api_key=dc6zaTOxFJmzC&q=${encodeURIComponent(query)}&limit=10&rating=g`);
                    const data = await response.json();
                    resultsDiv.innerHTML = data.data.map(gif => `
                        <div class="col-3 mb-2">
                            <img src="${gif.images.fixed_height.url}" alt="GIF" class="img-fluid cursor-pointer" onclick="document.getElementById('content-textarea').value += ' ${gif.images.fixed_height.url}'; new bootstrap.Modal(document.querySelector('.modal')).hide();">
                        </div>`).join('');
                } catch (error) {
                    console.error('GIF search error:', error);
                }
            });
        });
    } else {
        console.warn('GIF button not found');
    }

    // Load X Post (Placeholder)
    const xPosts = document.querySelectorAll('.x-post');
    xPosts.forEach(post => {
        post.innerHTML = `<blockquote class="twitter-tweet"><p>Test tweet</p><a href="https://twitter.com/X/status/123456789">View on X</a></blockquote>`;
        const script = document.createElement('script');
        script.src = 'https://platform.twitter.com/widgets.js';
        script.async = true;
        post.appendChild(script);
    });

    // Explore functionality
    const exploreForm = document.getElementById('explore-form');
    if (exploreForm) {
        exploreForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performExploreSearch();
        });
    }
    
    function performExploreSearch() {
        const searchTerm = document.getElementById('explore-search').value;
        const department = document.getElementById('explore-department').value;
        const status = document.getElementById('explore-status').value;
        const dateRange = document.getElementById('explore-date').value;
        
        // Build search URL
        const params = new URLSearchParams();
        if (searchTerm) params.append('q', searchTerm);
        if (department) params.append('department', department);
        if (status) params.append('status', status);
        if (dateRange) params.append('date', dateRange);
        
        // Redirect to dashboard with search parameters
        window.location.href = 'dashboard.php?' + params.toString();
    }
});