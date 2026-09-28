<?php
declare(strict_types=1);

header('Location: builder.php');
exit;

require __DIR__ . '/inc/image_info.php';

dtf_cleanup_temp_uploads(__DIR__ . '/uploads');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTF Quote Calculator | Printrivo</title>
    <meta name="description" content="Printrivo DTF quote calculator for artwork sizing, line pricing, quote printing, and invoice printing.">
    <link rel="icon" type="image/png" href="../assets/img/printrivo_light_logo.png">
    <link rel="apple-touch-icon" href="../assets/img/printrivo_light_logo.png">
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
            --accent-soft: rgba(69, 123, 157, 0.20);
            --warn: #ffd166;
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
        textarea {
            font: inherit;
        }

        textarea {
            resize: vertical;
        }

        .app-shell {
            width: min(1420px, calc(100% - 32px));
            margin: 0 auto;
            padding: 20px 0 34px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 158px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }

        .brand-lockup {
            display: grid;
            grid-template-columns: 118px minmax(0, 1fr);
            gap: 18px;
            align-items: center;
            min-width: 0;
        }

        .brand-logo {
            width: 118px;
            height: 118px;
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
            font-size: clamp(32px, 5vw, 62px);
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

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            min-height: 42px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--ink);
            padding: 0 14px;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn.primary {
            background: linear-gradient(180deg, #1d3557 0%, #1d3557 100%);
            border-color: #457b9d;
            color: white;
        }

        .btn.invoice {
            background: white;
            border-color: white;
            color: #1d3557;
            font-weight: 800;
        }

        .btn.tools-home {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .btn:hover {
            border-color: var(--accent-strong);
        }

        .workspace {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 18px;
            align-items: start;
        }

        .quote-panel,
        .summary-panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .upload-zone {
            display: grid;
            place-items: center;
            min-height: 190px;
            margin: 16px;
            border: 2px dashed rgba(69, 123, 157, 0.72);
            border-radius: 8px;
            background:
                linear-gradient(180deg, rgba(69, 123, 157, 0.22), rgba(255, 255, 255, 0.04)),
                var(--panel-soft);
            text-align: center;
            transition: border-color 0.16s ease, background 0.16s ease;
        }

        .upload-zone.is-over {
            border-color: white;
            background: rgba(69, 123, 157, 0.34);
        }

        .upload-zone strong {
            display: block;
            color: white;
            font-size: 23px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .upload-zone span {
            color: var(--muted);
        }

        .upload-zone input {
            position: absolute;
            inline-size: 1px;
            block-size: 1px;
            opacity: 0;
            pointer-events: none;
        }

        .table-wrap {
            border-top: 1px solid var(--line);
        }

        table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            border-collapse: collapse;
            background: #04060a;
        }

        th,
        td {
            padding: 10px 8px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        th {
            color: white;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0;
            background: linear-gradient(180deg, #1d3557 0%, #1d3557 100%);
        }

        .image-cell {
            display: grid;
            grid-template-columns: 50px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
            min-width: 0;
        }

        .thumb {
            width: 50px;
            height: 50px;
            border: 1px solid var(--line);
            border-radius: 6px;
            object-fit: cover;
            background: white;
        }

        .file-name {
            font-weight: 700;
            overflow-wrap: anywhere;
            line-height: 1.25;
        }

        .file-meta {
            color: var(--muted);
            margin-top: 3px;
            font-size: 12px;
        }

        .number-input {
            width: 64px;
            min-height: 38px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 6px 8px;
            background: #0b1018;
            color: white;
        }

        .size-editor {
            display: grid;
            gap: 5px;
            min-width: 0;
        }

        .size-inputs {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--muted);
        }

        .size-input {
            width: 64px;
        }

        .detected-size {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.3;
        }

        .remove-btn {
            width: 34px;
            height: 34px;
            border-radius: 7px;
            border: 1px solid var(--line);
            background: #0b1018;
            color: var(--danger);
            cursor: pointer;
        }

        .warning {
            color: var(--warn);
            line-height: 1.35;
            max-width: 160px;
            font-size: 12px;
        }

        .line-total {
            color: #b7d4e5;
            font-size: 16px;
            font-weight: 800;
            white-space: nowrap;
        }

        th:nth-child(1) {
            width: 27%;
        }

        th:nth-child(2) {
            width: 10%;
        }

        th:nth-child(3) {
            width: 9%;
        }

        th:nth-child(4) {
            width: 16%;
        }

        th:nth-child(5) {
            width: 7%;
        }

        th:nth-child(6),
        th:nth-child(7) {
            width: 9%;
        }

        th:nth-child(8) {
            width: 9%;
        }

        th:nth-child(9) {
            width: 4%;
        }

        .empty-state {
            padding: 28px 16px 34px;
            text-align: center;
            color: var(--muted);
            border-top: 1px solid var(--line);
            background: #04060a;
        }

        .empty-state[hidden] {
            display: none;
        }

        .summary-panel {
            position: sticky;
            top: 18px;
            padding: 16px;
        }

        .panel-title {
            margin: 0 0 14px;
            color: white;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .money-input,
        .field input,
        .field textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px 10px;
            background: #0b1018;
            color: white;
        }

        .field textarea {
            min-height: 78px;
            line-height: 1.35;
        }

        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .totals {
            margin-top: 18px;
            border-top: 1px solid var(--line);
            padding-top: 14px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
            color: var(--muted);
        }

        .total-row[hidden] {
            display: none;
        }

        .total-row strong {
            color: var(--ink);
        }

        .grand-total {
            margin-top: 8px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
            font-size: 26px;
            font-weight: 800;
            color: white;
            text-transform: uppercase;
        }

        .status {
            min-height: 22px;
            margin: 0 16px 12px;
            color: var(--muted);
            font-size: 14px;
        }

        .status.error {
            color: var(--danger);
        }

        .print-header,
        .print-customer {
            display: none;
        }

        @media (max-width: 980px) {
            .workspace {
                grid-template-columns: 1fr;
            }

            .summary-panel {
                position: static;
                order: -1;
            }

            .topbar {
                align-items: start;
                flex-direction: column;
            }

            .brand-lockup {
                grid-template-columns: 92px minmax(0, 1fr);
            }

            .brand-logo {
                width: 92px;
                height: 92px;
            }

            .actions {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                margin: 0.45in;
            }

            body {
                background: white;
                color: #111;
                font-size: 11px;
            }

            body * {
                color: #111;
            }

            .topbar,
            .upload-zone,
            .actions,
            .remove-btn,
            .status,
            .field,
            .panel-title,
            .warning,
            th:nth-child(8),
            td:nth-child(8),
            th:nth-child(9),
            td:nth-child(9) {
                display: none;
            }

            .app-shell {
                width: 100%;
                padding: 0;
            }

            .print-header {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 24px;
                align-items: start;
                padding-bottom: 18px;
                border-bottom: 3px solid #1d3557;
                margin-bottom: 16px;
            }

            .print-brand {
                display: grid;
                grid-template-columns: 84px minmax(0, 1fr);
                gap: 14px;
                align-items: center;
            }

            .print-brand img {
                width: 84px;
                height: 84px;
                object-fit: contain;
            }

            .print-brand h2 {
                margin: 0;
                color: #1d3557;
                font-size: 24px;
                line-height: 1;
                text-transform: uppercase;
            }

            .print-brand p,
            .print-contact p {
                margin: 4px 0 0;
                line-height: 1.35;
            }

            .print-invoice-number {
                color: #1d3557;
                font-size: 14px;
            }

            .print-contact {
                text-align: right;
                font-weight: 700;
            }

            .print-document-title {
                margin: 0 0 8px;
                color: #1d3557;
                font-size: 30px;
                line-height: 1;
                text-transform: uppercase;
            }

            .print-customer {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px;
                margin-bottom: 16px;
            }

            .print-box {
                min-height: 90px;
                border: 1px solid #b8c2d2;
                border-radius: 8px;
                padding: 10px 12px;
            }

            .print-box h3 {
                margin: 0 0 7px;
                color: #1d3557;
                font-size: 12px;
                text-transform: uppercase;
            }

            .print-box p {
                margin: 4px 0;
                line-height: 1.35;
                white-space: pre-wrap;
            }

            .workspace {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .quote-panel {
                order: 1;
            }

            .summary-panel {
                order: 2;
            }

            .quote-panel,
            .summary-panel {
                box-shadow: none;
                color: #111;
                border: 0;
                border-radius: 0;
                overflow: visible;
                background: white;
            }

            .table-wrap {
                overflow: visible;
                border: 1px solid #b8c2d2;
                border-radius: 8px;
            }

            table {
                min-width: 0;
                background: white;
            }

            th {
                background: #1d3557;
                color: white;
                font-size: 10px;
                padding: 8px;
            }

            td {
                padding: 8px;
                border-bottom: 1px solid #d8dde6;
                font-size: 10px;
            }

            td.line-total {
                font-size: 18px;
                font-weight: 800;
                white-space: nowrap;
            }

            .thumb {
                width: 42px;
                height: 42px;
                border-color: #d8dde6;
            }

            .image-cell {
                grid-template-columns: 42px minmax(0, 1fr);
            }

            .file-meta {
                color: #555;
            }

            .number-input {
                width: 38px;
                min-height: auto;
                padding: 0;
                border: 0;
                background: transparent;
                color: #111;
            }

            .size-editor {
                min-width: 0;
                gap: 2px;
            }

            .size-inputs {
                gap: 3px;
                color: #111;
            }

            .size-input {
                width: 28px;
            }

            .detected-size {
                display: none;
            }

            .summary-panel {
                padding: 0;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .totals {
                width: 290px;
                margin: 14px 0 0 auto;
                border: 1px solid #b8c2d2;
                border-radius: 8px;
                padding: 10px 12px;
            }

            .total-row {
                color: #333;
                padding: 5px 0;
            }

            .grand-total {
                color: #1d3557;
                font-size: 18px;
            }

            .grand-total strong {
                color: #1d3557;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/brand.css">
</head>
<body>
    <main class="app-shell">
        <header class="topbar">
            <div class="brand-lockup">
                <img class="brand-logo" src="../assets/img/printrivo_dark_logo.png" alt="Printrivo logo">
                <div>
                    <h1>DTF Quote Calculator</h1>
                    <p>Full color print pricing for pocket, back print, and gang sheet artwork.</p>
                </div>
            </div>
            <div class="actions">
                <a class="btn tools-home" href="../">Back to Suite</a>
                <a class="btn primary tools-home" href="builder.php">Open Printrivo Gang Sheet Builder</a>
                <button class="btn" type="button" id="clearBtn">Clear</button>
                <button class="btn primary" type="button" id="printBtn">Print Quote</button>
                <button class="btn invoice" type="button" id="invoiceBtn">Print Invoice</button>
            </div>
        </header>

        <section class="print-header" aria-label="Printed document header">
            <div class="print-brand">
                <img src="../assets/img/printrivo_light_logo.png" alt="Printrivo logo">
                <div>
                    <h2>Printrivo</h2>
                    <p>Professional print-production tools from Mber Digital.</p>
                </div>
            </div>
            <div class="print-contact">
                <h2 class="print-document-title" id="printDocumentTitle">Quote</h2>
                <p class="print-invoice-number" id="printInvoiceNumber" hidden></p>
                <p id="printDate"></p>
            </div>
        </section>

        <section class="print-customer" aria-label="Printed customer information">
            <div class="print-box">
                <h3>Customer</h3>
                <p id="printCustomerName">-</p>
                <p id="printCustomerContact"></p>
                <p id="printCustomerPhone"></p>
                <p id="printCustomerEmail"></p>
            </div>
            <div class="print-box">
                <h3>Job / Address</h3>
                <p id="printCustomerAddress">-</p>
                <p id="printCustomerNotes"></p>
            </div>
        </section>

        <section class="workspace">
            <div class="quote-panel">
                <label class="upload-zone" id="dropZone">
                    <input id="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                    <span>
                        <strong>Drop PNG designs here</strong>
                        <span>JPG, WebP, and GIF are also accepted</span>
                    </span>
                </label>
                <p class="status" id="status"></p><div id="uploadProgress" hidden style="margin:10px 0;overflow-wrap:anywhere"><div id="uploadProgressLabel" role="status" style="font-size:12px;margin-bottom:6px"></div><progress id="uploadProgressBar" max="100" value="0" aria-label="Artwork upload progress" style="width:100%;height:16px;accent-color:#8fbad3"></progress></div>

                <div class="table-wrap">
                    <table aria-label="Quote line items">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Pixels</th>
                                <th>DPI</th>
                                <th>Print Size</th>
                                <th>Qty</th>
                                <th>Chg Sq In</th>
                                <th>Line Total</th>
                                <th>Warning</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="quoteRows"></tbody>
                    </table>
                    <div class="empty-state" id="emptyState">No artwork added yet.</div>
                </div>
            </div>

            <aside class="summary-panel" aria-label="Quote summary">
                <h2 class="panel-title">Customer Information</h2>
                <div class="field">
                    <label for="invoiceNumber">
                        <span>CRM Invoice Number</span>
                    </label>
                    <input id="invoiceNumber" type="text" autocomplete="off" placeholder="Enter invoice number">
                </div>
                <div class="field">
                    <label for="customerName">
                        <span>Customer / Company</span>
                    </label>
                    <input id="customerName" type="text" list="customerList" autocomplete="off">
                    <datalist id="customerList"></datalist>
                </div>
                <div class="field">
                    <label for="customerContact">
                        <span>Contact</span>
                    </label>
                    <input id="customerContact" type="text" autocomplete="name">
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label for="customerPhone">
                            <span>Phone</span>
                        </label>
                        <input id="customerPhone" type="tel" autocomplete="tel">
                    </div>
                    <div class="field">
                        <label for="customerEmail">
                            <span>Email</span>
                        </label>
                        <input id="customerEmail" type="email" autocomplete="email">
                    </div>
                </div>
                <div class="field">
                    <label for="customerAddress">
                        <span>Customer Address</span>
                    </label>
                    <textarea id="customerAddress" rows="3" autocomplete="street-address"></textarea>
                </div>
                <div class="field">
                    <label for="customerNotes">
                        <span>Job Notes</span>
                    </label>
                    <textarea id="customerNotes" rows="3"></textarea>
                </div>

                <h2 class="panel-title">Pricing</h2>
                <div class="field">
                    <label for="rateInput">
                        <span>Rate per sq in</span>
                        <span id="rateEcho">$0.03</span>
                    </label>
                    <input class="money-input" id="rateInput" type="number" min="0" step="0.001" value="0.03">
                </div>
                <div class="field">
                    <label for="extraFeeLabel">
                        <span>Extra fee label</span>
                    </label>
                    <input id="extraFeeLabel" type="text" value="Rush / Pressing Fee">
                </div>
                <div class="field">
                    <label for="extraFeeInput">
                        <span>Extra fee amount</span>
                        <span id="extraFeeEcho">$0.00</span>
                    </label>
                    <input class="money-input" id="extraFeeInput" type="number" min="0" step="0.01" value="0.00">
                </div>
                <div class="field">
                    <label for="printSpeedInput">
                        <span>Avg print speed</span>
                        <span>sq in / min</span>
                    </label>
                    <input id="printSpeedInput" type="number" min="1" step="1" value="120">
                </div>
                <div class="totals">
                    <div class="total-row">
                        <span>Items</span>
                        <strong id="itemCount">0</strong>
                    </div>
                    <div class="total-row">
                        <span>Charged sq in</span>
                        <strong id="totalSqIn">0.00</strong>
                    </div>
                    <div class="total-row">
                        <span>Full sheet length</span>
                        <strong id="sheetLength">0.00 in</strong>
                    </div>
                    <div class="total-row">
                        <span>Estimated print time</span>
                        <strong id="printTime">0 min</strong>
                    </div>
                    <div class="total-row">
                        <span>Artwork subtotal</span>
                        <strong id="subtotal">$0.00</strong>
                    </div>
                    <div class="total-row extra-fee-row" id="extraFeeRow" hidden>
                        <span id="extraFeeName">Extra fee</span>
                        <strong id="extraFeeTotal">$0.00</strong>
                    </div>
                    <div class="total-row grand-total">
                        <span>Total</span>
                        <strong id="grandTotal">$0.00</strong>
                    </div>
                </div>
            </aside>
        </section>
    </main>

    <template id="rowTemplate">
        <tr>
            <td>
                <div class="image-cell">
                    <img class="thumb" alt="">
                    <div>
                        <div class="file-name"></div>
                        <div class="file-meta"></div>
                    </div>
                </div>
            </td>
            <td class="pixels"></td>
            <td class="dpi"></td>
            <td>
                <div class="size-editor">
                    <div class="size-inputs">
                        <input class="number-input size-input print-width" type="number" min="0.01" step="0.01" aria-label="Print width in inches">
                        <span>x</span>
                        <input class="number-input size-input print-height" type="number" min="0.01" step="0.01" aria-label="Print height in inches">
                    </div>
                    <div class="detected-size"></div>
                </div>
            </td>
            <td><input class="number-input qty" type="number" min="1" step="1" value="1"></td>
            <td class="sq-in"></td>
            <td class="line-total"></td>
            <td class="warning"></td>
            <td><button class="remove-btn" type="button" aria-label="Remove image">X</button></td>
        </tr>
    </template>

    <script src="assets/js/upload-progress.js?v=1"></script>
<script>
        const files = [];
        const dropZone = document.querySelector('#dropZone');
        const fileInput = document.querySelector('#fileInput');
        const quoteRows = document.querySelector('#quoteRows');
        const emptyState = document.querySelector('#emptyState');
        const rowTemplate = document.querySelector('#rowTemplate');
        const statusEl = document.querySelector('#status');
        const rateInput = document.querySelector('#rateInput');
        const extraFeeLabel = document.querySelector('#extraFeeLabel');
        const extraFeeInput = document.querySelector('#extraFeeInput');
        const printSpeedInput = document.querySelector('#printSpeedInput');
        const invoiceNumberInput = document.querySelector('#invoiceNumber');
        const customerFields = {
            name: document.querySelector('#customerName'),
            contact: document.querySelector('#customerContact'),
            phone: document.querySelector('#customerPhone'),
            email: document.querySelector('#customerEmail'),
            address: document.querySelector('#customerAddress'),
            notes: document.querySelector('#customerNotes'),
        };
        const customerList = document.querySelector('#customerList');
        let savedCustomers = [];
        let currentDocumentType = 'Quote';
        const defaultDocumentTitle = document.title;
        const previewMaxPixels = 360;
        const previewJpegQuality = 0.72;
        const printCutGapInches = 0.5;
        const money = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
        const totalsEls = {
            rateEcho: document.querySelector('#rateEcho'),
            extraFeeEcho: document.querySelector('#extraFeeEcho'),
            itemCount: document.querySelector('#itemCount'),
            totalSqIn: document.querySelector('#totalSqIn'),
            sheetLength: document.querySelector('#sheetLength'),
            printTime: document.querySelector('#printTime'),
            subtotal: document.querySelector('#subtotal'),
            extraFeeName: document.querySelector('#extraFeeName'),
            extraFeeTotal: document.querySelector('#extraFeeTotal'),
            extraFeeRow: document.querySelector('#extraFeeRow'),
            grandTotal: document.querySelector('#grandTotal'),
        };

        dropZone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropZone.classList.add('is-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('is-over');
        });

        dropZone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropZone.classList.remove('is-over');
            uploadFiles([...event.dataTransfer.files]);
        });

        fileInput.addEventListener('change', () => {
            uploadFiles([...fileInput.files]);
            fileInput.value = '';
        });

        rateInput.addEventListener('input', updateCalculations);
        extraFeeLabel.addEventListener('input', renderTotals);
        extraFeeInput.addEventListener('input', renderTotals);
        printSpeedInput.addEventListener('input', renderTotals);
        invoiceNumberInput.addEventListener('input', () => updatePrintFields(currentDocumentType));
        customerFields.name.addEventListener('input', applyMatchingCustomer);
        customerFields.name.addEventListener('change', applyMatchingCustomer);

        document.querySelector('#clearBtn').addEventListener('click', () => {
            revokePreviewUrls(files);
            files.splice(0, files.length);
            setStatus('');
            renderRows();
        });

        document.querySelector('#printBtn').addEventListener('click', () => {
            printDocument('Quote');
        });

        document.querySelector('#invoiceBtn').addEventListener('click', () => {
            printDocument('Invoice');
        });

        window.addEventListener('beforeprint', () => {
            preparePrintDocument(currentDocumentType);
        });

        window.addEventListener('afterprint', () => {
            document.title = defaultDocumentTitle;
        });

        window.addEventListener('beforeunload', () => {
            revokePreviewUrls(files);
        });

        async function uploadFiles(selectedFiles) {
            const imageFiles = selectedFiles.filter(isImageFile);
            if (imageFiles.length === 0) {
                setStatus('Choose one or more image files.', true);
                return;
            }

            if (!dtfProgress.start()) return;
            setStatus(`Processing ${imageFiles.length} image${imageFiles.length === 1 ? '' : 's'}...`);
            let successCount = 0;
            const errors = [];
            const processedFiles = [];

            for (const file of imageFiles) {
                dtfProgress.show(`Preparing ${file.name} (${imageFiles.indexOf(file) + 1} of ${imageFiles.length})…`);
                await new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0)));
                try {
                    processedFiles.push(await inspectImageFile(file));
                    successCount += 1;
                } catch (error) {
                    errors.push(`${file.name}: ${error.message}`);
                }
            }

            dtfProgress.finish(`${successCount} of ${imageFiles.length} files ready.${errors.length ? " See errors below." : ""}`);
            if (processedFiles.length > 0) {
                files.push(...processedFiles);
                renderRows();
            }

            if (errors.length > 0) {
                setStatus(errors.join(' '), true);
                return;
            }

            setStatus(`${successCount} image${successCount === 1 ? '' : 's'} processed.`);
        }

        async function inspectImageFile(file) {
            const preview = await createCompressedPreviewFromFile(file);
            const imageDetails = await readImageDetails(file, preview);

            return {
                ...imageDetails,
                id: makeId(),
                qty: 1,
                fileSize: file.size,
                previewUrl: preview.previewUrl,
                previewCompressed: true,
            };
        }

        async function readImageDetails(file, preview) {
            if (isPngFile(file)) {
                return readLocalPngImageDetails(file, preview);
            }

            return readUploadedImageDetails(file);
        }

        async function readLocalPngImageDetails(file, preview) {
            const dpiDetails = await readLocalPngDpi(file);
            const dpi = dpiDetails.dpi || 300;
            const dpiMissing = dpiDetails.dpi === null;
            const printWidth = preview.widthPx / dpi;
            const printHeight = preview.heightPx / dpi;
            const warnings = [];

            if (dpiMissing) {
                warnings.push('DPI metadata missing. Calculated using 300 DPI assumption.');
            } else if (dpi < 299.5) {
                warnings.push('DPI is below 300.');
            }

            return {
                name: file.name,
                mime: file.type || 'image/png',
                widthPx: preview.widthPx,
                heightPx: preview.heightPx,
                dpi: roundNumber(dpi, 2),
                dpiSource: dpiDetails.source,
                dpiMissing,
                printWidth: roundNumber(printWidth, 3),
                printHeight: roundNumber(printHeight, 3),
                squareInches: roundNumber(printWidth * printHeight, 3),
                warnings,
            };
        }

        async function readLocalPngDpi(file) {
            const maxBytesToScan = Math.min(file.size, 4 * 1024 * 1024);
            const buffer = await file.slice(0, maxBytesToScan).arrayBuffer();
            const view = new DataView(buffer);
            const signature = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];

            if (view.byteLength < signature.length || !signature.every((byte, index) => view.getUint8(index) === byte)) {
                return { dpi: null, source: 'assumed' };
            }

            let offset = 8;
            while (offset + 12 <= view.byteLength) {
                const length = view.getUint32(offset);
                const type = String.fromCharCode(
                    view.getUint8(offset + 4),
                    view.getUint8(offset + 5),
                    view.getUint8(offset + 6),
                    view.getUint8(offset + 7)
                );
                const dataOffset = offset + 8;
                const nextOffset = dataOffset + length + 4;

                if (nextOffset > view.byteLength) {
                    break;
                }

                if (type === 'pHYs' && length >= 9) {
                    const xPixelsPerMeter = view.getUint32(dataOffset);
                    const yPixelsPerMeter = view.getUint32(dataOffset + 4);
                    const unit = view.getUint8(dataOffset + 8);

                    if (unit === 1 && xPixelsPerMeter > 0 && yPixelsPerMeter > 0) {
                        return {
                            dpi: Math.min(xPixelsPerMeter, yPixelsPerMeter) * 0.0254,
                            source: 'png-pHYs',
                        };
                    }

                    return { dpi: null, source: 'assumed' };
                }

                if (type === 'IDAT') {
                    break;
                }

                offset = nextOffset;
            }

            return { dpi: null, source: 'assumed' };
        }

        async function readUploadedImageDetails(file) {
            return dtfProgress.upload(file);
        }

        function createCompressedPreviewFromFile(file) {
            return new Promise((resolve, reject) => {
                const originalUrl = URL.createObjectURL(file);
                const image = new Image();

                image.onload = async () => {
                    const widthPx = image.naturalWidth;
                    const heightPx = image.naturalHeight;

                    if (!widthPx || !heightPx) {
                        URL.revokeObjectURL(originalUrl);
                        reject(new Error('Image dimensions could not be read.'));
                        return;
                    }

                    let previewUrl = '';

                    try {
                        previewUrl = await createCompressedPreviewUrl(image, widthPx, heightPx);
                    } catch (error) {
                        URL.revokeObjectURL(originalUrl);
                        reject(new Error('Image preview could not be compressed.'));
                        return;
                    }

                    URL.revokeObjectURL(originalUrl);
                    resolve({
                        previewUrl,
                        widthPx,
                        heightPx,
                    });
                };

                image.onerror = () => {
                    URL.revokeObjectURL(originalUrl);
                    reject(new Error('Image could not be opened in the browser.'));
                };

                image.src = originalUrl;
            });
        }

        function isPngFile(file) {
            return file.type === 'image/png' || /\.png$/i.test(file.name);
        }

        function createCompressedPreviewUrl(image, widthPx, heightPx) {
            const scale = Math.min(1, previewMaxPixels / Math.max(widthPx, heightPx));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(widthPx * scale));
            canvas.height = Math.max(1, Math.round(heightPx * scale));

            const context = canvas.getContext('2d', { alpha: false });
            if (!context) {
                return Promise.reject(new Error('Canvas unavailable.'));
            }

            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);

            return new Promise((resolve, reject) => {
                canvas.toBlob((blob) => {
                    if (!blob) {
                        reject(new Error('Preview compression failed.'));
                        return;
                    }

                    resolve(URL.createObjectURL(blob));
                }, 'image/jpeg', previewJpegQuality);
            });
        }

        function renderRows() {
            quoteRows.innerHTML = '';
            emptyState.hidden = files.length > 0;

            files.forEach((file) => {
                const row = rowTemplate.content.firstElementChild.cloneNode(true);
                const qty = Number(file.qty) || 1;

                row.dataset.fileId = file.id;
                row.querySelector('.thumb').src = file.previewUrl;
                row.querySelector('.thumb').alt = file.name;
                row.querySelector('.file-name').textContent = file.name;
                row.querySelector('.file-meta').textContent = `${file.mime} - ${formatBytes(file.fileSize || 0)}`;
                row.querySelector('.pixels').textContent = `${file.widthPx} x ${file.heightPx}`;
                row.querySelector('.dpi').textContent = `${formatNumber(file.dpi, 2)} (${file.dpiSource})`;
                row.querySelector('.detected-size').textContent = `Detected: ${formatNumber(file.printWidth, 2)}" x ${formatNumber(file.printHeight, 2)}"`;
                row.querySelector('.warning').textContent = file.warnings.join(' ');

                const widthInput = row.querySelector('.print-width');
                const heightInput = row.querySelector('.print-height');
                const qtyInput = row.querySelector('.qty');
                widthInput.value = formatInputNumber(file.overridePrintWidth ?? file.printWidth);
                heightInput.value = formatInputNumber(file.overridePrintHeight ?? file.printHeight);
                qtyInput.value = qty;

                widthInput.addEventListener('input', () => {
                    file.overridePrintWidth = getPositiveInputValue(widthInput);
                    updateRowCalculations(row, file);
                    renderTotals();
                });

                heightInput.addEventListener('input', () => {
                    file.overridePrintHeight = getPositiveInputValue(heightInput);
                    updateRowCalculations(row, file);
                    renderTotals();
                });

                qtyInput.addEventListener('input', () => {
                    file.qty = Math.max(1, Number(qtyInput.value) || 1);
                    updateRowCalculations(row, file);
                    renderTotals();
                });

                row.querySelector('.remove-btn').addEventListener('click', () => {
                    const index = files.findIndex((item) => item.id === file.id);
                    if (index >= 0) {
                        revokePreviewUrls([files[index]]);
                        files.splice(index, 1);
                        renderRows();
                    }
                });

                updateRowCalculations(row, file);
                quoteRows.append(row);
            });

            renderTotals();
        }

        function updateCalculations() {
            const rowsById = new Map(
                [...quoteRows.querySelectorAll('tr[data-file-id]')].map((row) => [row.dataset.fileId, row])
            );

            files.forEach((file) => {
                const row = rowsById.get(file.id);
                if (row) {
                    updateRowCalculations(row, file);
                }
            });

            renderTotals();
        }

        function updateRowCalculations(row, file) {
            const qty = Math.max(1, Number(file.qty) || 1);
            const rate = getRate();
            const sqIn = getChargeableSquareInches(file) * qty;
            const lineTotal = sqIn * rate;

            row.querySelector('.sq-in').textContent = formatNumber(sqIn, 2);
            row.querySelector('.line-total').textContent = money.format(lineTotal);
        }

        function renderTotals() {
            const rate = getRate();
            const extraFee = getExtraFee();
            const extraFeeName = extraFeeLabel.value.trim() || 'Extra fee';
            const totalSqIn = files.reduce((sum, file) => sum + (getChargeableSquareInches(file) * (Number(file.qty) || 1)), 0);
            const sheetLength = files.reduce((sum, file) => sum + (getChargeableSheetLength(file) * (Number(file.qty) || 1)), 0);
            const subtotal = totalSqIn * rate;
            const grandTotal = subtotal + extraFee;

            totalsEls.rateEcho.textContent = money.format(rate);
            totalsEls.extraFeeEcho.textContent = money.format(extraFee);
            totalsEls.itemCount.textContent = String(files.reduce((sum, file) => sum + (Number(file.qty) || 1), 0));
            totalsEls.totalSqIn.textContent = formatNumber(totalSqIn, 2);
            totalsEls.sheetLength.textContent = `${formatNumber(sheetLength, 2)} in`;
            totalsEls.printTime.textContent = formatPrintTime(totalSqIn / getPrintSpeed());
            totalsEls.subtotal.textContent = money.format(subtotal);
            totalsEls.extraFeeName.textContent = extraFeeName;
            totalsEls.extraFeeTotal.textContent = money.format(extraFee);
            totalsEls.extraFeeRow.hidden = extraFee <= 0;
            totalsEls.grandTotal.textContent = money.format(grandTotal);
        }

        function getRate() {
            return Math.max(0, Number(rateInput.value) || 0);
        }

        function getExtraFee() {
            return Math.max(0, Number(extraFeeInput.value) || 0);
        }

        function getPrintSpeed() {
            return Math.max(1, Number(printSpeedInput.value) || 120);
        }

        function getChargeableSquareInches(file) {
            const width = getEffectivePrintDimension(file.overridePrintWidth, file.printWidth);
            const height = getEffectivePrintDimension(file.overridePrintHeight, file.printHeight);

            return width * (height + getCutGapHeight(file));
        }

        function getCutGapSquareInches(file) {
            const width = getEffectivePrintDimension(file.overridePrintWidth, file.printWidth);

            return width * getCutGapHeight(file);
        }

        function getChargeableSheetLength(file) {
            const height = getEffectivePrintDimension(file.overridePrintHeight, file.printHeight);

            return height + getCutGapHeight(file);
        }

        function getCutGapHeight(file) {
            return printCutGapInches;
        }

        function getEffectivePrintDimension(overrideValue, detectedValue) {
            const overrideNumber = Number(overrideValue);
            if (Number.isFinite(overrideNumber) && overrideNumber > 0) {
                return overrideNumber;
            }

            return Math.max(0, Number(detectedValue) || 0);
        }

        function getPositiveInputValue(input) {
            const value = Number(input.value);
            return Number.isFinite(value) && value > 0 ? roundNumber(value, 3) : null;
        }

        function formatInputNumber(value) {
            return String(roundNumber(value, 3));
        }

        function formatNumber(value, digits) {
            return Number(value).toLocaleString('en-US', {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits,
            });
        }

        function formatPrintTime(minutes) {
            const safeMinutes = Math.max(0, Number(minutes) || 0);
            if (safeMinutes < 1) {
                return '< 1 min';
            }

            const roundedMinutes = Math.ceil(safeMinutes);
            if (roundedMinutes < 60) {
                return `${roundedMinutes} min`;
            }

            const hours = Math.floor(roundedMinutes / 60);
            const remainingMinutes = roundedMinutes % 60;

            return remainingMinutes > 0 ? `${hours} hr ${remainingMinutes} min` : `${hours} hr`;
        }

        function roundNumber(value, digits) {
            const multiplier = 10 ** digits;
            return Math.round(Number(value) * multiplier) / multiplier;
        }

        function formatBytes(bytes) {
            if (!bytes) {
                return '0 bytes';
            }

            const units = ['bytes', 'KB', 'MB', 'GB'];
            let value = bytes;
            let unitIndex = 0;

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024;
                unitIndex += 1;
            }

            return `${formatNumber(value, unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
        }

        function revokePreviewUrls(items) {
            items.forEach((item) => {
                if (item.previewUrl && item.previewUrl.startsWith('blob:')) {
                    URL.revokeObjectURL(item.previewUrl);
                }
            });
        }

        function setStatus(message, isError = false) {
            statusEl.textContent = message;
            statusEl.classList.toggle('error', isError);
        }

        function printDocument(type) {
            currentDocumentType = type;
            preparePrintDocument(type);
            window.print();
        }

        function preparePrintDocument(type) {
            const invoiceNumber = ensureInvoiceNumber();
            const customerName = customerFields.name.value.trim() || 'Customer';
            document.title = `${sanitizeFileNamePart(customerName)}-${sanitizeFileNamePart(invoiceNumber)}`;
            updatePrintFields(type);
        }

        function ensureInvoiceNumber() {
            const existingInvoiceNumber = invoiceNumberInput.value.trim();
            if (existingInvoiceNumber) {
                return existingInvoiceNumber;
            }

            const now = new Date();
            const datePart = [
                now.getFullYear(),
                String(now.getMonth() + 1).padStart(2, '0'),
                String(now.getDate()).padStart(2, '0'),
            ].join('');
            const uniquePart = String(now.getTime()).slice(-4);
            const generatedInvoiceNumber = `DTF-${datePart}-${uniquePart}`;
            invoiceNumberInput.value = generatedInvoiceNumber;
            return generatedInvoiceNumber;
        }

        function sanitizeFileNamePart(value) {
            return value
                .replace(/[\\/:*?"<>|]+/g, '-')
                .replace(/\s+/g, ' ')
                .replace(/[. ]+$/g, '')
                .trim() || 'Untitled';
        }

        function updatePrintFields(type = 'Quote') {
            document.querySelector('#printDocumentTitle').textContent = type;
            const printInvoiceNumber = document.querySelector('#printInvoiceNumber');
            const invoiceNumber = invoiceNumberInput.value.trim();
            printInvoiceNumber.textContent = invoiceNumber ? `Invoice #: ${invoiceNumber}` : '';
            printInvoiceNumber.hidden = !invoiceNumber;
            document.querySelector('#printDate').textContent = new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });

            setPrintText('#printCustomerName', customerFields.name.value || '-');
            setPrintText('#printCustomerContact', customerFields.contact.value ? `Contact: ${customerFields.contact.value}` : '');
            setPrintText('#printCustomerPhone', customerFields.phone.value);
            setPrintText('#printCustomerEmail', customerFields.email.value);
            setPrintText('#printCustomerAddress', customerFields.address.value || '-');
            setPrintText('#printCustomerNotes', customerFields.notes.value);
        }

        function setPrintText(selector, value) {
            document.querySelector(selector).textContent = value;
        }

        async function loadCustomers() {
            try {
                const response = await fetch('customers.json', { cache: 'no-store' });
                if (!response.ok) {
                    throw new Error('Customer list could not be loaded.');
                }

                savedCustomers = await response.json();
                customerList.innerHTML = '';

                savedCustomers.forEach((customer) => {
                    const option = document.createElement('option');
                    option.value = customer.company;
                    option.label = customer.contact ? `${customer.company} - ${customer.contact}` : customer.company;
                    customerList.append(option);
                });
            } catch (error) {
                setStatus(error.message, true);
            }
        }

        function applyMatchingCustomer() {
            const typedName = normalizeCustomerName(customerFields.name.value);
            if (!typedName) {
                return;
            }

            const match = savedCustomers.find((customer) => normalizeCustomerName(customer.company) === typedName);
            if (!match) {
                return;
            }

            fillCustomer(match);
        }

        function fillCustomer(customer) {
            customerFields.name.value = customer.company || '';
            customerFields.contact.value = customer.contact || '';
            customerFields.phone.value = customer.phone || '';
            customerFields.email.value = customer.email || '';
            customerFields.address.value = customer.address || '';
            customerFields.notes.value = customer.notes || customerFields.notes.value;
            updatePrintFields(currentDocumentType);
        }

        function normalizeCustomerName(value) {
            return value.trim().toLowerCase();
        }

        function isImageFile(file) {
            if (file.type && file.type.startsWith('image/')) {
                return true;
            }

            return /\.(png|jpe?g|webp|gif)$/i.test(file.name);
        }

        function makeId() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        }

        renderRows();
        updatePrintFields();
        loadCustomers();
    </script>
</body>
</html>
