$(function() {
    const STORAGE_KEY = 'line_rich_menu_token';
    const API_URL = 'api.php';
    let currentMenus = []; 
    let activeRequests = 0; 
    let previewImageData = null; 
    let defaultRichMenuId = null; // 現在のデフォルトID

    const RESOLUTIONS = {
        large: { Large: { width: 2500, height: 1686 }, Medium: { width: 1200, height: 810 }, Small: { width: 800, height: 540 } },
        compact: { Large: { width: 2500, height: 843 }, Medium: { width: 1200, height: 405 }, Small: { width: 800, height: 270 } }
    };

    const BASE_W = 2500;
    const TEMPLATES = {
        large: [
            { id: '01', name: '6 Buttons', areas: [{ x: 0, y: 0, w: 833, h: 843, label: 'A' }, { x: 833, y: 0, w: 834, h: 843, label: 'B' }, { x: 1667, y: 0, w: 833, h: 843, label: 'C' }, { x: 0, y: 843, w: 833, h: 843, label: 'D' }, { x: 833, y: 843, w: 834, h: 843, label: 'E' }, { x: 1667, y: 843, w: 833, h: 843, label: 'F' }] },
            { id: '02', name: '4 Buttons (Square)', areas: [{ x: 0, y: 0, w: 1250, h: 843, label: 'A' }, { x: 1250, y: 0, w: 1250, h: 843, label: 'B' }, { x: 0, y: 843, w: 1250, h: 843, label: 'C' }, { x: 1250, y: 843, w: 1250, h: 843, label: 'D' }] },
            { id: '03', name: '4 Buttons (TopL + Bottom3S)', areas: [{ x: 0, y: 0, w: 2500, h: 843, label: 'A' }, { x: 0, y: 843, w: 833, h: 843, label: 'B' }, { x: 833, y: 843, w: 834, h: 843, label: 'C' }, { x: 1667, y: 843, w: 833, h: 843, label: 'D' }] },
            { id: '04', name: '3 Buttons (L+2S)', areas: [{ x: 0, y: 0, w: 1666, h: 1686, label: 'A' }, { x: 1666, y: 0, w: 834, h: 843, label: 'B' }, { x: 1666, y: 843, w: 834, h: 843, label: 'C' }] },
            { id: '05', name: '2 Buttons (V)', areas: [{ x: 0, y: 0, w: 2500, h: 843, label: 'A' }, { x: 0, y: 843, w: 2500, h: 843, label: 'B' }] },
            { id: '06', name: '2 Buttons (H)', areas: [{ x: 0, y: 0, w: 1250, h: 1686, label: 'A' }, { x: 1250, y: 0, w: 1250, h: 1686, label: 'B' }] },
            { id: '07', name: '1 Button', areas: [{ x: 0, y: 0, w: 2500, h: 1686, label: 'A' }] }
        ],
        compact: [
            { id: '01', name: '3 Buttons', areas: [{ x: 0, y: 0, w: 833, h: 843, label: 'A' }, { x: 833, y: 0, w: 834, h: 843, label: 'B' }, { x: 1667, y: 0, w: 833, h: 843, label: 'C' }] },
            { id: '02', name: '2 Buttons (H)', areas: [{ x: 0, y: 0, w: 1250, h: 843, label: 'A' }, { x: 1250, y: 0, w: 1250, h: 843, label: 'B' }] },
            { id: '03', name: '2 Buttons (L+S)', areas: [{ x: 0, y: 0, w: 1666, h: 843, label: 'A' }, { x: 1666, y: 0, w: 834, h: 843, label: 'B' }] },
            { id: '04', name: '2 Buttons (S+L)', areas: [{ x: 0, y: 0, w: 834, h: 843, label: 'A' }, { x: 834, y: 0, w: 1666, h: 843, label: 'B' }] },
            { id: '05', name: '1 Button', areas: [{ x: 0, y: 0, w: 2500, h: 843, label: 'A' }] }
        ]
    };

    const $loading = $('#loading');
    const $richMenuList = $('#rich-menu-list');
    const $tokenInput = $('#channel-token');
    const $inputName = $('#input-name');
    const $inputChatbar = $('#input-chatbar');
    const $btnSubmit = $('#btn-create-submit');
    const $selectedTemplateId = $('#selected-template-id');
    const $previewContainer = $('#preview-container');
    const $imgPreview = $('#img-preview-main');
    const $inputImagePreview = $('#input-image-preview');

    function showLoader() { activeRequests++; $loading.show(); }
    function hideLoader() { activeRequests--; if (activeRequests <= 0) { activeRequests = 0; $loading.hide(); } }

    const savedToken = localStorage.getItem(STORAGE_KEY);
    if (savedToken) { $tokenInput.val(savedToken); updateStatus(true); fetchRichMenus(); }

    function resetCreateForm() {
        $inputName.val(''); $inputChatbar.val(''); $inputImagePreview.val(''); $selectedTemplateId.val('');
        previewImageData = null; $imgPreview.attr('src', '').hide(); $('#preview-placeholder').show();
        $previewContainer.find('.area-overlay').remove(); $('#area-config-container').empty();
        $('#btn-download-guide').prop('disabled', true); validateForm();
    }

    function validateForm() {
        const nameVal = $inputName.val() || ''; const chatbarVal = $inputChatbar.val() || ''; const templateId = $selectedTemplateId.val();
        let isValid = (nameVal.length > 0 && nameVal.length <= 300 && chatbarVal.length > 0 && chatbarVal.length <= 14 && templateId && previewImageData);
        $('.required-field').each(function() { if ($(this).val().trim() === '') isValid = false; });
        $('#name-count').text(`${nameVal.length}/300`); $('#chatbar-count').text(`${chatbarVal.length}/14`);
        $btnSubmit.prop('disabled', !isValid);
    }

    $inputName.on('input', validateForm); $inputChatbar.on('input', validateForm); $(document).on('input', '.required-field', validateForm);

    function updateStatus(isConnected) { $('#status-label').text(isConnected ? 'Connected' : 'Disconnected').css('color', isConnected ? '#06c755' : '#dc3545'); }
    function getToken() { return localStorage.getItem(STORAGE_KEY); }

    $('#fetch-menus').on('click', fetchRichMenus);

    function fetchRichMenus() {
        const token = getToken(); if (!token) return;
        showLoader();
        
        // デフォルトIDを取得してからリストを取得
        $.ajax({ url: API_URL + '?action=get_default', method: 'GET', headers: { 'Authorization': 'Bearer ' + token } })
        .done(res => { defaultRichMenuId = res.richMenuId || null; })
        .always(() => {
            $richMenuList.empty();
            $.ajax({ url: API_URL + '?action=list', method: 'GET', headers: { 'Authorization': 'Bearer ' + token } })
            .done(res => { if (res && res.richmenus) { currentMenus = res.richmenus; res.richmenus.forEach(m => renderRichMenuCard(m)); } })
            .fail(xhr => alert('取得失敗: ' + xhr.responseText)).always(hideLoader);
        });
    }

    function renderRichMenuCard(menu) {
        const isDefault = (menu.richMenuId === defaultRichMenuId);
        const defaultBadge = isDefault ? `<div style="position:absolute; top:10px; right:10px; background:#06c755; color:#fff; padding:4px 10px; border-radius:20px; font-size:0.7rem; font-weight:bold; z-index:10; box-shadow:0 2px 4px rgba(0,0,0,0.2);">★ デフォルト</div>` : '';
        
        const card = $(`
            <div class="rich-menu-card" style="position:relative;">
                ${defaultBadge}
                <div class="image-container" id="img-${menu.richMenuId}"><div class="loader"></div></div>
                <div class="info">
                    <div class="title">${menu.name || 'No Name'}</div>
                    <div class="id">${menu.richMenuId}</div>
                    <div style="font-size: 0.8rem; color: #666; margin-bottom: 10px;">${menu.chatBarText} (${menu.size.width}x${menu.size.height})</div>
                </div>
                <div class="actions">
                    <button class="btn btn-primary btn-set-default" data-id="${menu.richMenuId}" ${isDefault ? 'disabled' : ''}>${isDefault ? '設定済み' : 'デフォルトに設定'}</button>
                    <button class="btn btn-secondary btn-set-user" data-id="${menu.richMenuId}">特定ユーザーに設定</button>
                    <button class="btn btn-info btn-view-json" data-id="${menu.richMenuId}">JSON</button>
                    <button class="btn btn-danger btn-delete" data-id="${menu.richMenuId}">削除</button>
                </div>
            </div>
        `);
        $richMenuList.append(card); fetchRichMenuImage(menu.richMenuId);
    }

    function fetchRichMenuImage(richMenuId) {
        $.ajax({ url: API_URL + '?action=get_image&richMenuId=' + richMenuId, method: 'GET', headers: { 'Authorization': 'Bearer ' + getToken() } })
        .done(res => { if (res && res.image) $(`#img-${richMenuId}`).html(`<img src="${res.image}">`); else $(`#img-${richMenuId}`).html('<p style="font-size: 0.7rem; color: #ccc;">No Image</p>'); })
        .fail(() => $(`#img-${richMenuId}`).html('<p style="font-size: 0.7rem; color: #ccc;">No Image</p>'));
    }

    // デフォルト設定処理
    $(document).on('click', '.btn-set-default', function() {
        const id = $(this).data('id');
        if (confirm('このリッチメニューをアカウント全体のデフォルトに設定しますか？')) {
            showLoader();
            $.ajax({ url: API_URL + '?action=set_default&richMenuId=' + id, method: 'GET', headers: { 'Authorization': 'Bearer ' + getToken() } })
            .done(() => { alert('デフォルトに設定しました'); fetchRichMenus(); })
            .fail(xhr => alert('失敗: ' + xhr.responseText)).always(hideLoader);
        }
    });

    // 特定ユーザー設定 (開発中)
    $(document).on('click', '.btn-set-user', function() {
        alert('「特定ユーザーのリッチメニュー設定」機能は現在開発中です。');
    });

    function renderTemplates() {
        const size = $('#size-select').val(); const resLabel = $('#res-select').val(); 
        const $list = $('#template-list'); $list.empty(); $('#area-config-container').empty();
        const folderName = size.charAt(0).toUpperCase() + size.slice(1);
        let suffix = (resLabel === 'Medium' ? 'm' : (resLabel === 'Small' ? 's' : ''));
        $previewContainer.css('aspect-ratio', size === 'compact' ? '2500 / 843' : '2500 / 1686');
        TEMPLATES[size].forEach(t => {
            const imgSrc = `img/richi_menu_official_temp/${folderName}/${resLabel}/richmenu-template-guide${suffix}-${t.id}.png`;
            const $item = $(`<div class="template-item" data-id="${t.id}" data-path="${imgSrc}" style="cursor:pointer; border:2px solid transparent; padding:5px; border-radius:4px; text-align:center;"><img src="${imgSrc}" style="width:100%; border-radius:2px;" onerror="this.src='https://via.placeholder.com/150?text=Guide'"><div style="font-size:0.7rem; margin-top:5px;">${t.name}</div></div>`);
            $item.on('click', function() {
                $('.template-item').css({'border-color':'transparent','background':'transparent'}); $(this).css({'border-color':'#06c755','background':'#e8f7ed'});
                $selectedTemplateId.val(t.id); $('#btn-download-guide').prop('disabled', false).data('path', imgSrc);
                updatePreviewOverlay(t); generateActionForm(t); validateForm();
            });
            $list.append($item);
        });
        $selectedTemplateId.val(''); $('#btn-download-guide').prop('disabled', true);
        $previewContainer.find('.area-overlay').remove(); validateForm();
    }

    $('#btn-download-guide').on('click', function() { const path = $(this).data('path'); const link = document.createElement('a'); link.href = path; link.download = path.split('/').pop(); link.click(); });

    $inputImagePreview.on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(evt) { previewImageData = file; $imgPreview.attr('src', evt.target.result).show(); $('#preview-placeholder').hide(); validateForm(); };
            reader.readAsDataURL(file);
        } else { previewImageData = null; $imgPreview.hide(); $('#preview-placeholder').show(); validateForm(); }
    });

    function updatePreviewOverlay(template) {
        $previewContainer.find('.area-overlay').remove();
        const baseH = ($('#size-select').val() === 'compact' ? 843 : 1686);
        template.areas.forEach(area => {
            const left = (area.x / BASE_W) * 100; const top = (area.y / baseH) * 100;
            const width = (area.w / BASE_W) * 100; const height = (area.h / baseH) * 100;
            const $overlay = $(`<div class="area-overlay" style="position:absolute; left:${left}%; top:${top}%; width:${width}%; height:${height}%; border:1px dashed #06c755; background:rgba(6,199,85,0.1); display:flex; align-items:center; justify-content:center; color:#06c755; font-weight:bold; font-size:1.2rem; pointer-events:none; box-sizing:border-box; z-index:5;">${area.label}</div>`);
            $previewContainer.append($overlay);
        });
    }

    function generateActionForm(template) {
        const $container = $('#area-config-container'); $container.empty().append('<h4 style="margin:20px 0 10px;">エリア・アクション設定</h4>');
        template.areas.forEach((area, index) => {
            const $row = $(`<div class="action-row" data-index="${index}" style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:15px; border: 1px solid #eee;"><div style="display:flex; gap:10px; align-items:center; margin-bottom:12px;"><span style="background:var(--primary-color); color:#fff; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:bold; font-size:0.8rem;">${area.label}</span><strong>エリア ${area.label}</strong></div><div class="form-group"><label style="font-size: 0.75rem;">タイプ</label><select class="area-type-select"><option value="uri">URLを開く (uri)</option><option value="message">メッセージを送る (message)</option><option value="postback">ポストバック (postback)</option><option value="datetimepicker">日時選択 (datetimepicker)</option></select></div><div class="action-fields-container"><div class="form-group"><label style="font-size: 0.75rem;">ラベル (必須)</label><input type="text" class="required-field area-label" placeholder="例: ボタンをクリック"></div><div class="dynamic-fields"><div class="form-group"><label style="font-size: 0.75rem;">URL</label><input type="text" class="required-field area-value" placeholder="https://..."></div></div></div></div>`);
            $container.append($row);
        });
        $('.area-type-select').on('change', function() {
            const type = $(this).val(); const $dyn = $(this).closest('.action-row').find('.dynamic-fields'); $dyn.empty();
            if (type === 'uri') $dyn.append(`<div class="form-group"><label style="font-size: 0.75rem;">URL</label><input type="text" class="required-field area-value" placeholder="https://..."></div>`);
            else if (type === 'message') $dyn.append(`<div class="form-group"><label style="font-size: 0.75rem;">テキスト</label><input type="text" class="required-field area-value" placeholder="メッセージ"></div>`);
            else if (type === 'postback') $dyn.append(`<div class="form-group"><label style="font-size: 0.75rem;">データ</label><input type="text" class="required-field area-value" placeholder="data"></div><div class="form-group"><label style="font-size: 0.75rem;">表示文字</label><input type="text" class="area-display-text" placeholder="任意"></div>`);
            else if (type === 'datetimepicker') $dyn.append(`<div class="form-group"><label style="font-size: 0.75rem;">データ</label><input type="text" class="required-field area-value" placeholder="data"></div><div class="form-group"><label style="font-size: 0.75rem;">モード</label><select class="area-mode"><option value="datetime">日時</option><option value="date">日付</option><option value="time">時刻</option></select></div>`);
            validateForm();
        });
    }

    $('#size-select, #res-select').on('change', renderTemplates);
    $('#open-create-modal').on('click', () => { resetCreateForm(); $('#create-modal').fadeIn(); renderTemplates(); });

    $('#create-form').on('submit', function(e) {
        e.preventDefault(); const token = getToken(); const sizeType = $('#size-select').val(); const resLabel = $('#res-select').val();
        const template = TEMPLATES[sizeType].find(t => t.id === $selectedTemplateId.val());
        const targetRes = RESOLUTIONS[sizeType][resLabel]; const scale = targetRes.width / BASE_W;
        const areas = template.areas.map((area, index) => {
            const $row = $(`.action-row[data-index="${index}"]`); const type = $row.find('.area-type-select').val();
            const action = { type: type, label: $row.find('.area-label').val().trim() };
            const val = $row.find('.area-value').val().trim();
            if (type === 'uri') action.uri = val; else if (type === 'message') action.text = val;
            else if (type === 'postback') { action.data = val; const disp = $row.find('.area-display-text').val().trim(); if (disp) action.displayText = disp; }
            else if (type === 'datetimepicker') { action.data = val; action.mode = $row.find('.area-mode').val(); }
            const hScale = (sizeType === 'compact' ? 843 : 1686);
            return { bounds: { x: Math.round(area.x * scale), y: Math.round(area.y * (targetRes.height / hScale)), width: Math.round(area.w * scale), height: Math.round(area.h * (targetRes.height / hScale)) }, action: action };
        });
        showLoader();
        $.ajax({ url: API_URL + '?action=create', method: 'POST', headers: { 'Authorization': 'Bearer ' + token }, data: JSON.stringify({ size: targetRes, selected: false, name: $inputName.val(), chatBarText: $inputChatbar.val(), areas: areas }), contentType: 'application/json' })
        .done(res => {
            const fd = new FormData(); fd.append('richMenuId', res.richMenuId); fd.append('image', previewImageData);
            $.ajax({ url: API_URL + '?action=upload', method: 'POST', headers: { 'Authorization': 'Bearer ' + token }, data: fd, processData: false, contentType: false })
            .done(() => { alert('作成が完了しました'); $('#create-modal').fadeOut(); resetCreateForm(); fetchRichMenus(); })
            .fail(xhr => alert('画像送信失敗: ' + xhr.responseText));
        }).fail(xhr => alert('作成失敗: ' + xhr.responseText)).always(hideLoader);
    });

    $(document).on('click', '.btn-delete', function() { if (confirm('削除しますか？')) { showLoader(); $.ajax({ url: API_URL + '?action=delete&richMenuId=' + $(this).data('id'), method: 'GET', headers: { 'Authorization': 'Bearer ' + getToken() } }).done(() => fetchRichMenus()).always(hideLoader); } });
    $('.nav-item').on('click', function() { $('.nav-item').removeClass('active'); $(this).addClass('active'); $('.section').removeClass('active'); $('#' + $(this).data('section')).addClass('active'); });
    $('#save-token').on('click', function() { const t = $tokenInput.val().trim(); if (t) { localStorage.setItem(STORAGE_KEY, t); updateStatus(true); alert('保存しました'); fetchRichMenus(); } });
    $('#clear-token').on('click', function() { if (confirm('削除？')) { localStorage.removeItem(STORAGE_KEY); $tokenInput.val(''); updateStatus(false); $richMenuList.empty(); } });
    $(document).on('click', '.btn-view-json', function() { const m = currentMenus.find(x => x.richMenuId === $(this).data('id')); if (m) { $('#json-display').text(JSON.stringify(m, null, 4)); $('#json-modal').fadeIn(); } });
    $('#copy-json').on('click', () => navigator.clipboard.writeText($('#json-display').text()).then(() => alert('JSONコピー済')));
    $('.close-modal').on('click', () => { $('.modal').fadeOut(); if ($('#create-modal').is(':visible')) resetCreateForm(); });
    $(window).on('click', (e) => { if ($(e.target).hasClass('modal')) { $('.modal').fadeOut(); resetCreateForm(); } });
});
