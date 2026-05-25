<?php
/**
 * Sidebar-Navigationslinks (wird in sidebar.php eingebunden).
 * Voraussetzung: getLinkClasses, getIconClasses, getSidebarCountBadgeClasses,
 * $userRole, $isAdminOrTechniker, $isNotKunde, BASE_URL, optional $pdo, optional $canSeeInventory
 */
?>
      <div class="flex min-h-full flex-1 flex-col">
      <div class="flex-1 min-h-0">
      <ul class="space-y-2">
        <li>
          <a href="<?php echo BASE_URL; ?>dashboard/" class="<?php echo getLinkClasses(BASE_URL . 'dashboard/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'dashboard/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5"/>
            </svg>
            <span class="ml-3" data-sidebar-collapse-hide="">Dashboard</span>
          </a>
        </li>
        <li>
          <a href="<?php echo BASE_URL; ?>kalender/" class="<?php echo getLinkClasses(BASE_URL . 'kalender/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'kalender/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/>
            </svg>
            <span class="ml-3" data-sidebar-collapse-hide="">Kalender</span>
          </a>
        </li>
        <li>
          <a href="<?php echo BASE_URL; ?>tickets/" class="<?php echo getLinkClasses(BASE_URL . 'tickets/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'tickets/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z"/>
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap" data-sidebar-collapse-hide="">Tickets</span>
            <?php if (isset($pdo) || (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO)) { try { $newTicketsCount = @getNewTicketsCount(); $hideTicketsBadge = $newTicketsCount <= 0; ?>
            <span class="sidebar-open-tickets-count-badge <?php echo getSidebarCountBadgeClasses($hideTicketsBadge ? 'hidden' : ''); ?>" title="<?php echo (int)$newTicketsCount; ?> offene Tickets"><?php echo $newTicketsCount > 99 ? '99' : (int)$newTicketsCount; ?></span>
            <?php } catch (Exception $e) {} catch (Error $e) {} } ?>
          </a>
        </li>
        <?php if ($isAdminOrTechniker): ?>
        <li>
          <a href="<?php echo BASE_URL; ?>todos/" class="<?php echo getLinkClasses(BASE_URL . 'todos/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'todos/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap" data-sidebar-collapse-hide="">Aufgaben</span>
            <?php if (isset($pdo) || (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO)) { try { $openTodosCount = @getOpenTodosCount(); $hideTodosBadge = $openTodosCount <= 0; ?>
            <span class="sidebar-open-todos-count-badge <?php echo getSidebarCountBadgeClasses($hideTodosBadge ? 'hidden' : ''); ?>" title="<?php echo (int)$openTodosCount; ?> offene Aufgaben"><?php echo $openTodosCount > 99 ? '99' : (int)$openTodosCount; ?></span>
            <?php } catch (Exception $e) {} catch (Error $e) {} } ?>
          </a>
        </li>
        <li>
          <a href="<?php echo BASE_URL; ?>projects/" class="<?php echo getLinkClasses(BASE_URL . 'projects/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'projects/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/>
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap" data-sidebar-collapse-hide="">Projekte</span>
            <?php if (isset($pdo) || (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO)) { try { $openProjectsCount = @getOpenProjectsCount(); if ($openProjectsCount > 0): ?>
            <span class="<?php echo getSidebarCountBadgeClasses(); ?>" title="<?php echo (int)$openProjectsCount; ?> aktive Projekte"><?php echo $openProjectsCount > 99 ? '99' : (int)$openProjectsCount; ?></span>
            <?php endif; } catch (Exception $e) {} catch (Error $e) {} } ?>
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <p class="ml-2 mt-4 mb-2 hidden text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" data-sidebar-collapse-hide="">Kunden &amp; Daten</p>
      <ul class="space-y-2 border-t border-gray-200 pt-2 dark:border-gray-700">
        <?php if ($isAdminOrTechniker): ?>
        <li>
          <a href="<?php echo BASE_URL; ?>companies/" class="<?php echo getLinkClasses(BASE_URL . 'companies/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'companies/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/>
            </svg>
            <span class="ml-3" data-sidebar-collapse-hide="">Firmen</span>
          </a>
        </li>
        <?php endif; ?>
        <?php if ($userRole === 'Admin' || $userRole === 'Techniker' || $userRole === 'Firmen-Admin'): ?>
        <li>
          <a href="<?php echo BASE_URL; ?>customers/" class="<?php echo getLinkClasses(BASE_URL . 'customers/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'customers/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
            </svg>
            <span class="ml-3" data-sidebar-collapse-hide="">Endkunden</span>
          </a>
        </li>
        <?php endif; ?>
        <li>
          <a href="<?php echo BASE_URL; ?>devices/" class="<?php echo getLinkClasses(BASE_URL . 'devices/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'devices/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"/>
            </svg>
            <span class="ml-3" data-sidebar-collapse-hide="">Geräte</span>
          </a>
        </li>
        <?php if (!empty($canSeeInventory)): ?>
        <li>
          <a href="<?php echo BASE_URL; ?>inventory/" class="<?php echo getLinkClasses(BASE_URL . 'inventory/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'inventory/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.143 4H4.857A.857.857 0 0 0 4 4.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 10 9.143V4.857A.857.857 0 0 0 9.143 4Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 20 9.143V4.857A.857.857 0 0 0 19.143 4Zm-10 10H4.857a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286A.857.857 0 0 0 9.143 14Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286a.857.857 0 0 0-.857-.857Z"/>
            </svg>
            <span class="ml-3" data-sidebar-collapse-hide="">Lager</span>
          </a>
        </li>
        <?php endif; ?>
        <?php if ($isNotKunde): ?>
        <li>
          <a href="<?php echo BASE_URL; ?>orders/" class="<?php echo getLinkClasses(BASE_URL . 'orders/'); ?>">
            <svg class="<?php echo getIconClasses(BASE_URL . 'orders/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8"/>
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap" data-sidebar-collapse-hide="">Bestellungen</span>
            <?php if (isset($pdo) || (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO)) { try { $openOrdersCount = @getOpenOrdersCount(); if ($openOrdersCount > 0): ?>
            <span class="<?php echo getSidebarCountBadgeClasses(); ?>" title="<?php echo (int)$openOrdersCount; ?> offene Bestellungen"><?php echo $openOrdersCount > 99 ? '99+' : (int)$openOrdersCount; ?></span>
            <?php endif; } catch (Exception $e) {} catch (Error $e) {} } ?>
          </a>
        </li>
        <?php endif; ?>
      </ul>
      </div>

      <div class="sidebar-nav-bottom mt-auto shrink-0 bg-white pt-2 pb-1 dark:bg-primary-50">
        <ul class="space-y-2">
          <li>
            <a href="<?php echo BASE_URL; ?>links/" class="<?php echo getLinkClasses(BASE_URL . 'links/'); ?>">
              <svg class="<?php echo getIconClasses(BASE_URL . 'links/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap" data-sidebar-collapse-hide="">Verknüpfungen</span>
            </a>
          </li>
          <?php if ($isAdminOrTechniker): ?>
          <li>
            <a href="<?php echo BASE_URL; ?>time-tracking/" class="<?php echo getLinkClasses(BASE_URL . 'time-tracking/'); ?>">
              <svg class="<?php echo getIconClasses(BASE_URL . 'time-tracking/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap" data-sidebar-collapse-hide="">Zeiterfassung</span>
            </a>
          </li>
          <li>
            <a href="<?php echo BASE_URL; ?>knowledge/" class="<?php echo getLinkClasses(BASE_URL . 'knowledge/'); ?>">
              <svg class="<?php echo getIconClasses(BASE_URL . 'knowledge/'); ?>" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v13H7a2 2 0 0 0-2 2Zm0 0a2 2 0 0 0 2 2h12M9 3v14m7 0v4"/>
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap" data-sidebar-collapse-hide="">Wissensdatenbank</span>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </div>
      </div>
