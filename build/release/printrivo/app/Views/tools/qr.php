<!doctype html>
<html lang="en">
<head>
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Printrivo QR Generator | Printrivo</title>
    <meta name="description" content="Printrivo QR Generator with built-in icon and custom logo support.">
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/printrivo_light_logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/img/printrivo_light_logo.png') ?>">
    <style>
        :root {
            color-scheme: dark;
            --ink: #f7f9ff;
            --muted: #aeb7c7;
            --line: rgba(255, 255, 255, 0.22);
            --panel: #05070b;
            --panel-soft: #0b1018;
            --soft: #101827;
            --accent: #1d3557;
            --accent-strong: #8fbad3;
            --danger: #ff6b6b;
            --shadow: 0 18px 50px rgba(0, 0, 0, 0.42);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 22% 0%, rgba(69, 123, 157, 0.30), transparent 34%),
                linear-gradient(180deg, #030407 0%, #070a10 52%, #020306 100%);
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        textarea {
            resize: vertical;
        }

        .app-shell {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 20px 0 34px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 148px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }

        .brand-lockup {
            display: grid;
            grid-template-columns: 112px minmax(0, 1fr);
            gap: 18px;
            align-items: center;
            min-width: 0;
        }

        .brand-logo {
            width: 112px;
            height: 112px;
            object-fit: contain;
        }

        .brand-kicker {
            margin: 0 0 6px;
            color: var(--accent-strong);
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        h1 {
            margin: 0;
            font-size: clamp(32px, 5vw, 58px);
            line-height: 0.96;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .topbar p {
            margin: 10px 0 0;
            color: var(--muted);
            max-width: 720px;
            line-height: 1.45;
            font-size: 16px;
        }

        .actions,
        .button-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--ink);
            padding: 0 14px;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(180deg, #1d3557 0%, #1d3557 100%);
            border-color: #457b9d;
            color: white;
        }

        .btn-success {
            background: white;
            border-color: white;
            color: #1d3557;
            font-weight: 800;
        }

        .btn-outline-primary {
            border-color: #457b9d;
            color: white;
        }

        .btn:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .btn:hover:not(:disabled) {
            border-color: var(--accent-strong);
        }

        .tool-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 0.9fr);
            gap: 18px;
            align-items: stretch;
        }

        .card {
            height: 100%;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-body {
            padding: 22px;
        }

        .form-label {
            display: block;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .form-control,
        .form-select {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 12px;
            background: #0b1018;
            color: white;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            accent-color: var(--accent-strong);
        }

        .mb-3 {
            margin-bottom: 16px;
        }

        .alert {
            border: 1px solid rgba(255, 107, 107, 0.45);
            border-radius: 8px;
            padding: 12px;
            color: white;
            background: rgba(255, 107, 107, 0.16);
        }

        .text-break {
            overflow-wrap: anywhere;
        }

        .text-muted {
            color: var(--muted);
        }

        .preview-card .card-body {
            min-height: 520px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        #qrcanvas{
            max-width: 100%;
            height: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        #img_preview{
            max-width: 180px;
            max-height: 180px;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px;
            background: white;
        }

        .d-none {
            display: none;
        }

        @media (max-width: 900px) {
            .topbar {
                align-items: start;
                flex-direction: column;
            }

            .brand-lockup {
                grid-template-columns: 88px minmax(0, 1fr);
            }

            .brand-logo {
                width: 88px;
                height: 88px;
            }

            .actions {
                justify-content: flex-start;
            }

            .tool-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="<?= base_url('assets/css/brand.css?v=20260918-mobile') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/report-bug.css') ?>">
</head>
<body class="qr-tool">
    <main class="app-shell">
        <header class="topbar">
            <div class="brand-lockup">
                <img class="brand-logo" src="<?= base_url('assets/img/printrivo_dark_logo.png') ?>" alt="Printrivo logo">
                <div>
                    <h1>Printrivo QR Generator</h1>
                    <p>Create QR codes with built-in icons or uploaded customer artwork.</p>
                </div>
            </div>
            <div class="actions">
                <a class="btn" href="<?= site_url('dashboard') ?>">Dashboard</a><?= view('partials/report_bug_button') ?>
            </div>
        </header>

        <section class="tool-grid">
            <div>
                <div class="card">
                    <div class="card-body">
                        <form action="<?= site_url('tools/qr') ?>" method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea id="content" name="content" rows="6" class="form-control"><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input
                                        type="checkbox"
                                        id="custom_logo_checkbox"
                                        name="custom_logo_checkbox"
                                        class="form-check-input"
                                        <?php echo $useCustomLogo ? 'checked' : ''; ?>
                                    >
                                    <label for="custom_logo_checkbox" class="form-check-label">Use custom logo upload</label>
                                </div>
                            </div>

                            <div class="mb-3" id="logo_select_wrap" <?php echo $useCustomLogo ? 'style="display:none;"' : ''; ?>>
                                <label for="logo_select" class="form-label">Choose SVG icon</label>
                                <select id="logo_select" name="logo_select" class="form-select">
                                    <option value="" <?php echo $selectedLogo === '' ? 'selected' : ''; ?>>No icon</option>
                                    <?php foreach($iconFiles as $file): ?>
                                        <option value="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedLogo === $file ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3" id="custom_logo_wrap" <?php echo !$useCustomLogo ? 'style="display:none;"' : ''; ?>>
                                <label for="custom_logo" class="form-label">Upload custom logo</label>
                                <input type="file" id="custom_logo" name="custom_logo" class="form-control" accept=".png,.jpg,.jpeg,.svg,.webp">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Logo preview</label><br>
                                <img
                                    id="img_preview"
                                    src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="img-thumbnail <?php echo $logoPath ? '' : 'd-none'; ?>"
                                    alt="Logo preview"
                                >
                            </div>

                            <div class="button-row">
                                <button type="submit" class="btn btn-primary">Generate</button>
                                <button type="button" id="download_png" class="btn btn-success" <?php echo $result ? '' : 'disabled'; ?>>Download PNG</button>
                                <button type="button" id="download_svg" class="btn btn-outline-primary" <?php echo $result ? '' : 'disabled'; ?>>Download SVG</button>
                            </div>
                        </form>

                        <?php if($error !== ''): ?>
                            <div class="alert alert-danger mt-3 mb-0">
                                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card preview-card">
                    <div class="card-body">
                        <?php if($content !== ''): ?>
                            <p class="mb-3 text-break">
                                <strong>Current content:</strong>
                                <?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>

                        <canvas id="qrcanvas" width="400" height="400"></canvas>

                        <?php if(!$result): ?>
                            <div class="text-muted mt-3">Generate a QR code to preview it here.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= base_url('assets/js/canvas2svg.js') ?>"></script>
<script>
(function($){
    const qrSrc = <?php echo json_encode($result); ?>;
    const builtInLogoBase = <?php echo json_encode($iconsDirWeb); ?>;
    const initialLogoPath = <?php echo json_encode($logoPath); ?>;

    const DPI = 300;
    const PRINT_SIZE_IN = 5; // fixed print size (inches)

    const PREVIEW_SIZE = 400;
    const EXPORT_SIZE = DPI * PRINT_SIZE_IN; 
    const SVG_SIZE = DPI * PRINT_SIZE_IN;

    $(function(){
        initLogoInputs();
        bindEvents();

        if(initialLogoPath){
            setPreviewImage(initialLogoPath);
        }

        if(qrSrc){
            drawVisibleCanvas();
        }
    });

    function bindEvents(){
        $('#custom_logo_checkbox').on('change', function(){
            toggleLogoInputs();

            if(this.checked){
                clearPreviewImage();
            } else {
                updateBuiltInPreview();
            }

            if(qrSrc){
                drawVisibleCanvas();
            }
        });

        $('#logo_select').on('change', function(){
            updateBuiltInPreview();

            if(qrSrc){
                drawVisibleCanvas();
            }
        });

        $('#custom_logo').on('change', function(e){
            const file = e.target.files[0];

            if(file){
                const previewUrl = URL.createObjectURL(file);
                setPreviewImage(previewUrl);

                if(qrSrc){
                    drawVisibleCanvas();
                }
            }
        });

        $('#download_png').on('click', async function(){
            if(!qrSrc){
                return;
            }

            try{
                const canvas = await buildCanvas(EXPORT_SIZE);
                downloadDataUrl(canvas.toDataURL('image/png'), 'custom_qr_code_' + Date.now() + '.png');
            }
            catch(err){
                console.error(err);
                alert('Could not generate the PNG download.');
            }
        });

        $('#download_svg').on('click', async function(){
            if(!qrSrc){
                return;
            }

            try{
                const svgText = await buildSVG(SVG_SIZE);
                const blob = new Blob([svgText], {type: 'image/svg+xml;charset=utf-8'});
                const url = URL.createObjectURL(blob);

                downloadDataUrl(url, 'custom_qr_code_' + Date.now() + '.svg');

                setTimeout(function(){
                    URL.revokeObjectURL(url);
                }, 1000);
            }
            catch(err){
                console.error(err);
                alert('Could not generate the SVG download.');
            }
        });
    }

    function initLogoInputs(){
        toggleLogoInputs();

        if(!$('#custom_logo_checkbox').is(':checked')){
            updateBuiltInPreview();
        }
    }

    function toggleLogoInputs(){
        const useCustom = $('#custom_logo_checkbox').is(':checked');
        $('#logo_select_wrap').toggle(!useCustom);
        $('#custom_logo_wrap').toggle(useCustom);
    }

    function getSelectedBuiltInLogoSrc(){
        const file = $('#logo_select').val();
        return file ? builtInLogoBase + file + '?v=' + Date.now() : '';
    }

    function updateBuiltInPreview(){
        const src = getSelectedBuiltInLogoSrc();

        if(src){
            setPreviewImage(src);
        } else {
            clearPreviewImage();
        }
    }

    function setPreviewImage(src){
        $('#img_preview')
            .attr('src', src)
            .removeClass('d-none');
    }

    function clearPreviewImage(){
        $('#img_preview')
            .removeAttr('src')
            .addClass('d-none');
    }

    function getCurrentLogoSrc(){
        const preview = document.getElementById('img_preview');

        if(!preview || preview.classList.contains('d-none')){
            return '';
        }

        return preview.getAttribute('src') || '';
    }

    function downloadDataUrl(url, filename){
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function loadImage(src){
        return new Promise(function(resolve, reject){
            if(!src){
                resolve(null);
                return;
            }

            const img = new Image();
            img.crossOrigin = 'anonymous';

            img.onload = function(){
                resolve(img);
            };

            img.onerror = function(){
                reject(new Error('Failed to load image: ' + src));
            };

            img.src = src;
        });
    }

    async function buildAssets(){
        const logoSrc = getCurrentLogoSrc();

        const qrImage = await loadImage(qrSrc);
        const logoImage = logoSrc ? await loadImage(logoSrc) : null;

        return { qrImage, logoImage };
    }

    async function buildCanvas(size){
        const { qrImage, logoImage } = await buildAssets();

        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;

        const ctx = canvas.getContext('2d');

        ctx.clearRect(0, 0, size, size);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(qrImage, 0, 0, size, size);

        if(logoImage){
            drawCenteredLogo(ctx, size, size, logoImage);
        }

        return canvas;
    }

    async function buildSVG(size){
        const { qrImage, logoImage } = await buildAssets();

        const ctx = new C2S(size, size);

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(qrImage, 0, 0, size, size);

        if(logoImage){
            drawCenteredLogo(ctx, size, size, logoImage);
        }

        return ctx.getSerializedSvg(true);
    }

    function drawCenteredLogo(ctx, canvasWidth, canvasHeight, logoImage){
        const logoBoxSize = canvasWidth * 0.23;
        const circleRadius = canvasWidth * 0.13;

        ctx.beginPath();
        ctx.arc(canvasWidth / 2, canvasHeight / 2, circleRadius, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();

        const naturalWidth = logoImage.naturalWidth || 0;
        const naturalHeight = logoImage.naturalHeight || 0;

        if(naturalWidth <= 0 || naturalHeight <= 0){
            return;
        }

        const scale = Math.min(logoBoxSize / naturalWidth, logoBoxSize / naturalHeight);
        const drawWidth = naturalWidth * scale;
        const drawHeight = naturalHeight * scale;
        const drawX = (canvasWidth - drawWidth) / 2;
        const drawY = (canvasHeight - drawHeight) / 2;

        ctx.drawImage(logoImage, drawX, drawY, drawWidth, drawHeight);
    }

    async function drawVisibleCanvas(){
        const visibleCanvas = document.getElementById('qrcanvas');

        if(!visibleCanvas || !qrSrc){
            return;
        }

        try{
            const previewCanvas = await buildCanvas(PREVIEW_SIZE);
            const ctx = visibleCanvas.getContext('2d');

            ctx.clearRect(0, 0, visibleCanvas.width, visibleCanvas.height);
            ctx.drawImage(previewCanvas, 0, 0, visibleCanvas.width, visibleCanvas.height);
        }
        catch(err){
            console.error(err);
        }
    }
})(jQuery);
</script>
<?= view('security/recaptcha') ?>
<?= view('partials/report_bug_dialog', ['toolRoute' => 'tools/qr']) ?>
</body>
</html>
