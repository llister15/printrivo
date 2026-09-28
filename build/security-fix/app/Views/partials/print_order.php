<div id="print-order-feedback" class="print-order-feedback" role="status" aria-live="polite" hidden
 data-authorize-endpoint="<?= site_url('api/print-orders/authorize') ?>" data-captcha-required="<?= config('Recaptcha')->enabled?'1':'0' ?>"
 data-endpoint="<?= site_url('api/print-orders') ?>" data-connected="<?= config('CswPrint')->ready()?'1':'0' ?>"
 data-csrf-header="<?= csrf_header() ?>" data-csrf-token="<?= csrf_hash() ?>">
 <span></span><button type="button" aria-label="Dismiss print order message">×</button>
</div>
<script src="<?= base_url('assets/js/print-order.js?v=20260924-print-verification') ?>"></script>
