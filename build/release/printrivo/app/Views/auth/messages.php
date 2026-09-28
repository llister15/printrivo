<?php if (session('error')): ?><div class="form-alert" role="alert"><?= esc(session('error')) ?></div><?php endif ?>
<?php if (session('errors')): ?><div class="form-alert" role="alert"><ul><?php foreach ((array) session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php if (session('message')): ?><div class="form-message" role="status"><?= esc(session('message')) ?></div><?php endif ?>
