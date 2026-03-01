<?php
// templates/sidebar.php
declare(strict_types=1);

$BASE = defined('BASE_URL') ? BASE_URL : '/';
$current = $_SERVER['REQUEST_URI'] ?? '';
$pathOnly = parse_url($current, PHP_URL_PATH) ?: $current;

// Helper: active if current URL contains the href
function is_active(string $href, string $pathOnly): bool {
  // normalize: if your app sits in subfolder, match by end segment
  return $href !== '#' && strpos($pathOnly, $href) !== false;
}

// Define menu (groups + children)
$nav = [
  [
    'key' => 'sms',
    'icon' => 'bi bi-chat-dots',
    'label' => 'SMS',
    'items' => [
      ['label' => 'Compose SMS',   'href' => 'modules/sms/compose.php',    'icon' => 'bi bi-pencil-square'],
      ['label' => 'Customized',    'href' => 'modules/sms/customized.php', 'icon' => 'bi bi-person-lines-fill'],
      ['label' => 'Sent',          'href' => 'modules/sms/sent.php',        'icon' => 'bi bi-send-check'],
      ['label' => 'Queue',         'href' => 'modules/sms/queue.php',       'icon' => 'bi bi-hourglass-split'],
    ],
  ],
  [
    'key' => 'phonebook',
    'icon' => 'bi bi-journal-bookmark',
    'label' => 'Phone Book',
    'items' => [
      ['label' => 'Groups',   'href' => 'modules/phonebook/groups.php',   'icon' => 'bi bi-collection'],
      ['label' => 'Contacts', 'href' => 'modules/phonebook/contacts.php', 'icon' => 'bi bi-people'],
    ],
  ],
  [
    'key' => 'wallet',
    'icon' => 'bi bi-wallet2',
    'label' => 'Wallet',
    'items' => [
      ['label' => 'Buy',           'href' => 'modules/wallet/buy.php',          'icon' => 'bi bi-cart-plus'],
      ['label' => 'Transactions',  'href' => 'modules/wallet/transactions.php', 'icon' => 'bi bi-receipt'],
      ['label' => 'Me 2 U',        'href' => 'modules/wallet/transfer.php',     'icon' => 'bi bi-arrow-left-right'],
      ['label' => 'Vouchers',      'href' => 'modules/wallet/vouchers.php',     'icon' => 'bi bi-ticket-perforated'],
    ],
  ],
  [
    'key' => 'messaging',
    'icon' => 'bi bi-envelope',
    'label' => 'Messaging',
    'items' => [
      ['label' => 'Inbox',   'href' => 'modules/messaging/inbox.php',   'icon' => 'bi bi-inbox'],
      ['label' => 'Compose', 'href' => 'modules/messaging/compose.php', 'icon' => 'bi bi-envelope-plus'],
    ],
  ],
  [
    'key' => 'settings',
    'icon' => 'bi bi-gear',
    'label' => 'Settings',
    'items' => [
      ['label' => 'Profile',  'href' => 'modules/settings/profile.php',  'icon' => 'bi bi-person-circle'],
      ['label' => 'Branding', 'href' => 'modules/settings/branding.php', 'icon' => 'bi bi-image'],
      ['label' => 'Theme',    'href' => 'modules/settings/theme.php',    'icon' => 'bi bi-palette'],
      ['label' => 'Password', 'href' => 'modules/settings/password.php', 'icon' => 'bi bi-shield-lock'],
    ],
  ],
];

