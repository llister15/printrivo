<div id="print-order-feedback" class="print-order-feedback" role="status" aria-live="polite" hidden
 data-authorize-endpoint="<?= site_url('api/print-orders/authorize') ?>" data-captcha-required="<?= config('Recaptcha')->enabled?'1':'0' ?>"
 data-endpoint="<?= site_url('api/print-orders') ?>" data-connected="<?= config('CswPrint')->ready()?'1':'0' ?>"
 data-csrf-header="<?= csrf_header() ?>" data-csrf-token="<?= csrf_hash() ?>">
 <span></span><button type="button" aria-label="Dismiss print order message">×</button>
</div>
<link rel="stylesheet" href="<?= base_url('assets/css/print-confirmation.css?v=20260924-1') ?>">
<dialog id="print-order-confirmation" class="print-confirmation" aria-labelledby="print-confirmation-title">
 <form>
  <h2 id="print-confirmation-title">Order from CSW Print</h2>
  <p data-dimensions></p>
  <div class="print-confirmation-price"><span>Print subtotal</span><strong data-price></strong></div>
  <p>$0.03 per square inch · $15 minimum per print.<br>Tax and shipping, if applicable, are additional.</p>
  <label for="print-fulfillment">Delivery method</label>
  <select id="print-fulfillment" name="fulfillment" required><option value="pickup">Pickup at CSW Print</option><option value="shipping">Shipping</option></select>
  <div data-shipping hidden>
   <label for="print-shipping-address">Recipient and complete shipping address</label>
   <textarea id="print-shipping-address" name="shipping_address" rows="4" minlength="10" maxlength="1000" autocomplete="street-address" placeholder="Full name, street, city, state, postal code and country"></textarea>
   <p>CSW will quote shipping separately.</p>
  </div>
  <p>Confirming sends your artwork and order to CSW. No payment is collected here. Retry unchanged order details if a request is not confirmed.</p>
  <div class="print-confirmation-actions"><button type="button" data-cancel autofocus>Cancel</button><button type="submit">Confirm &amp; send order</button></div>
 </form>
</dialog>
<script src="<?= base_url('assets/js/print-confirmation.js?v=20260924-1') ?>"></script>
<script src="<?= base_url('assets/js/print-order.js?v=20260924-print-confirmation') ?>"></script>
