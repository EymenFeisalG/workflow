<?php
   define('login_req', true);

   require 'global.php';

   if (isset($_SESSION['addOrder'])) {
       unset($_SESSION['addOrder']);
   }

   $auth->userLoginCheck();

   $_GET['dir'] = $dir = $auth->setDir();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="ui/style/css/app.css?v=2.12" rel="stylesheet">
    <script>
        workflow = false;
        var Direction = "<?php echo $_GET['dir']; ?>";
        var WorkflowCsrf = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        var CurrentUser = {
            id: <?php echo (int)($_SESSION['user']['userid'] ?? 1); ?>,
            username: "<?php echo addslashes($_SESSION['user']['username'] ?? ''); ?>",
            role: <?php echo (int)($_SESSION['user']['user_role'] ?? 1); ?>
        };
        var TeamWorkers = <?php echo json_encode($main->getWorkersList(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <link href="ui/style/css/general.css" rel="stylesheet">
    <link href="ui/style/css/orderModal.css?v=3.10" rel="stylesheet">
    <link href="ui/style/css/macDock.css?v=3.7" rel="stylesheet">
    <link href="ui/style/css/notificationsFocus.css?v=9" rel="stylesheet">
    <script src="ui/js/jquery.js"></script>
    <script src="ui/js/general.js"></script>
    <script src="ui/js/orderModal.js?v=2.21"></script>
    <script src="ui/js/macDock.js?v=2.7"></script>
    <script src="ui/js/app.js?v=2.13"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>



    <title>Workflow</title>
</head>
<body>

<div class="container">

        <div class="orders">
        
        </div>

        <aside class="createdHistoryCard" id="createdHistoryCard" aria-label="Skapade uppgifter" aria-hidden="true">
            <div class="historyCardHeader">
                <div class="historyCardTitle">
                    <span class="historyIcon">📋</span>
                    <h3>Skapade uppgifter</h3>
                    <span class="historyCountBadge" id="historyCountBadge">0</span>
                </div>
                <div class="historyCardActions">
                    <button type="button" class="historyRefreshBtn" id="historyRefreshBtn" title="Uppdatera historik">↻</button>
                    <button type="button" class="historyToggleCollapseBtn" id="historyToggleCollapseBtn" aria-label="Stäng skapade uppgifter" title="Stäng">✕</button>
                </div>
            </div>

            <div class="historyCardBody" id="historyCardBody">
                <div class="historyStatsRow" id="historyStatsRow">
                    <div class="historyStatItem">
                        <span class="statNum" id="statOngoing">0</span>
                        <span class="statLbl">Pågående</span>
                    </div>
                    <div class="historyStatItem">
                        <span class="statNum" id="statDelegated">0</span>
                        <span class="statLbl">Delegerade</span>
                    </div>
                    <div class="historyStatItem">
                        <span class="statNum" id="statCompleted">0</span>
                        <span class="statLbl">Klara</span>
                    </div>
                </div>

                <div class="historyList" id="historyList">
                    <div class="historyEmptyState">Hämtar dina skapade uppdrag...</div>
                </div>

                <div class="historyCardFooter">
                    <button type="button" class="historyViewAllBtn" id="historyViewAllBtn">
                        Visa alla skapade i listan
                    </button>
                </div>
            </div>
        </aside>

        <!-- macOS Dock Floating Bottom Navigation -->
        <nav class="macDockWrapper" id="macDockWrapper" aria-label="Huvudmeny">
            <section class="notificationPopover" id="notificationPopover" aria-label="Notifikationer" hidden>
                <div class="notificationHeader"><strong>Notifikationer</strong><button type="button" id="notificationReadAll">Markera övriga som lästa</button></div>
                <div class="notificationList" id="notificationList"></div>
            </section>
            <!-- Spotlight Quick Search Popover -->
            <div class="spotlightPopover" id="spotlightPopover">
                <div class="spotlightInner">
                    <svg class="spotlightIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input class="searchOrder" type="search" placeholder="Sök uppgift..." autocomplete="off">
                    <span class="results"></span>
                    <button type="button" class="spotlightCloseBtn" id="spotlightCloseBtn" aria-label="Stäng sök">✕</button>
                </div>
            </div>

            <!-- Submeny Popover (Admin, Papperskorg, Logga ut) -->
            <div class="dockSubmenuPopover" id="dockSubmenuPopover" role="menu" aria-label="Fler alternativ">
                <div class="dockSubmenuInner">
                    <div class="dockSubmenuHeader">
                        <div class="dockSubmenuUserAvatar">
                            <?php echo strtoupper(substr($_SESSION['user']['username'] ?? 'U', 0, 2)); ?>
                        </div>
                        <div class="dockSubmenuUserInfo">
                            <span class="dockSubmenuUsername"><?php echo htmlspecialchars($_SESSION['user']['username'] ?? ''); ?></span>
                            <span class="dockSubmenuRole"><?php echo ($auth->hasRight('admin') ? 'Administratör' : 'Medarbetare'); ?></span>
                        </div>
                        <button type="button" class="dockSubmenuCloseBtn" id="dockSubmenuCloseBtn" aria-label="Stäng meny">✕</button>
                    </div>

                    <div class="dockSubmenuList">
                        <div class="dockMobileCategories" aria-label="Fler uppgiftskategorier">
                            <span class="dockSubmenuSectionTitle">Fler uppgiftskategorier</span>
                            <button type="button" class="dockSubmenuItem dockMobileCategory dockMenuCategory" data-url="pending" role="menuitem">
                                <svg class="dockSubmenuSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 7v5l3 2"></path>
                                </svg>
                                <div class="dockSubmenuItemContent"><span class="dockSubmenuItemTitle dockTabText">Granskas</span></div>
                                <span class="dockMobileCategoryCount badgePending" aria-label="0 uppdrag">0</span>
                            </button>
                            <button type="button" class="dockSubmenuItem dockMobileCategory dockMenuCategory" data-url="rework" role="menuitem">
                                <svg class="dockSubmenuSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 0 1 15.4-6.4L21 8"></path>
                                    <path d="M21 3v5h-5"></path>
                                    <path d="M21 12a9 9 0 0 1-15.4 6.4L3 16"></path>
                                    <path d="M3 21v-5h5"></path>
                                </svg>
                                <div class="dockSubmenuItemContent"><span class="dockSubmenuItemTitle dockTabText">Kompletteras</span></div>
                                <span class="dockMobileCategoryCount badgeRework" aria-label="0 uppdrag">0</span>
                            </button>
                            <button type="button" class="dockSubmenuItem dockMobileCategory dockMenuCategory" data-url="completed" role="menuitem">
                                <svg class="dockSubmenuSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m5 12 4 4L19 6"></path>
                                </svg>
                                <div class="dockSubmenuItemContent"><span class="dockSubmenuItemTitle dockTabText">Godkända</span></div>
                                <span class="dockMobileCategoryCount badgeCompleted" aria-label="0 uppdrag">0</span>
                            </button>
                            <div class="dockSubmenuDivider"></div>
                        </div>

                        <?php if($auth->hasRight('admin')): ?>
                        <a href="admin/" class="dockSubmenuItem" role="menuitem">
                            <svg class="dockSubmenuSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            <div class="dockSubmenuItemContent">
                                <span class="dockSubmenuItemTitle">Adminpanel</span>
                                <span class="dockSubmenuItemDesc">Hantera användare och rättigheter</span>
                            </div>
                        </a>
                        <?php endif; ?>

                        <button type="button" class="dockSubmenuItem recycle" data-url="canceled" role="menuitem">
                            <svg class="dockSubmenuSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            <div class="dockSubmenuItemContent">
                                <span class="dockSubmenuItemTitle dockTabText">Papperskorg</span>
                                <span class="dockSubmenuItemDesc">Avbrutna och raderade uppdrag</span>
                            </div>
                        </button>

                        <div class="dockSubmenuDivider"></div>

                        <a href="php/functions/logout.php" class="dockSubmenuItem itemLogout" role="menuitem">
                            <svg class="dockSubmenuSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <div class="dockSubmenuItemContent">
                                <span class="dockSubmenuItemTitle">Logga ut</span>
                                <span class="dockSubmenuItemDesc">Avsluta aktiv session</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="historyQuickBar">
                <button type="button" class="historyTabTrigger" id="historyTabTrigger" aria-controls="createdHistoryCard" aria-expanded="false">
                    <span class="historySummaryItem historySummaryPrimary"><span class="historySummaryLabel">Skapade</span><span class="historyFloatBadge" id="historyFloatBadge">0</span></span>
                    <span class="historySummaryItem"><span class="historySummaryLabel">Delegerade</span><span class="historySummaryBadge" id="historyDelegatedBadge">0</span></span>
                    <span class="historySummaryItem"><span class="historySummaryLabel">Pågår</span><span class="historySummaryBadge" id="historyOngoingBadge">0</span></span>
                    <span class="historySummaryItem"><span class="historySummaryLabel">Klara</span><span class="historySummaryBadge" id="historyCompletedBadge">0</span></span>
                </button>
            </div>

            <!-- Floating Dock Shelf (Text-based, compact height) -->
            <div class="macDock textDock" id="macDock" role="toolbar">
                <!-- Primär action: + Ny uppgift -->
                <?php if($auth->hasRight('add_new_order')): ?>
                <button type="button" class="dockTextBtn dockBtnPrimary addOrder" aria-label="Skapa ny uppgift">
                    <span class="dockBtnPlus">+</span>
                    <span class="dockBtnLabel">Ny uppgift</span>
                    <span class="dockDraftMobileLabel" aria-hidden="true">Utkast</span>
                </button>
                <?php endif; ?>

                <!-- Spotlight Sök-trigger -->
                <button type="button" class="dockTextBtn dockBtnSearch" id="dockSpotlightTrigger" aria-label="Sök uppgifter" title="Sök (Ctrl+K)">
                    <svg class="dockSmallSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span>Sök</span>
                </button>

                <button type="button" class="dockTextBtn notificationBell" id="notificationBell" aria-label="Notifikationer" aria-expanded="false" aria-controls="notificationPopover" title="Notifikationer">
                    <svg class="dockSmallSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                    <span id="notificationCount" class="notificationCount" hidden>0</span>
                </button>

                <div class="dockDivider dockUtilityDivider" role="separator"></div>

                <!-- Order Status / Kategori Filter Badge Tabs (#parentStats) -->
                <?php $main->getOrderNav(); ?>

                <div class="dockDivider dockUtilityDivider" role="separator"></div>

                <!-- Submeny Trigger: Mer (Admin, Papperskorg, Logga ut) -->
                <button type="button" class="dockTextBtn dockBtnMore" id="dockMoreTrigger" aria-label="Fler alternativ" aria-haspopup="true" aria-expanded="false" title="Mer (fler uppgiftskategorier, Admin, Papperskorg, Logga ut)">
                    <svg class="dockSmallSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="1.5"></circle>
                        <circle cx="19" cy="12" r="1.5"></circle>
                        <circle cx="5" cy="12" r="1.5"></circle>
                    </svg>
                    <span>Mer</span>
                </button>
            </div>
        </nav>
   </div>



    <div class="focusOverlay" id="focusOverlay" hidden>
        <div class="focusShell" role="dialog" aria-modal="true" aria-labelledby="focusTitle">
            <div class="focusHeader"><strong id="focusTitle">Fokusläge</strong><button type="button" id="focusClose" aria-label="Stäng fokusläge">Stäng ✕</button></div>
            <div class="focusContent" id="focusContent"></div>
        </div>
    </div>
        <div class="modal">
            <form class="decisionForm" method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="orderid" class="orderid" value="" hidden>
                <input type="text" name="action" class="action" value="" hidden>
                <h5 class="modalTitle">Meddelande / Kommentar (valfritt)</h5>
                <textarea class="content" name="comment"></textarea>
                <input type="submit" value="Spara" class="saveDecision">
                <input type="button" class="closeDecision close" value="Ångra">
            </form>
        </div>

    <!-- Clean Paper Sheet Order Modal -->
    <div class="orderModalOverlay" id="orderModalOverlay">
        <div class="orderModalCard" role="dialog" aria-modal="true" aria-labelledby="orderModalHeading">
            <div class="orderModalSheetHandle" aria-hidden="true"></div>

            <div class="orderModalHeader">
                <div class="orderModalHeaderTop">
                    <div class="orderModalHeaderTitle">
                        <h2 id="orderModalHeading">Ny uppgift</h2>
                        <span class="orderDraftBadge" id="orderDraftBadge" style="display: none;">Utkast sparat</span>
                    </div>
                    <button type="button" class="orderModalCloseBtn" id="orderModalCloseBtn" aria-label="Stäng">✕</button>
                </div>

                <!-- Email-liknande mottagare / tilldela-rad -->
                <div class="delegationBar" id="delegationBar">
                    <span class="delegationLabel">Till:</span>
                    <div class="delegationControl">
                        <button type="button" class="delegationPill" id="delegationPill" aria-haspopup="listbox" aria-expanded="false">
                            <span class="delegationAvatar" id="delegationPillAvatar">Du</span>
                            <span class="delegationName" id="delegationPillName">Mig själv</span>
                            <span class="delegationBadge" id="delegationPillRole">Ansvarig</span>
                            <span class="delegationArrow">▾</span>
                        </button>

                        <button type="button" class="lastDelegatedChip" id="lastDelegatedChip" style="display: none;" title="Klicka för att snabbt tilldela">
                            <span class="chipText">Senast: <strong id="lastDelegatedName"></strong></span>
                        </button>

                        <div class="delegationDropdown" id="delegationDropdown">
                            <div class="delegationSearchBox">
                                <input type="text" id="delegationSearchInput" placeholder="Sök kollega..." autocomplete="off">
                            </div>
                            <div class="delegationList" id="delegationList"></div>
                        </div>
                    </div>
                    <!-- Dolt fält för form submit -->
                    <input type="hidden" name="worker" id="orderWorkerSelect" value="<?php echo (int)($_SESSION['user']['userid'] ?? 1); ?>">
                </div>
            </div>

            <!-- Draft Alert -->
            <div class="draftAlertBar" id="draftAlertBar" style="display: none;">
                <div class="draftAlertContent">
                    <span id="draftAlertText">Tidigare sparat utkast återställt.</span>
                </div>
                <button type="button" class="draftDiscardBtn" id="draftDiscardBtn">Släng utkast</button>
            </div>

            <form id="orderModalForm" class="orderModalForm" enctype="multipart/form-data">
                <div class="orderModalBody">
                    <div class="paperSheet">

                        <!-- Uppgift -->
                        <div class="paperSection">
                            <div class="orderModalGroup">
                                <label class="orderModalLabel" for="orderTitle">Uppgiftens namn *</label>
                                <input type="text" id="orderTitle" name="order_title" class="orderModalInput" placeholder="Vad ska göras?" maxlength="225" required>
                            </div>
                        </div>

                        <!-- Kontaktperson -->
                        <details class="paperSection optionalOrderDetails" id="orderContactDetails">
                            <summary class="optionalOrderSummary"><span class="optionalOrderPlus" aria-hidden="true">+</span><span>Kontaktperson (valfritt)</span></summary>
                            <div class="optionalOrderContent">
                                <div class="orderModalGroup customerSearchWrapper">
                                    <label class="orderModalLabel" for="orderCustomerSearch">Sök sparad kontaktperson</label>
                                    <input type="text" id="orderCustomerSearch" class="orderModalInput" placeholder="Sök kontaktperson..." autocomplete="off">
                                    <div id="customerDropdown" class="customerDropdown"></div>
                                    <div id="customerSelectedBadge" class="customerSelectedBadge">
                                        <span>Kopplad till <strong id="selectedCustomerName"></strong></span>
                                        <button type="button" id="customerResetBtn" class="customerResetBtn">Rensa</button>
                                    </div>
                                </div>

                                <div class="orderModalRow twoCol">
                                    <div class="orderModalGroup">
                                        <label class="orderModalLabel" for="orderCompanyName">Kontaktperson</label>
                                        <input type="text" id="orderCompanyName" name="company_name" class="orderModalInput" placeholder="Namn på kontaktperson">
                                    </div>
                                    <div class="orderModalGroup">
                                        <label class="orderModalLabel" for="orderOrg">Företag / Org.nummer</label>
                                        <input type="text" id="orderOrg" name="org" class="orderModalInput" placeholder="Företagsnamn eller 556XXX-XXXX">
                                    </div>
                                </div>

                                <div class="orderModalRow twoCol">
                                    <div class="orderModalGroup">
                                        <label class="orderModalLabel" for="orderContact">Kontaktuppgifter / E-post</label>
                                        <input type="text" id="orderContact" name="contact" class="orderModalInput" placeholder="kontakt@foretag.se">
                                    </div>
                                    <div class="orderModalGroup">
                                        <label class="orderModalLabel" for="orderCompanyDomain">Webbadress</label>
                                        <input type="text" id="orderCompanyDomain" name="company_domain" class="orderModalInput" placeholder="exempel.se">
                                    </div>
                                </div>

                                <details class="techCredentialsDetails">
                                    <summary class="techCredentialsSummary">Inloggningsuppgifter (valfritt, för valfri tjänst)</summary>
                                    <div class="orderModalRow twoCol" style="margin-top: 0.75rem;">
                                        <div class="orderModalGroup">
                                            <label class="orderModalLabel" for="orderAdminUser">Användarnamn</label>
                                            <input type="text" id="orderAdminUser" name="company_admin_username" class="orderModalInput" placeholder="Användarnamn">
                                        </div>
                                        <div class="orderModalGroup">
                                            <label class="orderModalLabel" for="orderAdminPass">Lösenord</label>
                                            <input type="password" id="orderAdminPass" name="company_admin_password" class="orderModalInput" placeholder="Lösenord" autocomplete="new-password">
                                            <label class="correctionPasswordClear" id="correctionPasswordClear" hidden><input type="checkbox" id="orderClearPassword"> Rensa sparat lösenord</label>
                                        </div>
                                    </div>
                                </details>
                                <p class="orderContactHint" id="orderContactHint">Kontaktpersoner med namn sparas automatiskt och kan väljas till fler uppgifter.</p>
                            </div>
                        </details>

                        <!-- Uppdragsbeskrivning -->
                        <div class="paperSection">
                            <div class="paperSectionHeader flexBetween">
                                <label class="orderModalLabel" for="orderDesc">Beskrivning</label>
                                <div id="asapSwitchWrapper" class="asapSwitchWrapper">
                                    <label class="asapLabel">
                                        <input type="checkbox" id="orderAsapCheck" class="orderModalCheckbox">
                                        <span class="asapBadge">Markera som akut</span>
                                    </label>
                                </div>
                            </div>

                            <div class="orderModalGroup">
                                <textarea id="orderDesc" name="order_desc" class="orderModalTextarea" placeholder="Beskriv uppdraget här..."></textarea>
                            </div>
                        </div>

                        <!-- Delmoment & Checklista -->
                        <div class="paperSection">
                            <label class="orderModalLabel" for="orderStepInput">Delmoment / Checklista</label>
                            <div class="stepsInputRow">
                                <div class="stepInputWrapper">
                                    <input type="text" id="orderStepInput" class="orderModalInput" placeholder="Lägg till delmoment...">
                                </div>
                                <button type="button" id="orderAddStepBtn" class="addStepBtn">
                                    <span>Lägg till</span>
                                </button>
                            </div>
                            <div id="orderStepsList" class="stepsList" style="display: none;"></div>
                        </div>

                        <!-- Bilagor -->
                        <div class="paperSection">
                            <label class="orderModalLabel">Bifoga filer</label>
                            <div id="orderDropZone" class="dropZone">
                                <span class="dropZoneText">Dra och släpp filer här, eller klicka för att välja</span>
                                <input type="file" id="orderDropZoneInput" class="dropZoneInput" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf">
                            </div>
                            <div id="orderImagePreviewGrid" class="imagePreviewGrid" style="display: none;"></div>
                            <div id="orderExistingImages" class="correctionExistingImages" hidden></div>
                        </div>

                    </div>
                </div>

                <!-- Footer Toolbar -->
                <div class="orderModalFooter">
                    <div class="footerStatusNote" id="orderFooterStatus">
                        <span class="statusDot"></span>
                        <span>Utkast sparas automatiskt</span>
                    </div>
                    <div class="footerBtnGroup">
                        <button type="button" class="orderModalCancelBtn" id="orderModalCancelBtn">Avbryt</button>
                        <button type="submit" class="orderModalSubmitBtn" id="orderSubmitBtn">
                            <span class="orderModalSpinner"></span>
                            <span>Skapa uppgift</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
