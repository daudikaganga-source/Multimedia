// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
    
    // File Upload Preview
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const uploadArea = document.querySelector('.file-upload-area');
    
    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#64ffda';
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = '#495670';
        });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#495670';
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                previewFile(e.dataTransfer.files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files.length) {
                previewFile(this.files[0]);
            }
        });
    }
    
    function previewFile(file) {
        if (filePreview) {
            const reader = new FileReader();
            reader.onload = function(e) {
                filePreview.innerHTML = `
                    <div class="file-preview">
                        <i class="fas fa-file-alt"></i>
                        <div>
                            <h4>${file.name}</h4>
                            <p>${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                        </div>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        }
    }
    
    // Download with Authentication Check
    const downloadButtons = document.querySelectorAll('.download-btn');
    downloadButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.dataset.authenticated || this.dataset.authenticated === 'false') {
                e.preventDefault();
                if (confirm('You need to be registered to download files. Register now?')) {
                    window.location.href = 'register.php?redirect=' + encodeURIComponent(window.location.href);
                }
            }
        });
    });
    
    // Real-time Notifications
    if (document.querySelector('.notification-bell')) {
        setInterval(checkNotifications, 30000); // Check every 30 seconds
    }
    
    function checkNotifications() {
        fetch('api/check_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    updateNotificationBadge(data.count);
                }
            });
    }
    
    function updateNotificationBadge(count) {
        const bell = document.querySelector('.notification-bell');
        let badge = bell.querySelector('.notification-badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'notification-badge';
            bell.appendChild(badge);
        }
        badge.textContent = count;
        badge.style.display = 'inline';
    }
    
    // Search Functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const documents = document.querySelectorAll('.document-card');
            
            documents.forEach(doc => {
                const title = doc.querySelector('h3').textContent.toLowerCase();
                const desc = doc.querySelector('p').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || desc.includes(searchTerm)) {
                    doc.style.display = 'block';
                } else {
                    doc.style.display = 'none';
                }
            });
        });
    }
});