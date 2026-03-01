// assets/js/app.js
// Main application JavaScript file

// Service Worker Registration for Push Notifications
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(BASE_URL + 'sw.js')
            .then((registration) => {
                console.log('ServiceWorker registration successful with scope: ', registration.scope);
            })
            .catch((error) => {
                console.log('ServiceWorker registration failed: ', error);
            });
    });
}

// Global AJAX helper with BASE_URL
window.app = {
    // Make API calls using dynamic BASE_URL
    api: async function(endpoint, options = {}) {
        const url = BASE_URL + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, finalOptions);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    },
    
    // Helper for form submissions
    submitForm: async function(formElement, onSuccess = null, onError = null) {
        const formData = new FormData(formElement);
        const endpoint = formElement.action.replace(BASE_URL, '');
        
        try {
            const result = await this.api(endpoint, {
                method: 'POST',
                body: formData
            });
            
            if (onSuccess) onSuccess(result);
            return result;
        } catch (error) {
            if (onError) onError(error);
            throw error;
        }
    }
};

// Initialize tooltips and popovers
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// Utility functions
window.utils = {
    // Show alert message
    showAlert: function(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of the container
        const container = document.querySelector('.app-container') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    },
    
    // Format currency
    formatCurrency: function(amount, currency = 'UGX') {
        return new Intl.NumberFormat('en-UG', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },
    
    // Format date/time
    formatDateTime: function(dateString) {
        return new Date(dateString).toLocaleString();
    }
};

(function () {
  const sidebar = document.getElementById('sidebar');
  const toggle  = document.getElementById('sidebarToggle');
  const flyout  = document.getElementById('sidebarFlyout');

  if (!sidebar) return;

  function ensureMobileTopbar() {
    if (document.querySelector('.mobile-topbar')) {
      return;
    }

    const brand = sidebar.querySelector('.brand');
    const brandText = brand?.querySelector('.brand-text')?.textContent?.trim() || 'AlmaTech SMS';
    const brandIcon = brand?.querySelector('.brand-icon')?.innerHTML || '<i class="bi bi-chat-square-text"></i>';
    const brandHref = brand?.getAttribute('href') || (window.BASE_URL ? window.BASE_URL + 'dashboard.php' : './dashboard.php');

    const topbar = document.createElement('header');
    topbar.className = 'mobile-topbar d-lg-none';
    topbar.innerHTML = `
      <button id="mobileMenuToggle" class="mobile-topbar-toggle" type="button" aria-label="Open menu">
        <i class="bi bi-list"></i>
      </button>
      <a class="mobile-topbar-brand" href="${brandHref}">
        <span class="mobile-topbar-icon">${brandIcon}</span>
        <span class="mobile-topbar-text"></span>
      </a>
    `;

    const textEl = topbar.querySelector('.mobile-topbar-text');
    if (textEl) {
      textEl.textContent = brandText;
    }

    document.body.appendChild(topbar);
  }

  ensureMobileTopbar();
  const mobileToggle = document.getElementById('mobileMenuToggle');

  // Persist collapsed state
  const saved = localStorage.getItem('sidebar_collapsed');
  if (saved === '1') sidebar.classList.add('collapsed');

  // If you wrap content in .app-shell, adjust padding
  const shell = document.querySelector('.app-shell');
  const isMobile = () => window.matchMedia('(max-width: 991px)').matches;

  let backdrop = document.querySelector('.sidebar-backdrop');
  if (!backdrop) {
    backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);
  }

  const openMobileSidebar = () => {
    sidebar.classList.add('mobile-open');
    backdrop.classList.add('show');
  };

  const closeMobileSidebar = () => {
    sidebar.classList.remove('mobile-open');
    backdrop.classList.remove('show');
  };

  if (mobileToggle) {
    mobileToggle.addEventListener('click', () => {
      if (!isMobile()) return;
      if (sidebar.classList.contains('mobile-open')) {
        closeMobileSidebar();
        return;
      }
      openMobileSidebar();
    });
  }

  backdrop.addEventListener('click', closeMobileSidebar);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeMobileSidebar();
    }
  });

  window.addEventListener('resize', () => {
    if (!isMobile()) {
      closeMobileSidebar();
    }
  });

  const syncShell = () => {
    if (!shell) return;
    shell.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
  };
  syncShell();

  // Toggle collapse
  if (toggle) {
    toggle.addEventListener('click', () => {
      if (isMobile()) {
        if (sidebar.classList.contains('mobile-open')) {
          closeMobileSidebar();
        } else {
          openMobileSidebar();
        }
        return;
      }
      sidebar.classList.toggle('collapsed');
      localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
      hideFlyout();
      syncShell();
    });
  }

  // Expanded mode: click group to open/close
  sidebar.querySelectorAll('.nav-group').forEach(group => {
    const btn = group.querySelector('.nav-group-btn');
    if (!btn) return;

    btn.addEventListener('click', () => {
      if (sidebar.classList.contains('collapsed')) return; // collapsed uses hover flyout
      // toggle only this group, keep others as-is (or close others if you want)
      group.classList.toggle('open');
    });
  });

  // Collapsed mode: hover shows submenu flyout
  function hideFlyout() {
    if (!flyout) return;
    flyout.classList.remove('show');
    flyout.innerHTML = '';
    flyout.setAttribute('aria-hidden', 'true');
  }

  function showFlyoutForGroup(group, anchorEl) {
    if (!flyout) return;

    const label = anchorEl.querySelector('.nav-text')?.textContent?.trim() || 'Menu';

    // Clone submenu links
    const submenu = group.querySelector('.nav-submenu');
    const links = submenu ? Array.from(submenu.querySelectorAll('a')) : [];

    flyout.innerHTML = `
      <div class="flyout-title">${escapeHtml(label)}</div>
      <div class="flyout-links"></div>
    `;

    const linksBox = flyout.querySelector('.flyout-links');
    links.forEach(a => {
      const clone = a.cloneNode(true);
      linksBox.appendChild(clone);
    });

    // Position flyout next to hovered group button
    const r = anchorEl.getBoundingClientRect();
    flyout.style.top = `${Math.max(10, r.top)}px`;
    flyout.style.left = `${r.right + 10}px`;

    flyout.classList.add('show');
    flyout.setAttribute('aria-hidden', 'false');
  }

  // attach hover handlers
  sidebar.querySelectorAll('.nav-group').forEach(group => {
    const btn = group.querySelector('.nav-group-btn');
    if (!btn) return;

    btn.addEventListener('mouseenter', () => {
      if (!sidebar.classList.contains('collapsed')) return;
      showFlyoutForGroup(group, btn);
    });

    btn.addEventListener('mouseleave', () => {
      if (!sidebar.classList.contains('collapsed')) return;
      // Give time to move mouse into flyout
      setTimeout(() => {
        if (!flyout) return;
        if (!flyout.matches(':hover')) hideFlyout();
      }, 120);
    });
  });

  // Keep flyout open while hovering it
  if (flyout) {
    flyout.addEventListener('mouseleave', hideFlyout);
  }

  // Hide flyout on scroll / click outside
  document.addEventListener('scroll', () => {
    if (sidebar.classList.contains('collapsed')) hideFlyout();
  }, true);

  document.addEventListener('click', (e) => {
    if (!sidebar.classList.contains('collapsed')) return;
    if (!flyout) return;
    const t = e.target;
    if (sidebar.contains(t) || flyout.contains(t)) return;
    hideFlyout();
  });

  // tiny util
  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[s]);
  }
})();
