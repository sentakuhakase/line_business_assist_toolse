<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE Rich Menu Expert</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h1>LINE Tools</h1>
        <div id="status-label" style="font-size: 0.7rem; margin-top: 5px; color: #dc3545; font-weight: bold;">Disconnected</div>
    </div>
    <ul class="nav-menu">
        <li class="nav-item active" data-section="section-manage">リッチメニュー管理</li>
        <li class="nav-item" data-section="section-config">接続設定</li>
    </ul>
</div>

<div class="main-content">
    <div class="container">
        
        <!-- 管理セクション -->
        <div id="section-manage" class="section active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>リッチメニュー一覧</h2>
                <div>
                    <button id="fetch-menus" class="btn btn-primary">更新</button>
                    <button id="open-create-modal" class="btn btn-secondary">新規作成</button>
                </div>
            </div>

            <div id="loading" class="hidden" style="text-align: center; padding: 40px;">
                <div class="loader"></div> 読み込み中...
            </div>
            
            <div id="rich-menu-list" class="rich-menu-grid">
                <!-- JSで動的に追加されます -->
            </div>
        </div>

        <!-- 設定セクション -->
        <div id="section-config" class="section">
            <h2>接続設定</h2>
            <div class="panel">
                <div class="form-group">
                    <label for="channel-token">Channel Access Token</label>
                    <input type="password" id="channel-token" placeholder="Enter your Channel Access Token">
                    <p style="font-size: 0.75rem; color: #666; margin-top: 10px;">
                        ※トークンはサーバーに保存されず、ブラウザのLocal Storageにのみ保持されます。
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button id="save-token" class="btn btn-primary">設定を保存</button>
                    <button id="clear-token" class="btn btn-secondary">リセット</button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- JSON表示モーダル -->
<div id="json-modal" class="modal">
    <div class="modal-content">
        <h3>リッチメニュー配列 (JSON)</h3>
        <pre id="json-display" class="json-viewer"></pre>
        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
            <button id="copy-json" class="btn btn-info">JSONをコピー</button>
            <button type="button" class="btn btn-secondary close-modal">閉じる</button>
        </div>
    </div>
</div>

<!-- 新規作成モーダル -->
<div id="create-modal" class="modal">
    <div class="modal-content">
        <h3>新規リッチメニュー作成</h3>
        <form id="create-form">
            <!-- STEP 1: 基本情報 -->
            <div class="panel-inner" style="background: #fdfdfd; padding: 15px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 20px;">
                <div class="form-group">
                    <label>名前 (管理用)</label>
                    <input type="text" id="input-name" name="name" placeholder="例: Spring Campaign" required>
                    <div class="char-counter">
                        <span id="name-error" class="error-msg"></span>
                        <span id="name-count">0/300</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>チャットバーテキスト</label>
                    <input type="text" id="input-chatbar" name="chatBarText" placeholder="例: メニューを開く" required>
                    <div class="char-counter">
                        <span id="chatbar-error" class="error-msg"></span>
                        <span id="chatbar-count">0/14</span>
                    </div>
                </div>
                <div class="form-group" style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label>タイプ</label>
                        <select name="size" id="size-select">
                            <option value="large">Large</option>
                            <option value="compact">Compact</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label>解像度 (ガイドサイズ)</label>
                        <select id="res-select">
                            <option value="Large">Large (2500px)</option>
                            <option value="Medium">Medium (1200px)</option>
                            <option value="Small">Small (800px)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- STEP 2: テンプレート選択 & ガイドDL -->
            <div class="form-group">
                <label>1. レイアウトテンプレートを選択</label>
                <div id="template-list" class="template-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; max-height: 180px; overflow-y: auto; padding: 10px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 10px;">
                    <!-- JSで動的に追加されます -->
                </div>
                <input type="hidden" name="templateId" id="selected-template-id" required>
                <button type="button" id="btn-download-guide" class="btn btn-secondary" style="width: 100%;" disabled>選択したテンプレートのガイド画像をDL</button>
            </div>

            <!-- STEP 3: 画像アップロード & プレビュー -->
            <div id="step-image-upload" style="margin-top: 25px;">
                <label>2. 制作した画像をアップロード (プレビュー用)</label>
                <input type="file" id="input-image-preview" accept="image/png, image/jpeg" style="margin-bottom: 15px;">
                
                <div id="preview-container" style="position: relative; width: 100%; max-width: 500px; margin: 0 auto; background: #eee; border: 2px dashed #ccc; aspect-ratio: 2500 / 1686; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 8px;">
                    <span id="preview-placeholder" style="color: #999;">画像をアップロードしてください</span>
                    <img id="img-preview-main" style="display: none; width: 100%; height: 100%; object-fit: contain;">
                    <!-- エリアオーバーレイがここにJSで追加されます -->
                </div>
            </div>

            <!-- STEP 4: アクション設定 -->
            <div id="area-config-container" style="max-height: 300px; overflow-y: auto; margin-top: 25px; padding-right: 5px;">
                <!-- テンプレート選択後にJSで生成されます -->
            </div>

            <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <button type="submit" id="btn-create-submit" class="btn btn-primary" style="flex: 2; padding: 15px;" disabled>LINEにリッチメニューを作成・登録</button>
                <button type="button" class="btn btn-secondary close-modal" style="flex: 1;">キャンセル</button>
            </div>
        </form>
    </div>
</div>

<!-- 画像アップロードモーダル (既存) -->
<div id="upload-modal" class="modal">
    <div class="modal-content">
        <h3>リッチメニュー画像のアップロード</h3>
        <p id="upload-menu-id" style="font-size: 0.8rem; color: #666;"></p>
        <form id="upload-form">
            <input type="hidden" name="richMenuId" id="target-rich-menu-id">
            <div class="form-group">
                <label>画像ファイル (PNG/JPEG, 規定サイズ)</label>
                <input type="file" name="image" accept="image/png, image/jpeg" required>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">アップロード</button>
                <button type="button" class="btn btn-secondary close-modal">キャンセル</button>
            </div>
        </form>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
