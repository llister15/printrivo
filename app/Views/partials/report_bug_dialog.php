<dialog id="printrivo-bug-dialog" class="printrivo-bug-dialog" aria-labelledby="printrivo-bug-title">
<div class="printrivo-bug-heading"><h2 id="printrivo-bug-title">Report a bug</h2><button type="button" data-close-report aria-label="Close bug report">×</button></div>
<p class="printrivo-bug-intro">Something not working? Tell us what happened so we can help.</p>
<form method="post" action="<?= site_url('support/bug-report') ?>" id="printrivo-bug-form">
<?= csrf_field() ?>
<input type="hidden" name="tool" value="<?= esc($toolRoute,'attr') ?>">
<label for="printrivo-bug-summary">Short summary</label>
<input id="printrivo-bug-summary" name="summary" maxlength="120" required placeholder="For example: my export won’t download">
<label for="printrivo-bug-description">What happened?</label>
<textarea id="printrivo-bug-description" name="description" rows="4" minlength="10" maxlength="3000" required placeholder="What were you trying to do? What steps led to the problem?"></textarea>
<label for="printrivo-bug-expected">What did you expect? <span>(optional)</span></label>
<textarea id="printrivo-bug-expected" name="expected" rows="2" maxlength="1000"></textarea>
<p class="printrivo-bug-note">We’ll include the tool name and your account email so we can reply. Your artwork isn’t attached. Please leave out passwords and payment details.</p>
<p class="printrivo-bug-status" role="status" aria-live="polite" data-bug-status></p>
<div class="printrivo-bug-actions"><button type="button" data-close-report>Close</button><button type="submit" data-send-report>Send report</button></div>
</form>
</dialog>
<script src="<?= base_url('assets/js/report-bug.js') ?>"></script>