// Admin-only groups (optional)
$isAdmin = (int)($_SESSION['user']['role_id'] ?? 0) === 1;
if ($isAdmin) {
  $nav[] = [
    'key' => 'admin',
    'icon' => 'bi bi-speedometer2',
    'label' => 'Admin',
    'items' => [
      ['label' => 'Users',        'href' => 'modules/admin/users/index.php',       'icon' => 'bi bi-people-fill'],
      ['label' => 'Payments',     'href' => 'modules/admin/payments/index.php',    'icon' => 'bi bi-cash-coin'],
      ['label' => 'SMS Logs',     'href' => 'modules/admin/sms_logs/index.php',    'icon' => 'bi bi-list-check'],
      ['label' => 'Maintenance',  'href' => 'modules/admin/maintenance/index.php', 'icon' => 'bi bi-tools'],
      ['label' => 'Broadcast',    'href' => 'modules/admin/broadcast/index.php',   'icon' => 'bi bi-megaphone'],
    ],
  ];
}
?>
<aside id="sidebar" class="sidebar">
  <div class="sidebar-top">
    <a class="brand" href="<?= htmlspecialchars($BASE) ?>dashboard.php">
      <span class="brand-icon">
        <?php if (!empty($_SESSION['user']['business_logo'])): ?>
          <img src="<?= htmlspecialchars($BASE . $_SESSION['user']['business_logo']) ?>" alt="Logo">
        <?php else: ?>
          <i class="bi bi-chat-square-text"></i>
        <?php endif; ?>
      </span>
      <span class="brand-text"><?= htmlspecialchars($_SESSION['user']['business_name'] ?: 'AlmaTech SMS') ?></span>
    </a>

    <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle Sidebar">
      <i class="bi bi-list"></i>
    </button>
  </div>

  <nav class="sidebar-nav" role="navigation">
    <a class="nav-link <?= is_active('/dashboard.php', $pathOnly) ? 'active' : '' ?>"
       href="<?= htmlspecialchars($BASE) ?>dashboard.php">
      <i class="bi bi-house-door"></i>
      <span class="nav-text">Dashboard</span>
    </a>

    <?php foreach ($nav as $group): ?>
      <?php
        $groupOpen = false;
        foreach ($group['items'] as $it) {
          if (is_active('/' . $it['href'], $pathOnly) || is_active($it['href'], $pathOnly)) { $groupOpen = true; break; }
        }
      ?>
      <div class="nav-group <?= $groupOpen ? 'open' : '' ?>" data-group="<?= htmlspecialchars($group['key']) ?>">
        <button class="nav-group-btn" type="button">
          <i class="<?= htmlspecialchars($group['icon']) ?>"></i>
          <span class="nav-text"><?= htmlspecialchars($group['label']) ?></span>
          <i class="bi bi-chevron-down chevron"></i>
        </button>

        <div class="nav-submenu">
          <?php foreach ($group['items'] as $it): ?>
            <?php $active = is_active('/' . $it['href'], $pathOnly) || is_active($it['href'], $pathOnly); ?>
            <a class="nav-sublink <?= $active ? 'active' : '' ?>"
               href="<?= htmlspecialchars($BASE . $it['href']) ?>">
              <i class="<?= htmlspecialchars($it['icon']) ?>"></i>
              <span><?= htmlspecialchars($it['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <a class="nav-link" href="<?= htmlspecialchars($BASE) ?>logout.php">
      <i class="bi bi-box-arrow-right"></i>
      <span class="nav-text">Logout</span>
    </a>
  </nav>

  <!-- Floating submenu for collapsed hover -->
  <div id="sidebarFlyout" class="sidebar-flyout" aria-hidden="true"></div>
</aside>

<header class="mobile-topbar d-lg-none">
  <button id="mobileMenuToggle" class="mobile-topbar-toggle" type="button" aria-label="Open menu">
    <i class="bi bi-list"></i>
  </button>

  <a class="mobile-topbar-brand" href="<?= htmlspecialchars($BASE) ?>dashboard.php">
    <span class="mobile-topbar-icon">
      <?php if (!empty($_SESSION['user']['business_logo'])): ?>
        <img src="<?= htmlspecialchars($BASE . $_SESSION['user']['business_logo']) ?>" alt="Logo">
      <?php else: ?>
        <i class="bi bi-chat-square-text"></i>
      <?php endif; ?>
    </span>
    <span class="mobile-topbar-text"><?= htmlspecialchars($_SESSION['user']['business_name'] ?: 'AlmaTech SMS') ?></span>
  </a>
</header>
