<?php
   define('login_req', true);

   require 'global.php';

   if (isset($_SESSION['addOrder'])) {
       unset($_SESSION['addOrder']);
   }

   $auth->userLoginCheck();

   if(!isset($_SESSION['focusOrder']))
        $_GET['dir'] = $dir = $auth->setDir();
    else
        $_GET['dir'] = $dir = $_SESSION['focusOrder']['dir'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="ui/style/css/app.css?v=2.8" rel="stylesheet">
    <script>
        workflow = false;
        var Direction = "<?php echo $_GET['dir']; ?>";
        var orderInFocus = "<?php if(isset($_SESSION['focusOrder'])) echo 'true'; else echo 'false'; ?>";
        var CurrentUser = {
            id: <?php echo (int)($_SESSION['user']['userid'] ?? 1); ?>,
            username: "<?php echo addslashes($_SESSION['user']['username'] ?? ''); ?>",
            role: <?php echo (int)($_SESSION['user']['user_role'] ?? 1); ?>
        };
        var TeamWorkers = <?php echo json_encode($main->getWorkersList(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <link href="ui/style/css/general.css" rel="stylesheet">
    <link href="ui/style/css/orderModal.css?v=2.8" rel="stylesheet">
    <link href="ui/style/css/macDock.css?v=2.7" rel="stylesheet">
    <script src="ui/js/jquery.js"></script>
    <script src="ui/js/general.js"></script>
    <script src="ui/js/orderModal.js?v=2.7"></script>
    <script src="ui/js/macDock.js?v=2.7"></script>
    <script src="ui/js/app.js?v=2.6"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>



    <title>Workflow</title>
</head>
<body>

<div class="container">

        <div class="orders">
        
        </div>

        <!-- Snyggt kort för historik över skapade / delegerade uppdrag -->
        <aside class="createdHistoryCard" id="createdHistoryCard">
            <div class="historyCardHeader">
                <div class="historyCardTitle">
                    <span class="historyIcon">📋</span>
                    <h3>Skapade uppdrag</h3>
                    <span class="historyCountBadge" id="historyCountBadge">0</span>
                </div>
                <div class="historyCardActions">
                    <button type="button" class="historyRefreshBtn" id="historyRefreshBtn" title="Uppdatera historik">↻</button>
                    <button type="button" class="historyToggleCollapseBtn" id="historyToggleCollapseBtn" title="Minimera / Maximera">−</button>
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
                        <button type="button" class="dockSubmenuItem historyFloatTrigger" id="historyFloatTrigger" role="menuitem">
                            <span>📋 Skapade uppdrag</span>
                            <span class="historyFloatBadge" id="historyFloatBadge">0</span>
                        </button>

                        <button type="button" class="dockSubmenuItem" id="dockWorkflowBtn" data-url="prio" role="menuitem">
                            <svg class="dockSubmenuSvg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="7" height="7" rx="1"></rect>
                                <rect x="14" y="4" width="7" height="7" rx="1"></rect>
                                <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                                <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            </svg>
                            <div class="dockSubmenuItemContent">
                                <span class="dockSubmenuItemTitle">Workflow</span>
                                <span class="dockSubmenuItemDesc">Kanban och prioritering</span>
                            </div>
                        </button>

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

            <!-- Floating Dock Shelf (Text-based, compact height) -->
            <div class="macDock textDock" id="macDock" role="toolbar">
                <!-- Primär action: + Ny uppgift -->
                <?php if($auth->hasRight('add_new_order')): ?>
                <button type="button" class="dockTextBtn dockBtnPrimary addOrder" aria-label="Skapa ny uppgift">
                    <span class="dockBtnPlus">+</span>
                    <span>Ny uppgift</span>
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

                <div class="dockDivider dockUtilityDivider" role="separator"></div>

                <!-- Order Status / Kategori Filter Badge Tabs (#parentStats) -->
                <?php $main->getOrderNav(); ?>

                <div class="dockDivider dockUtilityDivider" role="separator"></div>

                <!-- Submeny Trigger: Mer (Admin, Papperskorg, Logga ut) -->
                <button type="button" class="dockTextBtn dockBtnMore" id="dockMoreTrigger" aria-label="Fler alternativ" aria-haspopup="true" aria-expanded="false" title="Mer (Workflow, Skapade, Admin, Papperskorg, Logga ut)">
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



    <div hidden id="focusOrder"><?php if(isset($_SESSION['focusOrder'])) echo $_SESSION['focusOrder']['orderid']; ?></div>
        <div class="modalFocus" <?php if(isset($_SESSION['focusOrder'])) echo 'style="display: block;'; ?>></div>
        <div class="modal">
            <form class="timeForm" method="post">
                <input type="text" name="orderid" class="orderid" value="" hidden>
                <input type="text" name="action" class="action" value="" hidden>
                <h5 class="modalTitle">Meddelande / Kommentar (valfritt)</h5>
                <textarea class="content" name="comment"></textarea>
                <input type="submit" value="Spara" class="saveTime">
                <input type="button" class="closeTime close" value="Ångra">
            </form>
        </div>

    <?php if($auth->hasRight('add_new_order')): ?>
    <!-- Clean Paper Sheet Order Modal -->
    <div class="orderModalOverlay" id="orderModalOverlay">
        <div class="orderModalCard" role="dialog" aria-modal="true" aria-labelledby="orderModalHeading">
            <div class="orderModalSheetHandle" aria-hidden="true"></div>

            <div class="orderModalHeader">
                <div class="orderModalHeaderTop">
                    <div class="orderModalHeaderTitle">
                        <h2 id="orderModalHeading">Ny uppgift</h2>
                        <span class="orderDraftBadge" id="orderDraftBadge" style="display: none;">Sparat</span>
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
                            <span class="chipIcon">⚡</span>
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
                                        </div>
                                    </div>
                                </details>
                                <p class="orderContactHint">Kontaktpersoner med namn sparas automatiskt och kan väljas till fler uppgifter.</p>
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
                                <input type="file" id="orderDropZoneInput" class="dropZoneInput" multiple accept="image/*">
                            </div>
                            <div id="orderImagePreviewGrid" class="imagePreviewGrid" style="display: none;"></div>
                        </div>

                    </div>
                </div>

                <!-- Footer Toolbar -->
                <div class="orderModalFooter">
                    <div class="footerStatusNote">
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
    <?php endif; ?>

</body>
</html>
